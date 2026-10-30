import { Head, Link, router } from '@inertiajs/react';
import { History, Maximize2, Menu, Minimize2, Printer, RotateCcw, Save, Sheet } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import type { FocusEvent, KeyboardEvent } from 'react';
import { toast } from 'sonner';
import { EmployeeCombobox } from '@/components/employee-combobox';
import type { EmployeeOption } from '@/components/employee-combobox';
import { ScheduleSheet } from '@/components/schedule/schedule-sheet';
import type { ScheduleData } from '@/components/schedule/schedule-sheet';
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
    note_time_in: string;
    note_time_out: string;
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

export type TimesheetPageProps = {
    sites: { value: string; label: string }[];
    /** The selected site's value, or "all". */
    site: string;
    employees: SheetEmployee[];
    employee: SheetEmployee | null;
    month: string;
    /** One DTR form per month, oldest first. */
    sheets: { month: string; days: Day[] }[];
    /** How many months are shown. */
    span: number;
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
    schedule?: ScheduleData | null;
    manning?: Manning | null;
};

// The four DTR time columns, in paper order.
const TIME_FIELDS = ['time_in', 'break_out', 'break_in', 'time_out'] as const;
type TimeField = (typeof TIME_FIELDS)[number];

// Arrival/departure jotted outside the form, like the small box beside the
// paper DTR. Kept as notes; never paid until copied into the form.
const NOTE_FIELDS = ['note_time_in', 'note_time_out'] as const;
type NoteField = (typeof NOTE_FIELDS)[number];

type Row = Pick<Day, 'date' | TimeField | NoteField | 'day_status' | 'remarks'>;

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
    note_time_in: 'outside note arrival',
    note_time_out: 'outside note departure',
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

// Grid columns used for keyboard movement: remarks margin, the four times,
// then the two outside notes.
const NAV_COLUMNS = 7;

// Sheet palette taken from the workbook.
const PAPER = 'bg-[#f6e3ad]';
const INK_BORDER = 'border border-[#3b3220]';
// The small gold-headed box of times noted beside the form.
const NOTE_BORDER = 'border border-[#9e9e9e] bg-white';
const NOTE_HEAD = 'border border-[#9e9e9e] bg-[#f1c232] text-center text-[12px] font-bold';

const toRow = (day: Day): Row => ({
    date: day.date,
    time_in: day.time_in,
    break_out: day.break_out,
    break_in: day.break_in,
    time_out: day.time_out,
    note_time_in: day.note_time_in,
    note_time_out: day.note_time_out,
    day_status: day.day_status,
    remarks: day.remarks,
});

const sameRow = (a: Row, b: Row) =>
    [...TIME_FIELDS, ...NOTE_FIELDS].every((field) => a[field] === b[field]) &&
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

    for (const field of [...TIME_FIELDS, ...NOTE_FIELDS]) {
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

/** Cells are keyed by form (month), so arrow keys stay within one form. */
const cellSelector = (form: string, row: number, col: number) => `[data-cell="${form}:${row}-${col}"]`;

function focusCell(form: string, row: number, col: number, step: 1 | -1, rowCount: number) {
    // Skip rows that have no input in this column (a merged "Day Off" row).
    for (let r = row; r >= 0 && r < rowCount; r += step) {
        const cell = document.querySelector<HTMLElement>(cellSelector(form, r, col));

        if (cell) {
            cell.focus();

            return;
        }
    }
}

const monthLabel = (month: string, style: 'long' | 'short') =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('en-US', { month: style, year: 'numeric' });

const SPAN_OPTIONS = [
    { value: 1, label: '1 month' },
    { value: 3, label: '3 months' },
    { value: 6, label: '6 months' },
    { value: 12, label: '12 months' },
];

export function DtrWorkbook({
    sites,
    site,
    employees,
    employee,
    month,
    sheets,
    span,
    history,
    official_hours,
    in_charge,
    fullScreen = false,
    sheet = 'dtr',
    schedule = null,
    manning = null,
}: Props) {
    const siteLabel = sites.find((s) => s.value === site)?.label ?? 'All Sites';
    // Unsaved days per form (month), reported up by each form.
    const [dirtyByMonth, setDirtyByMonth] = useState<Record<string, number>>({});
    const dirty = Object.values(dirtyByMonth).reduce((sum, count) => sum + count, 0);

    const reportDirty = useCallback((formMonth: string, count: number) => {
        setDirtyByMonth((current) => (current[formMonth] === count ? current : { ...current, [formMonth]: count }));
    }, []);

    useEffect(() => {
        if (dirty === 0) {
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
    }, [dirty]);

    const employeeIndex = employee ? employees.findIndex((e) => e.id === employee.id) + 1 : 0;

    const visit = (params: {
        employee_id?: number | string;
        month?: string;
        sheet?: SheetName;
        site?: string;
        span?: number;
    }) => {
        router.get(
            fullScreen ? '/timesheet/workbook' : '/timesheet',
            {
                site,
                // Switching site drops the employee, who may not work there.
                ...(params.site ? {} : { employee_id: employee?.id }),
                month,
                span,
                ...(fullScreen ? { sheet } : {}),
                ...params,
            },
            { preserveScroll: true, preserveState: false },
        );
    };

    const rangeLabel =
        sheets.length > 1
            ? `${monthLabel(sheets[0].month, 'short')} – ${monthLabel(sheets[sheets.length - 1].month, 'short')}`
            : monthLabel(month, 'long');

    return (
        <>
            <Head title={fullScreen ? 'Time Sheet Workbook' : 'Time Sheet'} />
            {/* Print only the sheet, the way the paper copy is printed: one DTR per page. */}
            <style>{`@media print {
                body * { visibility: hidden; }
                .dtr-print, .dtr-print * { visibility: visible; }
                .dtr-print { position: absolute; inset: 0 auto auto 0; }
                .dtr-no-print { display: none !important; }
                .dtr-page { break-after: page; }
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
                                {sheet === 'dtr' ? rangeLabel : monthLabel(month, 'long')}
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
                        aria-label={sheet === 'dtr' && span > 1 ? 'Up to month' : 'Month'}
                        title={sheet === 'dtr' && span > 1 ? 'The last month shown' : undefined}
                        className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm"
                        value={month}
                        onChange={(event) => event.target.value && visit({ month: event.target.value })}
                    />
                    {sheet === 'dtr' && (
                        <select
                            aria-label="Months shown"
                            className="rounded-full border border-slate-200 bg-white px-3 py-1.5 text-sm"
                            value={span}
                            onChange={(event) => visit({ span: Number(event.target.value) })}
                        >
                            {SPAN_OPTIONS.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    )}
                    <div className="w-64 text-sm">
                        <EmployeeCombobox
                            employees={employees}
                            value={sheet === 'dtr' ? (employee?.id?.toString() ?? '') : ''}
                            onChange={(id) => visit({ employee_id: id, sheet: 'dtr' })}
                        />
                    </div>

                    <div className="ml-auto flex items-center gap-2">
                        {dirty > 0 && (
                            <span className="text-sm text-amber-700">
                                {dirty} unsaved {dirty === 1 ? 'day' : 'days'}
                            </span>
                        )}
                        <Link
                            href={fullScreen ? { manning: '/manning', schedule: '/schedules', dtr: '/timesheet' }[sheet] : '/timesheet/workbook'}
                            data={{ site, employee_id: employee?.id, month, ...(fullScreen ? {} : { sheet: 'dtr', span }) }}
                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200/60"
                        >
                            {fullScreen ? <Minimize2 className="size-4" /> : <Maximize2 className="size-4" />}
                            {fullScreen ? 'Exit full screen' : 'Full screen'}
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-200/60"
                        >
                            <Printer className="size-4" />
                            Print
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
                    ) : sheet === 'schedule' && schedule ? (
                        <ScheduleSheet data={schedule} onOpenEmployee={(id) => visit({ employee_id: id, sheet: 'dtr' })} />
                    ) : !employee ? (
                        <div className="mx-auto max-w-md bg-white p-8 text-center text-sm text-slate-500">
                            Add an active employee first to start encoding time sheets.
                        </div>
                    ) : (
                        <>
                            {/* One DTR per month, laid out two across like the employee's tab. */}
                            <div className="dtr-print flex flex-wrap items-start justify-center gap-x-10 gap-y-12">
                                {sheets.map((form) => (
                                    <DtrForm
                                        key={form.month}
                                        employee={employee}
                                        employeeIndex={employeeIndex}
                                        month={form.month}
                                        days={form.days}
                                        officialHours={official_hours}
                                        inCharge={in_charge}
                                        onDirtyChange={reportDirty}
                                    />
                                ))}
                            </div>

                            <p className="dtr-no-print mx-auto mt-6 max-w-3xl text-center text-xs text-slate-500">
                                Type times like <code>645</code>, <code>6:45</code> or <code>6:45pm</code>. Type{' '}
                                <code>off</code>, <code>absent</code> or <code>leave</code> in a time cell to mark the
                                day. Enter and the arrow keys move between cells. Each month saves on its own.
                            </p>

                            <section className="dtr-no-print mx-auto mt-8 max-w-3xl rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
                                <h2 className="mb-3 flex items-center gap-2 text-base font-semibold text-slate-900">
                                    <History className="size-4 text-slate-400" />
                                    Change Log · {rangeLabel}
                                </h2>
                                {history.length === 0 ? (
                                    <p className="text-sm text-slate-500">No changes recorded for these months yet.</p>
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
                        </>
                    )}
                </div>

                {/* Sheet tabs, like the workbook's: Manning, the schedule, then one per employee. */}
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

type DtrFormProps = {
    employee: SheetEmployee;
    employeeIndex: number;
    month: string;
    days: Day[];
    officialHours: string | null;
    inCharge: string | null;
    onDirtyChange: (month: string, count: number) => void;
};

/** Unsaved days kept on this device, so a refresh, crash or brownout loses nothing. */
type Draft = { savedAt: string; rows: Record<string, Row> };

const draftKey = (employeeId: number, month: string) => `dtr-draft:${employeeId}:${month}`;

function readDraft(key: string): Draft | null {
    try {
        const raw = window.localStorage.getItem(key);

        return raw ? (JSON.parse(raw) as Draft) : null;
    } catch {
        return null;
    }
}

function writeDraft(key: string, draft: Draft | null): void {
    try {
        if (draft) {
            window.localStorage.setItem(key, JSON.stringify(draft));
        } else {
            window.localStorage.removeItem(key);
        }
    } catch {
        // Storage unavailable (private window, full): the draft just isn't kept.
    }
}

/**
 * One month's DAILY TIME RECORD, encoded like the paper form. It keeps its
 * own unsaved edits and saves on its own, so saving one month never throws
 * away what's being typed into another. Unsaved edits are also kept as a
 * draft on this device and restored when the page is opened again.
 */
function DtrForm({ employee, employeeIndex, month, days, officialHours, inCharge, onDirtyChange }: DtrFormProps) {
    const key = draftKey(employee.id, month);
    const [rows, setRows] = useState<Row[]>(() => {
        const draft = readDraft(key);

        return days.map((day) => draft?.rows[day.date] ?? toRow(day));
    });
    // When unsaved days were brought back from a draft, and when that draft was last kept.
    const [restoredAt] = useState(() => readDraft(key)?.savedAt ?? null);
    const [showRestored, setShowRestored] = useState(restoredAt !== null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    // Fresh props arrive after any form saves. Only reset this form when its
    // own month actually changed, so other months keep their unsaved edits.
    const signature = useMemo(() => JSON.stringify(days), [days]);
    const [loadedSignature, setLoadedSignature] = useState(signature);

    if (loadedSignature !== signature) {
        setLoadedSignature(signature);
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
        onDirtyChange(month, dirty.length);
    }, [month, dirty.length, onDirtyChange]);

    useEffect(() => () => onDirtyChange(month, 0), [month, onDirtyChange]);

    // Keep every unsaved day as a draft on this device as it's typed; a
    // save or discard leaves nothing unsaved, which clears the draft.
    useEffect(() => {
        const changed = rows.filter((row, i) => days[i] && !sameRow(interpret(row), toRow(days[i])));

        writeDraft(
            key,
            changed.length > 0
                ? { savedAt: new Date().toISOString(), rows: Object.fromEntries(changed.map((row) => [row.date, row])) }
                : null,
        );
    }, [rows, days, key]);

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
            focusCell(month, row + 1, col, 1, rows.length);
        } else if (event.key === 'ArrowUp' && isInput) {
            event.preventDefault();
            focusCell(month, row - 1, col, -1, rows.length);
        } else if (event.key === 'ArrowRight' && (atEnd || allSelected) && col < NAV_COLUMNS - 1) {
            event.preventDefault();
            document.querySelector<HTMLElement>(cellSelector(month, row, col + 1))?.focus();
        } else if (event.key === 'ArrowLeft' && (atStart || allSelected) && col > 0) {
            event.preventDefault();
            document.querySelector<HTMLElement>(cellSelector(month, row, col - 1))?.focus();
        }
    };

    // Typing into a cell replaces it, like a spreadsheet.
    const selectAll = (event: FocusEvent<HTMLInputElement>) => event.target.select();

    const save = () => {
        if (dirty.length === 0) {
            return;
        }

        if (dirty.some((row) => [...TIME_FIELDS, ...NOTE_FIELDS].some((field) => normalizeTime(row[field]) === null))) {
            toast.error(`Fix the highlighted times in ${monthLabel(month, 'long')} before saving.`);

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
                note_time_in: toPayloadTime(row.note_time_in),
                note_time_out: toPayloadTime(row.note_time_out),
                day_status: row.day_status,
                remarks: row.remarks.trim() || null,
            };
        });

        router.put(
            '/timesheet',
            { employee_id: employee.id, rows: payload },
            {
                preserveScroll: true,
                // Keep the other months' forms (and their unsaved edits) mounted.
                preserveState: true,
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
        <div className="dtr-page">
            {/* This month's own save bar. */}
            <div className="dtr-no-print mb-1.5 flex items-center justify-end gap-2 pl-40 text-sm">
                <span className="mr-auto font-medium text-slate-700">{monthLabel(month, 'long')}</span>
                {dirty.length > 0 && (
                    <span className="text-amber-700">
                        {dirty.length} unsaved {dirty.length === 1 ? 'day' : 'days'}
                    </span>
                )}
                <button
                    type="button"
                    disabled={dirty.length === 0 || saving}
                    onClick={() => {
                        if (window.confirm(`Discard ${dirty.length} unsaved ${dirty.length === 1 ? 'day' : 'days'} in ${monthLabel(month, 'long')}?`)) {
                            setRows(days.map(toRow));
                            setErrors({});
                            setShowRestored(false);
                        }
                    }}
                    className="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-slate-700 hover:bg-slate-200/60 disabled:opacity-40"
                >
                    <RotateCcw className="size-3.5" />
                    Discard
                </button>
                <button
                    type="button"
                    disabled={dirty.length === 0 || saving}
                    onClick={save}
                    className="inline-flex items-center gap-1 rounded-full bg-[#c2e7ff] px-3 py-1 font-medium text-[#001d35] hover:bg-[#b3dcf7] disabled:opacity-40"
                >
                    <Save className="size-3.5" />
                    {saving ? 'Saving…' : 'Save'}
                </button>
            </div>

            {showRestored && restoredAt && dirty.length > 0 && (
                <div className="dtr-no-print mb-2 ml-40 flex items-center gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    <span className="flex-1">
                        Restored {dirty.length} unsaved {dirty.length === 1 ? 'day' : 'days'} from your draft (last typed{' '}
                        {new Date(restoredAt).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' })}
                        ). Check them, then click <b>Save</b>.
                    </span>
                    <button type="button" className="text-amber-800 hover:underline" onClick={() => setShowRestored(false)}>
                        OK
                    </button>
                </div>
            )}

            <table className="border-collapse font-[Calibri,Carlito,Arial,sans-serif] text-[13px] text-[#1f1a10]">
                <colgroup>
                    <col className="w-40" />
                    <col className="w-14" />
                    <col className="w-[88px]" />
                    <col className="w-[88px]" />
                    <col className="w-[88px]" />
                    <col className="w-[88px]" />
                    <col className="w-[96px]" />
                    <col className="w-[96px]" />
                    {/* Outside notes: a gap, arrival, departure, and the copy-in button. */}
                    <col className="w-4" />
                    <col className="w-[76px]" />
                    <col className="w-[76px]" />
                    <col className="w-7" />
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
                            Regular days: {officialHours ?? '—'}
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
                        <td />
                        <td className={NOTE_HEAD} title="Times noted outside the form — not paid until copied in">
                            AM
                        </td>
                        <td className={NOTE_HEAD} title="Times noted outside the form — not paid until copied in">
                            PM
                        </td>
                        <td />
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
                        <td className={`${NOTE_BORDER} bg-white text-center font-bold`}>Arrival</td>
                        <td className={`${NOTE_BORDER} bg-white text-center font-bold`}>Departure</td>
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
                                        data-cell={`${month}:${i}-0`}
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
                                                    data-cell={`${month}:${i}-${f + 1}`}
                                                    aria-label={`${FIELD_LABELS[field]} on ${monthLabel(month, 'short')} ${day.day}`}
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

                                {/* Outside the form: times noted beside it, kept as notes only. */}
                                <td />
                                {NOTE_FIELDS.map((field, n) => {
                                    const bad = normalizeTime(row[field]) === null;

                                    return (
                                        <td key={field} className={`${NOTE_BORDER} p-0`}>
                                            <input
                                                data-cell={`${month}:${i}-${n + 5}`}
                                                aria-label={`${FIELD_LABELS[field]} on ${monthLabel(month, 'short')} ${day.day}`}
                                                className={`${timeInput} ${bad ? 'bg-red-100 text-red-700' : ''}`}
                                                value={row[field]}
                                                title={bad ? 'Not a time' : undefined}
                                                onFocus={selectAll}
                                                onChange={(event) => update(i, { [field]: event.target.value })}
                                                onBlur={() => commit(i)}
                                                onKeyDown={(event) => onKeyDown(event, i, n + 5)}
                                            />
                                        </td>
                                    );
                                })}
                                <td className="dtr-no-print p-0 text-center">
                                    {(row.note_time_in || row.note_time_out) && (
                                        <button
                                            type="button"
                                            title="Copy these times into the form (arrival → AM Arrival, departure → PM Departure)"
                                            className="rounded px-1 text-[13px] font-bold text-[#b45309] hover:bg-amber-100"
                                            onClick={() =>
                                                update(i, {
                                                    day_status: 'worked',
                                                    time_in: row.note_time_in || row.time_in,
                                                    time_out: row.note_time_out || row.time_out,
                                                })
                                            }
                                        >
                                            ←
                                        </button>
                                    )}
                                </td>

                                <td className="dtr-no-print p-0 pl-1">
                                    <select
                                        aria-label={`Mark ${monthLabel(month, 'short')} ${day.day}`}
                                        className={`h-[22px] w-full rounded-sm border border-transparent bg-transparent text-[12px] outline-none hover:border-slate-300 focus:border-[#1a73e8] focus:bg-white ${
                                            worked ? 'text-slate-400' : 'font-medium text-slate-800'
                                        }`}
                                        value={row.day_status}
                                        onChange={(event) => update(i, { day_status: event.target.value as DayStatus })}
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
                            I certify on my honor that the above is a true and correct report of the hours of work
                            performed, record of which was made daily at the time of arrival and departure from office.
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
                            {inCharge ?? ''}
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
        </div>
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
export function ManningSheet({ manning, site, onOpen }: { manning: Manning; site: string; onOpen: (employeeId: number) => void }) {
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
