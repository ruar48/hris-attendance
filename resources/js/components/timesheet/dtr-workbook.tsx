import { Head, Link, router } from '@inertiajs/react';
import { History, Maximize2, Menu, Minimize2, Printer, RotateCcw, Save, Sheet } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import type { FocusEvent, KeyboardEvent } from 'react';
import { toast } from 'sonner';
import { EmployeeCombobox } from '@/components/employee-combobox';
import type { EmployeeOption } from '@/components/employee-combobox';
import { useOptionList } from '@/hooks/use-option-list';

type DayStatus = 'worked' | 'day_off' | 'absent' | 'leave';

type DayResult = {
    worked_minutes: number;
    late_minutes: number;
    undertime_minutes: number;
    ot_minutes: number;
    is_incomplete: boolean;
};

type Day = {
    date: string;
    day: number;
    dow: string;
    is_sunday: boolean;
    holiday: string | null;
    schedule: string | null;
    time_in: string;
    break_out: string;
    break_in: string;
    time_out: string;
    day_status: DayStatus;
    remarks: string;
    result: DayResult | null;
};

type HistoryEntry = {
    id: number;
    at: string;
    user: string;
    work_date: string;
    action: 'created' | 'updated' | 'deleted';
    changes: Record<string, [string | null, string | null]>;
};

type SheetEmployee = EmployeeOption & { position: string | null };

export type ScheduleWeek = {
    days: { date: string; label: string; dow: string; in_month: boolean; holiday: string | null }[];
    rows: {
        employee_id: number | null;
        name: string;
        position: string | null;
        time: string | null;
        cells: ('work' | 'off' | 'holiday' | 'leave' | null)[];
        remarks: string[];
    }[];
};

export type TimesheetPageProps = {
    sites: { value: string; label: string }[];
    /** The selected site's value, or "all". */
    site: string;
    employees: SheetEmployee[];
    employee: SheetEmployee | null;
    month: string;
    days: Day[];
    history: HistoryEntry[];
    official_hours: string | null;
    in_charge: string | null;
};

export type SheetName = 'manning' | 'schedule' | 'dtr';

export type Manning = {
    title: string;
    operation_hours: string | null;
    groups: {
        title: string;
        rows: {
            employee_id: number;
            name: string;
            start_date: string | null;
            shift_schedule: string | null;
            schedule: string | null;
            position: string | null;
        }[];
    }[];
    unassigned: EmployeeOption[];
};

type Props = TimesheetPageProps & {
    /** Full-screen workbook: no app chrome, with MANNING and SCHEDULE tabs first. */
    fullScreen?: boolean;
    sheet?: SheetName;
    schedule?: ScheduleWeek[];
    manning?: Manning | null;
};

// The four DTR time columns, in paper order.
const TIME_FIELDS = ['time_in', 'break_out', 'break_in', 'time_out'] as const;
type TimeField = (typeof TIME_FIELDS)[number];

type Row = Pick<Day, 'date' | TimeField | 'day_status' | 'remarks'>;

const STATUS_LABELS: Record<DayStatus, string> = {
    worked: 'Worked',
    day_off: 'Day Off',
    absent: 'Absent',
    leave: 'Leave',
};

const FIELD_LABELS: Record<string, string> = {
    time_in: 'AM arrival',
    break_out: 'AM departure',
    break_in: 'PM arrival',
    time_out: 'PM departure',
    day_status: 'status',
    remarks: 'remarks',
};

// Words typed into a time cell that mark the whole day instead, the way the
// paper sheet writes "Day Off" across the row.
const STATUS_WORDS: Record<string, DayStatus> = {
    off: 'day_off',
    'day off': 'day_off',
    dayoff: 'day_off',
    do: 'day_off',
    absent: 'absent',
    abs: 'absent',
    leave: 'leave',
};

// Grid columns used for keyboard movement: remarks margin, then the four times.
const NAV_COLUMNS = 5;

// Sheet palette taken from the workbook.
const PAPER = 'bg-[#f6e3ad]';
const INK_BORDER = 'border border-[#3b3220]';

const toRow = (day: Day): Row => ({
    date: day.date,
    time_in: day.time_in,
    break_out: day.break_out,
    break_in: day.break_in,
    time_out: day.time_out,
    day_status: day.day_status,
    remarks: day.remarks,
});

const sameRow = (a: Row, b: Row) =>
    TIME_FIELDS.every((field) => a[field] === b[field]) &&
    a.day_status === b.day_status &&
    a.remarks.trim() === b.remarks.trim();

/**
 * Accepts the shorthand people type into a spreadsheet — "645", "6:45",
 * "6.45", "18", "6:45pm" — and returns "H:mm" the way the paper DTR writes
 * it. Returns null when the text can't be read as a time, and "" for an empty
 * cell.
 */
function normalizeTime(raw: string): string | null {
    const text = raw.trim().toLowerCase().replace(/\s+/g, '');

    if (text === '') {
        return '';
    }

    const match = text.match(/^(\d{1,2})(?:[:.]?(\d{2}))?(am|pm|a|p)?$/);

    if (!match) {
        return null;
    }

    let hours = Number(match[1]);
    const minutes = Number(match[2] ?? 0);
    const meridiem = match[3]?.[0];

    if (meridiem) {
        if (hours < 1 || hours > 12) {
            return null;
        }

        hours = (hours % 12) + (meridiem === 'p' ? 12 : 0);
    }

    if (hours > 23 || minutes > 59) {
        return null;
    }

    return `${hours}:${String(minutes).padStart(2, '0')}`;
}

/** "6:45" → "06:45", the format the server validates. */
const toPayloadTime = (value: string) => {
    const normalized = normalizeTime(value);

    return normalized ? normalized.padStart(5, '0') : null;
};

const statusWord = (value: string): DayStatus | undefined =>
    STATUS_WORDS[value.trim().toLowerCase().replace(/\s+/g, ' ')];

/**
 * Apply what was typed: a status word in any time cell marks the day, and
 * every time is normalized. Leaves unreadable times as typed.
 */
function interpret(row: Row): Row {
    for (const field of TIME_FIELDS) {
        const status = statusWord(row[field]);

        if (status) {
            return { ...row, day_status: status, time_in: '', break_out: '', break_in: '', time_out: '' };
        }
    }

    const next = { ...row };

    for (const field of TIME_FIELDS) {
        const normalized = normalizeTime(row[field]);

        if (normalized !== null) {
            next[field] = normalized;
        }
    }

    return next;
}

/** Minutes as the sheet writes them: "2hr", "2hr/30mins", "27mins". */
function formatHours(minutes: number): string {
    if (minutes <= 0) {
        return '';
    }

    const h = Math.floor(minutes / 60);
    const m = minutes % 60;
    const hours = h > 0 ? `${h}${h === 1 ? 'hr' : 'hrs'}` : '';
    const mins = m > 0 ? `${m}mins` : '';

    return [hours, mins].filter(Boolean).join('/');
}

function focusCell(row: number, col: number, step: 1 | -1, rowCount: number) {
    // Skip rows that have no input in this column (a merged "Day Off" row).
    for (let r = row; r >= 0 && r < rowCount; r += step) {
        const cell = document.querySelector<HTMLElement>(`[data-cell="${r}-${col}"]`);

        if (cell) {
            cell.focus();

            return;
        }
    }
}

const monthLabel = (month: string, style: 'long' | 'short') =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('en-US', { month: style, year: 'numeric' });

export function DtrWorkbook({
    sites,
    site,
    employees,
    employee,
    month,
    days,
    history,
    official_hours,
    in_charge,
    fullScreen = false,
    sheet = 'dtr',
    schedule = [],
    manning = null,
}: Props) {
    const siteLabel = sites.find((s) => s.value === site)?.label ?? 'All Sites';

    const [rows, setRows] = useState<Row[]>(() => days.map(toRow));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [loadedDays, setLoadedDays] = useState(days);

    // Fresh props arrive after every save or employee/month switch; reset the
    // grid to them during render rather than in an effect.
    if (loadedDays !== days) {
        setLoadedDays(days);
        setRows(days.map(toRow));
        setErrors({});
    }

    const dirty = useMemo(
        () =>
            rows
                .map(interpret)
                .filter((row, i) => days[i] && !sameRow(row, toRow(days[i]))),
        [rows, days],
    );

    useEffect(() => {
        if (dirty.length === 0) {
            return;
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        const removeRouterGuard = router.on('before', (event) => {
            // Saving is itself a visit; only guard navigation away.
            if (event.detail.visit.method !== 'get') {
                return;
            }

            return window.confirm('You have unsaved time sheet changes. Leave without saving?');
        });

        return () => {
            window.removeEventListener('beforeunload', warn);
            removeRouterGuard();
        };
    }, [dirty.length]);

    const totals = useMemo(
        () =>
            days.reduce(
                (sum, day) => ({
                    ot: sum.ot + (day.result?.ot_minutes ?? 0),
                    lates: sum.lates + (day.result?.late_minutes ?? 0) + (day.result?.undertime_minutes ?? 0),
                }),
                { ot: 0, lates: 0 },
            ),
        [days],
    );

    const employeeIndex = employee ? employees.findIndex((e) => e.id === employee.id) + 1 : 0;

    const update = (index: number, patch: Partial<Row>) => {
        setRows((current) => current.map((row, i) => (i === index ? { ...row, ...patch } : row)));
    };

    const commit = (index: number) => {
        const next = interpret(rows[index]);

        if (!sameRow(next, rows[index])) {
            update(index, next);
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLElement>, row: number, col: number) => {
        const target = event.currentTarget;
        const isInput = target instanceof HTMLInputElement;
        const atStart = isInput && target.selectionStart === 0 && target.selectionEnd === 0;
        const atEnd = isInput && target.selectionStart === target.value.length;
        const allSelected = isInput && target.selectionStart === 0 && target.selectionEnd === target.value.length;

        if (event.key === 'Enter' || (event.key === 'ArrowDown' && isInput)) {
            event.preventDefault();
            focusCell(row + 1, col, 1, rows.length);
        } else if (event.key === 'ArrowUp' && isInput) {
            event.preventDefault();
            focusCell(row - 1, col, -1, rows.length);
        } else if (event.key === 'ArrowRight' && (atEnd || allSelected) && col < NAV_COLUMNS - 1) {
            event.preventDefault();
            document.querySelector<HTMLElement>(`[data-cell="${row}-${col + 1}"]`)?.focus();
        } else if (event.key === 'ArrowLeft' && (atStart || allSelected) && col > 0) {
            event.preventDefault();
            document.querySelector<HTMLElement>(`[data-cell="${row}-${col - 1}"]`)?.focus();
        }
    };

    // Typing into a cell replaces it, like a spreadsheet.
    const selectAll = (event: FocusEvent<HTMLInputElement>) => event.target.select();

    const visit = (params: { employee_id?: number | string; month?: string; sheet?: SheetName; site?: string }) => {
        router.get(
            fullScreen ? '/timesheet/workbook' : '/timesheet',
            {
                site,
                // Switching site drops the employee, who may not work there.
                ...(params.site ? {} : { employee_id: employee?.id }),
                month,
                ...(fullScreen ? { sheet } : {}),
                ...params,
            },
            { preserveScroll: true, preserveState: false },
        );
    };

    const save = () => {
        if (!employee || dirty.length === 0) {
            return;
        }

        if (dirty.some((row) => TIME_FIELDS.some((field) => normalizeTime(row[field]) === null))) {
            toast.error('Fix the highlighted times before saving.');

            return;
        }

        const payload = dirty.map((row) => {
            const worked = row.day_status === 'worked';

            return {
                date: row.date,
                time_in: worked ? toPayloadTime(row.time_in) : null,
                break_out: worked ? toPayloadTime(row.break_out) : null,
                break_in: worked ? toPayloadTime(row.break_in) : null,
                time_out: worked ? toPayloadTime(row.time_out) : null,
                day_status: row.day_status,
                remarks: row.remarks.trim() || null,
            };
        });

        router.put(
            '/timesheet',
            { employee_id: employee.id, rows: payload },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
                onError: (errs) => {
                    // Server errors are keyed by position in the payload; map
                    // them back onto the dates they belong to.
                    const byDate: Record<string, string> = {};

                    Object.entries(errs).forEach(([key, message]) => {
                        const date = payload[Number(key.split('.')[1])]?.date;

                        if (date) {
                            byDate[date] = message;
                        }
                    });
                    setErrors(byDate);
                    toast.error(Object.values(errs)[0] ?? 'Could not save the time sheet.');
                },
            },
        );
    };

    const cell = `${INK_BORDER} ${PAPER} px-1.5 py-0.5`;
    const timeInput =
        'h-6 w-full bg-transparent px-1 text-center tabular-nums outline-none focus:bg-white focus:outline-2 focus:outline-[#1a73e8]';

    return (
        <>
            <Head title={fullScreen ? "Time Sheet Workbook" : "Time Sheet"} />
            {/* Print only the form, the way the paper copy is printed. */}
            <style>{`@media print {
                body * { visibility: hidden; }
                .dtr-print, .dtr-print * { visibility: visible; }
                .dtr-print { position: absolute; inset: 0 auto auto 0; }
                .dtr-no-print { display: none !important; }
            }`}</style>

            <div className={fullScreen ? 'flex h-dvh flex-col overflow-hidden' : 'flex flex-1 flex-col'}>
                {/* Toolbar, styled after the spreadsheet's. */}
                <div className="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-[#f9fbfd] px-4 py-2.5">
                    <div className="mr-2 flex items-center gap-2.5">
                        {fullScreen && <Sheet className="size-7 text-[#188038]" strokeWidth={1.75} />}
                        <div>
                            <div className="text-[15px] font-medium text-slate-800">{siteLabel} Time Sheet</div>
                            <div className="text-xs text-slate-500">
                                {{ manning: 'Manning', schedule: 'Schedule', dtr: 'Daily Time Record' }[sheet]} ·{' '}
                                {monthLabel(month, 'long')}
                            </div>
                        </div>
                    </div>

                    <select
                        aria-label="Site"
                        className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium"
                        value={site}
                        onChange={(event) => visit({ site: event.target.value })}
                    >
                        {sites.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                        <option value="all">All sites</option>
                    </select>
                    <input
                        type="month"
                        aria-label="Month"
                        className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm"
                        value={month}
                        onChange={(event) => event.target.value && visit({ month: event.target.value })}
                    />
                    <div className="w-64 text-sm">
                        <EmployeeCombobox
                            employees={employees}
                            value={sheet === 'dtr' ? (employee?.id?.toString() ?? '') : ''}
                            onChange={(id) => visit({ employee_id: id, sheet: 'dtr' })}
                        />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        <Link
                            href={fullScreen ? '/timesheet' : '/timesheet/workbook'}
                            data={{ site, employee_id: employee?.id, month }}
                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200/60"
                        >
                            {fullScreen ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                            {fullScreen ? 'Exit full screen' : 'Full screen'}
                        </Link>
                        {dirty.length > 0 && (
                            <span className="text-sm text-amber-700">
                                {dirty.length} unsaved {dirty.length === 1 ? 'day' : 'days'}
                            </span>
                        )}
                        <button
                            type="button"
                            onClick={() => window.print()}
                            disabled={!employee}
                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200/60 disabled:opacity-50"
                        >
                            <Printer className="size-4" />
                            Print
                        </button>
                        <button
                            type="button"
                            disabled={dirty.length === 0 || saving}
                            onClick={() => {
                                setRows(days.map(toRow));
                                setErrors({});
                            }}
                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200/60 disabled:opacity-50"
                        >
                            <RotateCcw className="size-4" />
                            Discard
                        </button>
                        <button
                            type="button"
                            disabled={dirty.length === 0 || saving}
                            onClick={save}
                            className="inline-flex items-center gap-1.5 rounded-full bg-[#c2e7ff] px-4 py-1.5 text-sm font-medium text-[#001d35] hover:bg-[#b3dcf7] disabled:opacity-50"
                        >
                            <Save className="size-4" />
                            {saving ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </div>

                {/* The sheet. */}
                <div className="flex-1 overflow-auto bg-white bg-[linear-gradient(#e2e3e3_1px,transparent_1px),linear-gradient(90deg,#e2e3e3_1px,transparent_1px)] bg-[size:100px_21px] px-4 py-6">
                    {sheet === 'manning' && manning ? (
                        <ManningSheet
                            manning={manning}
                            site={site}
                            onOpen={(id) => visit({ employee_id: id, sheet: 'dtr' })}
                        />
                    ) : sheet === 'schedule' ? (
                        <ScheduleSheet weeks={schedule} onOpen={(id) => visit({ employee_id: id, sheet: 'dtr' })} />
                    ) : (
                    <>
                    {!employee ? (
                        <div className="mx-auto max-w-md bg-white p-8 text-center text-sm text-slate-500">
                            Add an active employee first to start encoding time sheets.
                        </div>
                    ) : (
                        <table
                            className="dtr-print mx-auto border-collapse font-[Calibri,Carlito,Arial,sans-serif] text-[13px] text-[#1f1a10]"
                        >
                            <colgroup>
                                <col className="w-40" />
                                <col className="w-14" />
                                <col className="w-[88px]" />
                                <col className="w-[88px]" />
                                <col className="w-[88px]" />
                                <col className="w-[88px]" />
                                <col className="w-[96px]" />
                                <col className="w-[96px]" />
                                <col className="w-28" />
                            </colgroup>

                            <thead>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-r-0 text-[11px]`}>No.</td>
                                    <td className={`${cell} border-x-0 text-center font-semibold`}>{employeeIndex}</td>
                                    <td className={`${cell} border-x-0`} />
                                    <td className={`${cell} border-x-0`} />
                                    <td className={`${cell} border-x-0 text-[11px]`}>Employee No.:</td>
                                    <td className={`${cell} border-l-0 text-right font-semibold`} colSpan={2}>
                                        {employee.employee_code}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} text-center text-[15px] font-bold`} colSpan={7}>
                                        DAILY TIME RECORD
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-b-0 py-1 text-center text-lg font-bold`} colSpan={7}>
                                        {employee.last_name}, {employee.first_name}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-t-0 text-center`} colSpan={7}>
                                        (Name)
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-r-0 border-b-0 italic`}>Month</td>
                                    <td className={`${cell} border-x-0 border-b-0 text-center`} colSpan={2}>
                                        {monthLabel(month, 'short')}
                                    </td>
                                    <td className={`${cell} border-x-0 border-b-0`} />
                                    <td className={`${cell} border-x-0 border-b-0`}>Position</td>
                                    <td className={`${cell} border-l-0 border-b-0 font-semibold`} colSpan={2}>
                                        {employee.position ?? ''}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-y-0 border-r-0`} />
                                    <td className={`${cell} border-y-0 border-x-0 italic`} colSpan={3}>
                                        Official hours for arrival
                                    </td>
                                    <td className={`${cell} border-y-0 border-l-0 text-[11px] italic`} colSpan={3}>
                                        Regular days: {official_hours ?? '—'}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-t-0 border-r-0`} />
                                    <td className={`${cell} border-t-0 border-x-0 italic`} colSpan={3}>
                                        and departure
                                    </td>
                                    <td className={`${cell} border-t-0 border-l-0 text-[11px] italic`} colSpan={3}>
                                        Saturdays &amp; Sundays: as required
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} h-4`} colSpan={7} />
                                    <td />
                                </tr>
                                <tr>
                                    <td className="dtr-no-print px-1 text-[11px] font-medium text-slate-500">Remarks</td>
                                    <td className={`${cell} text-center font-semibold`} rowSpan={2}>
                                        Day
                                    </td>
                                    <td className={`${cell} text-center font-semibold`} colSpan={2}>
                                        AM
                                    </td>
                                    <td className={`${cell} text-center font-semibold`} colSpan={2}>
                                        PM
                                    </td>
                                    <td className={`${cell} text-center text-[12px] font-semibold`} colSpan={2}>
                                        overtime/Lates
                                    </td>
                                    <td className="dtr-no-print px-2 text-[11px] font-medium text-slate-500">Mark day</td>
                                </tr>
                                <tr className="text-[11px]">
                                    <td />
                                    <td className={`${cell} text-center`}>Arrival</td>
                                    <td className={`${cell} text-center`}>Departure</td>
                                    <td className={`${cell} text-center`}>Arrival</td>
                                    <td className={`${cell} text-center`}>Departure</td>
                                    <td className={`${cell} text-center`}>Hours/Minutes</td>
                                    <td className={`${cell} text-center`}>Hours/Minutes</td>
                                    <td />
                                </tr>
                            </thead>

                            <tbody>
                                {days.map((day, i) => {
                                    const row = rows[i] ?? toRow(day);
                                    const isDirty = !sameRow(interpret(row), toRow(day));
                                    const worked = row.day_status === 'worked';
                                    const error = errors[day.date];
                                    // Results reflect the saved entry, so hide them while the row is being edited.
                                    const result = isDirty ? null : day.result;
                                    const lates = (result?.late_minutes ?? 0) + (result?.undertime_minutes ?? 0);
                                    const highlight = day.holiday ? 'bg-[#fff200]' : PAPER;
                                    const noteFilled = row.remarks.trim() !== '';

                                    return (
                                        <tr key={day.date} className="h-[23px]">
                                            {/* Notes margin, like the yellow notes left of each DTR. */}
                                            <td className="p-0 pr-1">
                                                <input
                                                    data-cell={`${i}-0`}
                                                    className={`h-[22px] w-full px-1 text-[12px] outline-none placeholder:text-[#8a7a00] focus:bg-white focus:outline-2 focus:outline-[#1a73e8] ${
                                                        noteFilled || day.holiday ? 'bg-[#fff200]' : 'bg-transparent'
                                                    }`}
                                                    value={row.remarks}
                                                    placeholder={day.holiday ?? ''}
                                                    title={day.holiday ?? row.remarks}
                                                    maxLength={255}
                                                    onFocus={selectAll}
                                                    onChange={(event) => update(i, { remarks: event.target.value })}
                                                    onKeyDown={(event) => onKeyDown(event, i, 0)}
                                                />
                                            </td>

                                            <td
                                                className={`${INK_BORDER} ${PAPER} relative px-1.5 text-center font-semibold ${
                                                    isDirty ? 'shadow-[inset_3px_0_0_#f59e0b]' : ''
                                                }`}
                                                title={[day.dow, day.schedule].filter(Boolean).join(' · ')}
                                            >
                                                {day.day}
                                                <span className={`ml-1 text-[9px] font-normal ${day.is_sunday ? 'text-red-700' : 'text-[#7a6a40]'}`}>
                                                    {day.dow.slice(0, 2)}
                                                </span>
                                            </td>

                                            {worked ? (
                                                TIME_FIELDS.map((field, f) => {
                                                    const bad = normalizeTime(row[field]) === null && !statusWord(row[field]);
                                                    const flagged = bad || (error && field === 'time_out');

                                                    return (
                                                        <td key={field} className={`${INK_BORDER} ${highlight} p-0`}>
                                                            <input
                                                                data-cell={`${i}-${f + 1}`}
                                                                aria-label={`${FIELD_LABELS[field]} on day ${day.day}`}
                                                                className={`${timeInput} ${flagged ? 'bg-red-100 text-red-700' : ''}`}
                                                                value={row[field]}
                                                                title={flagged ? (error ?? 'Not a time') : undefined}
                                                                onFocus={selectAll}
                                                                onChange={(event) => update(i, { [field]: event.target.value })}
                                                                onBlur={() => commit(i)}
                                                                onKeyDown={(event) => onKeyDown(event, i, f + 1)}
                                                            />
                                                        </td>
                                                    );
                                                })
                                            ) : (
                                                <td
                                                    className={`${INK_BORDER} ${PAPER} text-center tracking-wide ${
                                                        row.day_status === 'day_off'
                                                            ? 'font-bold text-[#1e8c3a]'
                                                            : row.day_status === 'absent'
                                                              ? 'font-semibold text-red-700'
                                                              : 'font-semibold text-blue-800'
                                                    }`}
                                                    colSpan={4}
                                                >
                                                    {STATUS_LABELS[row.day_status].toUpperCase()}
                                                </td>
                                            )}

                                            {result?.is_incomplete ? (
                                                <td className={`${INK_BORDER} ${PAPER} text-center text-[11px] font-medium text-amber-800`} colSpan={2}>
                                                    missing time
                                                </td>
                                            ) : (
                                                <>
                                                    <td className={`${INK_BORDER} ${highlight} px-1.5`}>
                                                        {formatHours(result?.ot_minutes ?? 0)}
                                                    </td>
                                                    <td className={`${INK_BORDER} ${highlight} px-1.5`}>{formatHours(lates)}</td>
                                                </>
                                            )}

                                            <td className="dtr-no-print p-0 pl-1">
                                                <select
                                                    aria-label={`Mark day ${day.day}`}
                                                    className={`h-[22px] w-full rounded-sm border border-transparent bg-transparent text-[12px] outline-none hover:border-slate-300 focus:border-[#1a73e8] focus:bg-white ${
                                                        worked ? 'text-slate-400' : 'font-medium text-slate-800'
                                                    }`}
                                                    value={row.day_status}
                                                    onChange={(event) =>
                                                        update(i, { day_status: event.target.value as DayStatus })
                                                    }
                                                >
                                                    {Object.entries(STATUS_LABELS).map(([value, label]) => (
                                                        <option key={value} value={value}>
                                                            {label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>

                            <tfoot>
                                <tr>
                                    <td />
                                    <td className={`${cell} font-bold`}>Total</td>
                                    <td className={cell} colSpan={4} />
                                    <td className={`${cell} font-semibold`}>{formatHours(totals.ot)}</td>
                                    <td className={`${cell} font-semibold`}>{formatHours(totals.lates)}</td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} h-4 border-b-0`} colSpan={7} />
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-y-0 px-3 text-[11px] leading-5 italic`} colSpan={7}>
                                        I certify on my honor that the above is a true and correct report of the hours of
                                        work performed, record of which was made daily at the time of arrival and
                                        departure from office.
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-y-0 pt-3 text-center font-bold`} colSpan={7}>
                                        {employee.last_name}, {employee.first_name}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-y-0 px-3 text-[11px] italic`} colSpan={7}>
                                        VERIFIED as to the prescribed office hours:
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-y-0 pt-4 text-center font-bold`} colSpan={7}>
                                        {in_charge ?? ''}
                                    </td>
                                    <td />
                                </tr>
                                <tr>
                                    <td />
                                    <td className={`${cell} border-t-0 text-center italic`} colSpan={7}>
                                        In Charge
                                    </td>
                                    <td />
                                </tr>
                            </tfoot>
                        </table>
                    )}

                    {employee && (
                        <p className="dtr-no-print mx-auto mt-4 max-w-3xl text-center text-xs text-slate-500">
                            Type times like <code>645</code>, <code>6:45</code> or <code>6:45pm</code>. Type{' '}
                            <code>off</code>, <code>absent</code> or <code>leave</code> in a time cell to mark the day.
                            Enter and the arrow keys move between cells.
                        </p>
                    )}

                    {employee && (
                        <section className="dtr-no-print mx-auto mt-8 max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900">
                                <History className="size-4 text-slate-400" />
                                Change Log · {monthLabel(month, 'long')}
                            </h2>
                            {history.length === 0 ? (
                                <p className="text-sm text-slate-500">No changes recorded for this month yet.</p>
                            ) : (
                                <ul className="divide-y divide-slate-100 text-sm">
                                    {history.map((entry) => (
                                        <li key={entry.id} className="flex flex-wrap items-baseline gap-x-3 gap-y-1 py-2">
                                            <span className="w-14 font-semibold text-slate-800">{entry.work_date}</span>
                                            <span
                                                className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                    entry.action === 'created'
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : entry.action === 'deleted'
                                                          ? 'bg-red-100 text-red-700'
                                                          : 'bg-blue-100 text-blue-700'
                                                }`}
                                            >
                                                {entry.action}
                                            </span>
                                            <span className="flex-1 text-slate-600">
                                                {Object.entries(entry.changes).map(([field, [before, after]], j) => (
                                                    <span key={field}>
                                                        {j > 0 && ' · '}
                                                        <span className="text-slate-400">{FIELD_LABELS[field] ?? field}</span>{' '}
                                                        {before ?? '—'} →{' '}
                                                        <span className="font-medium text-slate-800">{after ?? '—'}</span>
                                                    </span>
                                                ))}
                                            </span>
                                            <span className="text-xs text-slate-400">
                                                {entry.user} · {entry.at}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                    </>
                    )}
                </div>

                {/* Sheet tabs, like the workbook's: the schedule, then one per employee. */}
                <div className="dtr-no-print sticky bottom-0 flex items-stretch border-t border-slate-300 bg-[#f1f3f4] text-[13px]">
                    <span className="flex items-center px-3 text-slate-500">
                        <Menu className="size-4" />
                    </span>
                    <div className="flex overflow-x-auto">
                        {fullScreen && (
                            <SheetTab
                                label="MANNING"
                                active={sheet === 'manning'}
                                onClick={() => visit({ sheet: 'manning' })}
                            />
                        )}
                        {fullScreen && (
                            <SheetTab
                                label="SCHEDULE"
                                active={sheet === 'schedule'}
                                onClick={() => visit({ sheet: 'schedule' })}
                            />
                        )}
                        {employees.map((e) => (
                            <SheetTab
                                key={e.id}
                                label={`${e.last_name}, ${e.first_name}`}
                                active={sheet === 'dtr' && e.id === employee?.id}
                                onClick={() => visit({ employee_id: e.id, sheet: 'dtr' })}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

function SheetTab({ label, active, onClick }: { label: string; active: boolean; onClick: () => void }) {
    return (
        <button
            type="button"
            onClick={() => !active && onClick()}
            className={`shrink-0 px-4 py-2 font-medium whitespace-nowrap uppercase ${
                active ? 'bg-[#e1e9f7] text-[#0b57d0] shadow-[inset_0_-3px_0_#0b57d0]' : 'text-slate-600 hover:bg-slate-200'
            }`}
        >
            {label}
        </button>
    );
}

/**
 * The Manning tab: the site's people by role, with their start date and
 * regular schedule time. Schedule times and site membership save as they change.
 */
function ManningSheet({ manning, site, onOpen }: { manning: Manning; site: string; onOpen: (employeeId: number) => void }) {
    const shiftOptions = useOptionList('shift_schedule');
    const border = 'border border-[#1f1f1f]';

    const save = (employeeId: number, data: Record<string, string | null>) =>
        router.put(`/timesheet/manning/${employeeId}`, data, { preserveScroll: true });

    return (
        <div className="dtr-print mx-auto w-fit font-[Arial,sans-serif] text-[11px] text-[#111]">
            <div className="mb-1 flex items-end gap-16 pl-[96px]">
                <h2 className="text-[17px] font-bold">{manning.title}</h2>
                <span className="text-[11px] font-bold italic">Schedule Time</span>
            </div>
            <div className="mb-5 flex gap-6 pl-[96px] text-[10px]">
                <span className="w-48">Warehouse Operation Hours</span>
                <span>{manning.operation_hours ?? '—'}</span>
            </div>

            {manning.groups.map((group) => {

                return (
                    <div key={group.title} className="mb-6">
                        <div className="mb-0.5 pl-[96px] text-[11px] font-bold italic">{group.title}</div>
                        <table className="border-collapse bg-white">
                            <colgroup>
                                <col className="w-24" />
                                <col className="w-12" />
                                <col className="w-56" />
                                <col className="w-60" />
                                <col className="w-44" />
                                <col className="w-6" />
                            </colgroup>
                            <tbody>
                                {group.rows.length === 0 && (
                                    <tr>
                                        <td />
                                        <td className={`${border} h-5`} />
                                        <td className={`${border} bg-[#dbe5f1]`} />
                                        <td className={border} />
                                        <td className={border} />
                                        <td />
                                    </tr>
                                )}
                                {group.rows.map((row, index) => {

                                    return (
                                        <tr key={row.employee_id} className="group/row">
                                            <td className="pr-2 text-left whitespace-nowrap">{row.start_date ?? ''}</td>
                                            <td className={`${border} text-center font-bold`}>{index + 1}</td>
                                            <td className={`${border} bg-[#dbe5f1] px-1.5 text-center font-bold`}>
                                                <button type="button" className="hover:underline" onClick={() => onOpen(row.employee_id)}>
                                                    {row.name}
                                                </button>
                                            </td>
                                            <td className={`${border} p-0 text-center font-bold`}>
                                                <select
                                                    aria-label={`Schedule time for ${row.name}`}
                                                    className="h-5 w-full cursor-pointer appearance-none bg-transparent text-center font-bold outline-none hover:bg-[#e8f0fe] focus:bg-[#e8f0fe]"
                                                    value={row.shift_schedule ?? ''}
                                                    onChange={(event) =>
                                                        save(row.employee_id, { shift_schedule: event.target.value || null })
                                                    }
                                                >
                                                    <option value="">{row.shift_schedule ? '' : (row.schedule ?? '')}</option>
                                                    {shiftOptions.map((option) => (
                                                        <option key={option.value} value={option.value}>
                                                            {option.label}
                                                        </option>
                                                    ))}
                                                </select>
                                            </td>
                                            <td className={`${border} text-center font-bold italic`}>{row.position ?? ''}</td>
                                            <td className="dtr-no-print text-center">
                                                {site !== 'all' && (
                                                    <button
                                                        type="button"
                                                        title={`Remove ${row.name} from this site`}
                                                        className="invisible text-slate-400 group-hover/row:visible hover:text-red-600"
                                                        onClick={() =>
                                                            window.confirm(`Remove ${row.name} from this site?`) &&
                                                            save(row.employee_id, { work_location: null })
                                                        }
                                                    >
                                                        ×
                                                    </button>
                                                )}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                );
            })}

            {site !== 'all' && manning.unassigned.length > 0 && (
                <div className="dtr-no-print mt-2 flex items-center gap-2 pl-[96px] font-sans text-xs text-slate-600">
                    <span>Add to this site:</span>
                    <div className="w-72">
                        <EmployeeCombobox
                            employees={manning.unassigned}
                            value=""
                            placeholder="Search unassigned employees…"
                            onChange={(id) => save(Number(id), { work_location: site })}
                        />
                    </div>
                </div>
            )}
        </div>
    );
}

// Weekday column colours from the SHEDULE tab.
const WEEKDAY_TONES = ['#f4cccc', '#d9d2e9', '#9fc5e8', '#d9ead3', '#ffe599', '#f9cb9c', '#ea9999'];

/** The SHEDULE tab: a block per week, a row per person, a name in each day they work. */
function ScheduleSheet({ weeks, onOpen }: { weeks: ScheduleWeek[]; onOpen: (employeeId: number) => void }) {
    const border = 'border border-[#9e9e9e]';

    if (weeks.every((week) => week.rows.length === 0)) {
        return (
            <div className="mx-auto max-w-md bg-white p-8 text-center text-sm text-slate-500">
                No schedules for this month yet. Assign shifts on the Schedules page.
            </div>
        );
    }

    return (
        <div className="dtr-print flex flex-col gap-6 font-[Calibri,Carlito,Arial,sans-serif] text-[12px]">
            {weeks.map((week) => (
                <table key={week.days[0].date} className="border-collapse bg-white">
                    <thead>
                        <tr>
                            <th className={`${border} w-36 bg-[#dbe8f5] text-[15px]`} rowSpan={2}>
                                Time
                            </th>
                            {week.days.map((day, i) => (
                                <th
                                    key={day.date}
                                    className={`${border} w-36 text-[15px] font-bold`}
                                    style={{ backgroundColor: WEEKDAY_TONES[i] }}
                                >
                                    {day.dow}
                                </th>
                            ))}
                            <th className={`${border} w-36 bg-[#f4cccc] text-[15px]`} rowSpan={2}>
                                Position
                            </th>
                            <th className={`${border} min-w-64 bg-[#efefef] text-[15px]`} rowSpan={2}>
                                Remarks
                            </th>
                        </tr>
                        <tr>
                            {week.days.map((day) => (
                                <th
                                    key={day.date}
                                    className={`${border} bg-white px-1 text-[11px] font-semibold ${
                                        day.holiday ? 'text-red-600' : day.in_month ? 'text-slate-900' : 'text-slate-400'
                                    }`}
                                    title={day.holiday ?? undefined}
                                >
                                    {day.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {week.rows.map((row, r) => (
                            <tr key={`${row.employee_id}-${r}`}>
                                <td className={`${border} bg-[#dbe8f5] px-1.5 font-semibold whitespace-nowrap`}>
                                    {row.time ?? '—'}
                                </td>
                                {row.cells.map((cell, i) => (
                                    <td
                                        key={week.days[i].date}
                                        className={`${border} px-1.5 whitespace-nowrap`}
                                        style={{ backgroundColor: cell ? WEEKDAY_TONES[i] : undefined }}
                                    >
                                        {cell === 'work' && (
                                            <button
                                                type="button"
                                                className="hover:underline"
                                                onClick={() => row.employee_id && onOpen(row.employee_id)}
                                            >
                                                {row.name}
                                            </button>
                                        )}
                                        {cell === 'off' && (
                                            <span className="font-[cursive] text-[11px] text-[#7f1d1d]">Day OFF</span>
                                        )}
                                        {cell === 'holiday' && (
                                            <span className="font-[cursive] text-[13px] font-bold text-red-600">HOLIDAY</span>
                                        )}
                                        {cell === 'leave' && <span className="text-[11px] text-blue-800">On Leave</span>}
                                    </td>
                                ))}
                                <td className={`${border} bg-[#f4cccc] px-1.5 whitespace-nowrap uppercase`}>
                                    {row.position ?? ''}
                                </td>
                                <td className={`${border} px-1.5 text-[11px]`}>
                                    {row.remarks.map((remark) => (
                                        <span key={remark} className="mr-2 inline-block bg-[#ffff00] px-1">
                                            {remark}
                                        </span>
                                    ))}
                                </td>
                            </tr>
                        ))}
                        <tr>
                            <td className={border} />
                            {week.days.map((day) => (
                                <td
                                    key={day.date}
                                    className={`${border} px-1.5 text-[10px] ${day.holiday ? 'bg-[#4a86e8] text-white' : ''}`}
                                >
                                    {day.holiday ?? ''}
                                </td>
                            ))}
                            <td className={border} colSpan={2} />
                        </tr>
                    </tbody>
                </table>
            ))}
        </div>
    );
}
