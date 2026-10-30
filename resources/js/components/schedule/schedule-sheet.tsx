import { router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { MouseEvent, ReactNode } from 'react';
import { toast } from 'sonner';

export type ScheduleCellValue = string; // "s:{shift_type_id}" | "off" | a day status | "" (Manning shift)

export type ScheduleData = {
    weeks: {
        days: { date: string; label: string; dow: string; in_month: boolean; holiday: string | null }[];
        rows: {
            employee_id: number;
            name: string;
            position: string | null;
            time: string | null;
            regular_shift_type_id: number | null;
            active_from: string | null;
            active_until: string | null;
            cells: ScheduleCellValue[];
            remarks: string[];
        }[];
    }[];
    shift_types: { id: number; label: string; is_rest_day: boolean }[];
};

type Row = ScheduleData['weeks'][number]['rows'][number];
type Day = ScheduleData['weeks'][number]['days'][number];
type Target = { row: Row; date: string };

type Props = {
    data: ScheduleData;
    onOpenEmployee?: (employeeId: number) => void;
};

// Day column colours from the client's SHEDULE tab.
const WEEKDAY_TONES: Record<string, string> = {
    MON: '#ead1dc',
    TUE: '#d9d2e9',
    WED: '#9fc5e8',
    THU: '#d9ead3',
    FRI: '#ffe599',
    SAT: '#f9cb9c',
    SUN: '#ea9999',
};

// How each non-working day reads on the sheet and in the menu (mirrors Schedule::STATUS_LABELS).
const STATUS_TEXT: Record<string, { text: string; className: string }> = {
    off: { text: 'DAY OFF', className: 'font-[cursive] text-[11px] font-bold text-[#7f1d1d]' },
    absent: { text: 'ABSENT', className: 'rounded bg-red-600 px-1.5 text-[10px] font-bold text-white' },
    sick_leave: { text: 'Sick Leave', className: 'rounded bg-orange-100 px-1.5 text-[10px] font-semibold text-orange-800' },
    vacation_leave: { text: 'Vacation Leave', className: 'rounded bg-sky-100 px-1.5 text-[10px] font-semibold text-sky-800' },
    emergency_leave: { text: 'Emergency Leave', className: 'rounded bg-purple-100 px-1.5 text-[10px] font-semibold text-purple-800' },
    leave: { text: 'On Leave', className: 'rounded bg-blue-100 px-1.5 text-[10px] font-semibold text-blue-800' },
    business_trip: { text: 'Business Trip', className: 'rounded bg-emerald-100 px-1.5 text-[10px] font-semibold text-emerald-800' },
};

const MENU_STATUSES = ['off', 'absent', 'sick_leave', 'vacation_leave', 'emergency_leave', 'leave', 'business_trip'];

const key = (employeeId: number, date: string) => `${employeeId}|${date}`;

/** Whether the person is employed on this date (before their start, nothing is shown). */
const isActive = (row: Row, date: string) =>
    (!row.active_from || date >= row.active_from) && (!row.active_until || date <= row.active_until);

/**
 * A site's monthly schedule as weekly blocks, filled from the Manning tab:
 * everyone works their Manning schedule time each day unless a day is marked
 * otherwise. Clicking a day opens a menu; the choice saves straight away.
 */
export function ScheduleSheet({ data, onOpenEmployee }: Props) {
    const [menu, setMenu] = useState<{ x: number; y: number; title: string; targets: Target[] } | null>(null);
    // Choices sent but not yet reflected in fresh data, so the sheet updates at once.
    const [pending, setPending] = useState<Record<string, ScheduleCellValue>>({});
    const [loaded, setLoaded] = useState(data);
    const menuRef = useRef<HTMLDivElement>(null);

    if (loaded !== data) {
        setLoaded(data);
        setPending({});
    }

    useEffect(() => {
        if (!menu) {
            return;
        }

        const close = (event: Event) => {
            if (event instanceof KeyboardEvent ? event.key === 'Escape' : !menuRef.current?.contains(event.target as Node)) {
                setMenu(null);
            }
        };
        // The menu is positioned for where the cell was; scrolling moves it away.
        const closeOnScroll = (event: Event) => {
            if (!menuRef.current?.contains(event.target as Node)) {
                setMenu(null);
            }
        };
        window.addEventListener('pointerdown', close);
        window.addEventListener('keydown', close);
        window.addEventListener('scroll', closeOnScroll, true);

        return () => {
            window.removeEventListener('pointerdown', close);
            window.removeEventListener('keydown', close);
            window.removeEventListener('scroll', closeOnScroll, true);
        };
    }, [menu]);

    const shiftLabels = useMemo(
        () => Object.fromEntries(data.shift_types.map((shift) => [`s:${shift.id}`, shift.label])),
        [data.shift_types],
    );
    const workingShifts = data.shift_types.filter((shift) => !shift.is_rest_day);

    /** The stored value, treating an explicit Manning shift the same as no entry. */
    const storedValue = (row: Row, date: string, saved: ScheduleCellValue) => {
        const k = key(row.employee_id, date);
        const value = k in pending ? pending[k] : saved;

        return value === `s:${row.regular_shift_type_id}` ? '' : value;
    };

    const openMenu = (event: MouseEvent<HTMLElement>, title: string, targets: Target[]) => {
        const rect = event.currentTarget.getBoundingClientRect();
        const width = 240;
        const height = 380;

        setMenu({
            x: Math.min(rect.left, window.innerWidth - width - 8),
            y: rect.bottom + height > window.innerHeight ? Math.max(8, rect.top - height) : rect.bottom + 2,
            title,
            targets,
        });
    };

    const apply = (targets: Target[], choice: ScheduleCellValue) => {
        setMenu(null);

        const cells = targets
            .filter(({ row, date }) => isActive(row, date))
            .map(({ row, date }) => ({
                employee_id: row.employee_id,
                work_date: date,
                // "Working" on someone's own Manning shift is stored as no entry.
                value: choice === `s:${row.regular_shift_type_id}` ? '' : choice,
            }));

        if (cells.length === 0) {
            return;
        }

        setPending((current) => ({
            ...current,
            ...Object.fromEntries(cells.map((cell) => [key(cell.employee_id, cell.work_date), cell.value])),
        }));

        router.post(
            '/schedules/bulk',
            { cells },
            {
                preserveScroll: true,
                preserveState: true,
                onError: () => {
                    setPending({});
                    toast.error('Could not save the schedule. Please try again.');
                },
            },
        );
    };

    const single = menu?.targets.length === 1 ? menu.targets[0] : null;
    const singleWeek = single ? data.weeks.find((w) => w.days.some((d) => d.date === single.date)) : null;
    const singleIndex = single && singleWeek ? singleWeek.days.findIndex((d) => d.date === single.date) : -1;
    const current =
        single && singleWeek ? storedValue(single.row, single.date, single.row.cells[singleIndex] ?? '') : null;

    const border = 'border border-[#9e9e9e]';

    const renderCell = (row: Row, day: Day, value: ScheduleCellValue) => {
        if (!isActive(row, day.date)) {
            return null;
        }

        if (value.startsWith('s:')) {
            return (
                <span>
                    {row.name}
                    <span className="ml-1 text-[10px] text-slate-600">({shiftLabels[value] ?? 'shift'})</span>
                </span>
            );
        }

        if (value) {
            return <span className={STATUS_TEXT[value]?.className}>{STATUS_TEXT[value]?.text ?? value}</span>;
        }

        if (day.holiday) {
            return <span className="font-[cursive] text-[13px] font-bold text-red-600">HOLIDAY</span>;
        }

        // No entry: working their Manning shift.
        return row.regular_shift_type_id ? <span>{row.name}</span> : null;
    };

    return (
        <div className="flex flex-col gap-4">
            <p className="dtr-no-print font-sans text-xs text-slate-500">
                Everyone works their <b>Manning</b> schedule time every day unless marked otherwise. <b>Click a day</b>{' '}
                to mark it Day OFF, Absent, Sick Leave and more — it saves right away. Click a <b>day header</b> to mark
                everyone that day, or a person's <b>time</b> to mark their whole week.
            </p>

            {data.weeks.every((week) => week.rows.length === 0) ? (
                <div className="mx-auto max-w-md bg-white p-8 text-center text-sm text-slate-500">
                    No one is assigned to this site yet. Add people on the Manning page.
                </div>
            ) : (
                <div className="dtr-print flex flex-col gap-6 font-[Calibri,Carlito,Arial,sans-serif] text-[12px] select-none">
                    {data.weeks.map((week) => (
                        <table key={week.days[0].date} className="w-max border-collapse bg-white">
                            <thead>
                                <tr>
                                    <th className={`${border} w-40 bg-[#dbe8f5] text-[16px] font-bold`} rowSpan={2}>
                                        Time
                                    </th>
                                    {week.days.map((day) => (
                                        <th
                                            key={day.date}
                                            className={`${border} w-40 cursor-pointer text-[16px] font-bold hover:brightness-95`}
                                            style={{ backgroundColor: WEEKDAY_TONES[day.dow] }}
                                            title="Mark everyone this day"
                                            onClick={(event) =>
                                                openMenu(
                                                    event,
                                                    `Everyone · ${day.label}`,
                                                    week.rows.map((row) => ({ row, date: day.date })),
                                                )
                                            }
                                        >
                                            {day.dow === 'TUE' ? 'TUES' : day.dow === 'THU' ? 'THURS' : day.dow}
                                        </th>
                                    ))}
                                    <th className={`${border} w-36 bg-[#f4cccc] text-[16px] font-bold`} rowSpan={2}>
                                        Position
                                    </th>
                                    <th className={`${border} min-w-64 bg-[#efefef] text-[16px] font-bold`} rowSpan={2}>
                                        Remarks
                                    </th>
                                </tr>
                                <tr>
                                    {week.days.map((day) => (
                                        <th
                                            key={day.date}
                                            className={`${border} cursor-pointer bg-white px-1 text-[11px] font-bold hover:bg-slate-50 ${
                                                day.holiday ? 'text-red-600' : day.in_month ? 'text-slate-900' : 'text-slate-400'
                                            }`}
                                            title={day.holiday ?? 'Mark everyone this day'}
                                            onClick={(event) =>
                                                openMenu(
                                                    event,
                                                    `Everyone · ${day.label}`,
                                                    week.rows.map((row) => ({ row, date: day.date })),
                                                )
                                            }
                                        >
                                            {day.label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {week.rows.map((row) => (
                                    <tr key={row.employee_id}>
                                        <td
                                            className={`${border} cursor-pointer bg-[#dbe8f5] px-1.5 font-bold whitespace-nowrap hover:brightness-95`}
                                            title={`Mark ${row.name}'s whole week`}
                                            onClick={(event) =>
                                                openMenu(
                                                    event,
                                                    `${row.name} · whole week`,
                                                    week.days.map((day) => ({ row, date: day.date })),
                                                )
                                            }
                                        >
                                            {row.time ?? <span className="font-normal text-slate-400 italic">no Manning time</span>}
                                        </td>
                                        {week.days.map((day, i) => {
                                            const value = storedValue(row, day.date, row.cells[i]);
                                            const active = isActive(row, day.date);

                                            return (
                                                <td
                                                    key={day.date}
                                                    className={`${border} h-5 px-1.5 text-center whitespace-nowrap ${
                                                        active
                                                            ? 'cursor-pointer hover:outline-2 hover:-outline-offset-2 hover:outline-[#1a73e8]'
                                                            : ''
                                                    }`}
                                                    style={{
                                                        backgroundColor: !active
                                                            ? '#ffffff'
                                                            : day.in_month
                                                              ? WEEKDAY_TONES[day.dow]
                                                              : '#f3f3f3',
                                                    }}
                                                    title={active ? undefined : 'Not employed yet on this day'}
                                                    onClick={(event) =>
                                                        active && openMenu(event, `${row.name} · ${day.label}`, [{ row, date: day.date }])
                                                    }
                                                    onDoubleClick={() => onOpenEmployee?.(row.employee_id)}
                                                >
                                                    {renderCell(row, day, value)}
                                                </td>
                                            );
                                        })}
                                        <td className={`${border} bg-[#f4cccc] px-1.5 whitespace-nowrap`}>
                                            {onOpenEmployee ? (
                                                <button
                                                    type="button"
                                                    className="hover:underline"
                                                    title={`Open ${row.name}'s time sheet`}
                                                    onClick={() => onOpenEmployee(row.employee_id)}
                                                >
                                                    {row.position ?? '—'}
                                                </button>
                                            ) : (
                                                (row.position ?? '')
                                            )}
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
            )}

            {menu && (
                <div
                    ref={menuRef}
                    role="menu"
                    className="fixed z-50 w-60 rounded-xl border border-slate-200 bg-white py-1.5 font-sans text-sm shadow-xl"
                    style={{ left: menu.x, top: menu.y }}
                >
                    <div className="truncate px-3 pt-1 pb-2 text-xs font-medium text-slate-500">{menu.title}</div>
                    <MenuItem selected={current === ''} onClick={() => apply(menu.targets, '')}>
                        <span className="font-medium text-slate-800">Working</span>
                        <span className="ml-1 text-xs text-slate-500">
                            {single ? (single.row.time ?? 'no Manning time') : '(Manning shift)'}
                        </span>
                    </MenuItem>
                    {MENU_STATUSES.map((status) => (
                        <MenuItem key={status} selected={current === status} onClick={() => apply(menu.targets, status)}>
                            <span className={STATUS_TEXT[status].className}>{STATUS_TEXT[status].text}</span>
                        </MenuItem>
                    ))}
                    {workingShifts.length > 0 && (
                        <details className="group border-t border-slate-100 mt-1">
                            <summary className="cursor-pointer list-none px-3 py-1.5 text-slate-700 hover:bg-slate-50">
                                Another shift…
                            </summary>
                            <div className="max-h-40 overflow-y-auto">
                                {workingShifts.map((shift) => (
                                    <MenuItem
                                        key={shift.id}
                                        selected={current === `s:${shift.id}`}
                                        onClick={() => apply(menu.targets, `s:${shift.id}`)}
                                    >
                                        <span className="pl-2 text-slate-700">{shift.label}</span>
                                    </MenuItem>
                                ))}
                            </div>
                        </details>
                    )}
                </div>
            )}
        </div>
    );
}

function MenuItem({ selected, onClick, children }: { selected: boolean; onClick: () => void; children: ReactNode }) {
    return (
        <button
            type="button"
            role="menuitem"
            onClick={onClick}
            className="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-slate-50"
        >
            <span className="w-4">{selected && <Check className="size-4 text-[#0b57d0]" />}</span>
            <span className="flex-1">{children}</span>
        </button>
    );
}
