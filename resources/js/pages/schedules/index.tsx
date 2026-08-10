import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, ChevronDown, ChevronUp, Pencil, Save, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';

type ShiftType = {
    id: number;
    code: number;
    name: string;
    time_in: string | null;
    time_out: string | null;
    is_rest_day: boolean;
};

type EmployeeRow = {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
    department: string | null;
};

type Day = {
    date: string;
    day: number;
    dow: string;
    is_sunday: boolean;
    is_holiday: boolean;
};

type CellValue = {
    status: 'working' | 'leave' | 'business_trip';
    shift_type_id: number | null;
};

type Props = {
    employees: EmployeeRow[];
    shiftTypes: ShiftType[];
    days: Day[];
    cells: Record<string, CellValue>;
    start_date: string;
    end_date: string;
};

const cellKey = (employeeId: number, date: string) => `${employeeId}-${date}`;

function optionValue(cell: CellValue | undefined): string {
    if (!cell) {
return '';
}

    if (cell.status === 'working') {
return cell.shift_type_id ? String(cell.shift_type_id) : '';
}

    return cell.status;
}

const blankShiftTypeForm = {
    code: '',
    name: '',
    time_in: '',
    time_out: '',
    is_rest_day: false as boolean,
};

export default function SchedulesIndex({ employees, shiftTypes, days, cells, start_date, end_date }: Props) {
    const [range, setRange] = useState({ start_date, end_date });
    const [edits, setEdits] = useState<Record<string, CellValue | null>>({});
    const [showShiftTypes, setShowShiftTypes] = useState(false);
    const [editingShiftType, setEditingShiftType] = useState<ShiftType | null>(null);
    const shiftTypeForm = useForm({ ...blankShiftTypeForm });

    const currentValue = (employeeId: number, date: string): CellValue | undefined => {
        const key = cellKey(employeeId, date);

        if (key in edits) {
            return edits[key] ?? undefined;
        }

        return cells[key];
    };

    const setCell = (employeeId: number, date: string, raw: string) => {
        const key = cellKey(employeeId, date);
        const original = cells[key];

        const next: CellValue | null =
            raw === ''
                ? null
                : raw === 'leave' || raw === 'business_trip'
                  ? { status: raw, shift_type_id: null }
                  : { status: 'working', shift_type_id: Number(raw) };

        const sameAsOriginal = original
            ? next !== null && next.status === original.status && next.shift_type_id === original.shift_type_id
            : next === null;

        setEdits((prev) => {
            const copy = { ...prev };

            if (sameAsOriginal) {
                delete copy[key];
            } else {
                copy[key] = next;
            }

            return copy;
        });
    };

    const dirtyCount = Object.keys(edits).length;

    const applyRange = () => {
        router.get('/schedules', range, { preserveState: true });
    };

    const saveSchedule = () => {
        const payload = Object.entries(edits).map(([key, value]) => {
            const [employeeId, ...dateParts] = key.split('-');

            return {
                employee_id: Number(employeeId),
                work_date: dateParts.join('-'),
                status: value?.status ?? '',
                shift_type_id: value?.shift_type_id ?? null,
            };
        });

        router.post(
            '/schedules/bulk',
            { cells: payload },
            {
                preserveScroll: true,
                onSuccess: () => setEdits({}),
                onError: () => toast.error('Could not save the schedule. Please try again.'),
            }
        );
    };

    const cancelShiftTypeEdit = () => {
        shiftTypeForm.setData({ ...blankShiftTypeForm });
        shiftTypeForm.clearErrors();
        setEditingShiftType(null);
    };

    const startShiftTypeEdit = (shiftType: ShiftType) => {
        shiftTypeForm.setData({
            code: String(shiftType.code),
            name: shiftType.name,
            time_in: shiftType.time_in ?? '',
            time_out: shiftType.time_out ?? '',
            is_rest_day: shiftType.is_rest_day,
        });
        shiftTypeForm.clearErrors();
        setEditingShiftType(shiftType);
    };

    const submitShiftType = (event: React.FormEvent) => {
        event.preventDefault();

        if (editingShiftType) {
            shiftTypeForm.put(`/shift-types/${editingShiftType.id}`, { onSuccess: () => cancelShiftTypeEdit() });
        } else {
            shiftTypeForm.post('/shift-types', { onSuccess: () => shiftTypeForm.setData({ ...blankShiftTypeForm }) });
        }
    };

    return (
        <>
            <Head title="Schedules" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Employee Schedules</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Assign a shift, leave, or business trip to each employee for each date. Rest-day
                        shifts, leave, and business-trip days are excluded from payroll absence deductions.
                    </p>
                </div>

                <section className="flex flex-wrap items-end gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <label className="text-sm">
                        <span className="mb-1.5 block text-slate-600">Start Date</span>
                        <input
                            type="date"
                            className="rounded-xl border border-slate-200 px-3 py-2.5"
                            value={range.start_date}
                            onChange={(e) => setRange((r) => ({ ...r, start_date: e.target.value }))}
                        />
                    </label>
                    <label className="text-sm">
                        <span className="mb-1.5 block text-slate-600">End Date</span>
                        <input
                            type="date"
                            className="rounded-xl border border-slate-200 px-3 py-2.5"
                            value={range.end_date}
                            onChange={(e) => setRange((r) => ({ ...r, end_date: e.target.value }))}
                        />
                    </label>
                    <button
                        type="button"
                        onClick={applyRange}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    >
                        Apply Range
                    </button>
                    <div className="ml-auto">
                        <button
                            type="button"
                            onClick={saveSchedule}
                            disabled={dirtyCount === 0}
                            className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50"
                        >
                            <Save className="size-4" />
                            {dirtyCount === 0 ? 'Save Schedule' : `Save Schedule (${dirtyCount})`}
                        </button>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <button
                        type="button"
                        onClick={() => setShowShiftTypes((v) => !v)}
                        className="flex w-full items-center justify-between px-5 py-4 text-left"
                    >
                        <div className="flex items-center gap-2">
                            <div className="rounded-xl bg-teal-100 p-2 text-teal-600">
                                <CalendarDays className="size-4" />
                            </div>
                            <h2 className="text-lg font-semibold text-slate-900">Manage Shift Codes</h2>
                        </div>
                        {showShiftTypes ? (
                            <ChevronUp className="size-4 text-slate-400" />
                        ) : (
                            <ChevronDown className="size-4 text-slate-400" />
                        )}
                    </button>

                    {showShiftTypes && (
                        <div className="border-t border-slate-100 p-5">
                            <form className="grid gap-4 md:grid-cols-2" onSubmit={submitShiftType}>
                                <label className="text-sm">
                                    <span className="mb-1.5 block text-slate-600">Code</span>
                                    <input
                                        type="number"
                                        min={1}
                                        max={99}
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={shiftTypeForm.data.code}
                                        onChange={(e) => shiftTypeForm.setData('code', e.target.value)}
                                        required
                                    />
                                    {shiftTypeForm.errors.code && (
                                        <span className="mt-1 block text-xs text-rose-600">
                                            {shiftTypeForm.errors.code}
                                        </span>
                                    )}
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1.5 block text-slate-600">Name</span>
                                    <input
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={shiftTypeForm.data.name}
                                        onChange={(e) => shiftTypeForm.setData('name', e.target.value)}
                                        placeholder="Morning Shift"
                                        required
                                    />
                                    {shiftTypeForm.errors.name && (
                                        <span className="mt-1 block text-xs text-rose-600">
                                            {shiftTypeForm.errors.name}
                                        </span>
                                    )}
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1.5 block text-slate-600">Time In</span>
                                    <input
                                        type="time"
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={shiftTypeForm.data.time_in}
                                        onChange={(e) => shiftTypeForm.setData('time_in', e.target.value)}
                                    />
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1.5 block text-slate-600">Time Out</span>
                                    <input
                                        type="time"
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={shiftTypeForm.data.time_out}
                                        onChange={(e) => shiftTypeForm.setData('time_out', e.target.value)}
                                    />
                                </label>
                                <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm md:col-span-2">
                                    <input
                                        type="checkbox"
                                        className="mt-1"
                                        checked={shiftTypeForm.data.is_rest_day}
                                        onChange={(e) => shiftTypeForm.setData('is_rest_day', e.target.checked)}
                                    />
                                    <span>
                                        <span className="font-semibold text-slate-800">Rest day</span>
                                        <span className="mt-0.5 block text-xs text-slate-500">
                                            Days scheduled with this shift type are excluded from absence
                                            deductions in payroll.
                                        </span>
                                    </span>
                                </label>
                                <div className="flex items-center gap-3 md:col-span-2">
                                    <button
                                        type="submit"
                                        disabled={shiftTypeForm.processing}
                                        className="rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-60"
                                    >
                                        {editingShiftType ? 'Save Changes' : 'Add Shift Type'}
                                    </button>
                                    {editingShiftType && (
                                        <button
                                            type="button"
                                            onClick={cancelShiftTypeEdit}
                                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                        >
                                            <X className="size-4" />
                                            Cancel
                                        </button>
                                    )}
                                </div>
                            </form>

                            <table className="mt-5 min-w-full text-left text-sm">
                                <thead className="border-b border-slate-200 text-slate-500">
                                    <tr>
                                        <th className="py-2 pr-3 font-medium">Code</th>
                                        <th className="py-2 pr-3 font-medium">Name</th>
                                        <th className="py-2 pr-3 font-medium">Time In</th>
                                        <th className="py-2 pr-3 font-medium">Time Out</th>
                                        <th className="py-2 pr-3 font-medium">Rest Day</th>
                                        <th className="py-2 text-right font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {shiftTypes.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="py-6 text-center text-slate-500">
                                                No shift types yet.
                                            </td>
                                        </tr>
                                    )}
                                    {shiftTypes.map((shiftType) => (
                                        <tr key={shiftType.id} className="border-b border-slate-100 last:border-0">
                                            <td className="py-2 pr-3 font-medium text-slate-800">
                                                {shiftType.code}
                                            </td>
                                            <td className="py-2 pr-3 text-slate-700">{shiftType.name}</td>
                                            <td className="py-2 pr-3 text-slate-600">{shiftType.time_in ?? '—'}</td>
                                            <td className="py-2 pr-3 text-slate-600">{shiftType.time_out ?? '—'}</td>
                                            <td className="py-2 pr-3">
                                                {shiftType.is_rest_day && (
                                                    <span className="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                                                        REST DAY
                                                    </span>
                                                )}
                                            </td>
                                            <td className="py-2">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => startShiftTypeEdit(shiftType)}
                                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
                                                    >
                                                        <Pencil className="size-3.5" />
                                                        Edit
                                                    </button>
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50"
                                                        onClick={() => {
                                                            if (confirm(`Delete shift type "${shiftType.name}"?`)) {
                                                                router.delete(`/shift-types/${shiftType.id}`);
                                                            }
                                                        }}
                                                    >
                                                        <Trash2 className="size-3.5" />
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full border-collapse text-center text-xs">
                        <thead className="bg-slate-50 text-slate-500">
                            <tr>
                                <th
                                    rowSpan={2}
                                    className="sticky left-0 z-10 border-b border-r border-slate-200 bg-slate-50 px-3 py-2 text-left font-medium"
                                >
                                    Employee
                                </th>
                                {days.map((day) => (
                                    <th
                                        key={day.date}
                                        className={`border-b border-slate-200 px-2 py-1 font-medium ${
                                            day.is_sunday || day.is_holiday ? 'bg-rose-50 text-rose-600' : ''
                                        }`}
                                    >
                                        {day.day}
                                    </th>
                                ))}
                            </tr>
                            <tr>
                                {days.map((day) => (
                                    <th
                                        key={day.date}
                                        className={`border-b border-slate-200 px-2 py-1 font-normal ${
                                            day.is_sunday || day.is_holiday ? 'bg-rose-50 text-rose-500' : ''
                                        }`}
                                    >
                                        {day.dow}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {employees.length === 0 && (
                                <tr>
                                    <td colSpan={days.length + 1} className="px-4 py-8 text-slate-500">
                                        No active employees.
                                    </td>
                                </tr>
                            )}
                            {employees.map((employee) => (
                                <tr key={employee.id} className="border-b border-slate-100 last:border-0">
                                    <td className="sticky left-0 z-10 border-r border-slate-200 bg-white px-3 py-2 text-left">
                                        <div className="font-medium text-slate-800">
                                            {employee.first_name} {employee.last_name}
                                        </div>
                                        <div className="text-[10px] text-slate-400">
                                            {employee.employee_code} · {employee.department ?? '—'}
                                        </div>
                                    </td>
                                    {days.map((day) => {
                                        const value = currentValue(employee.id, day.date);
                                        const key = cellKey(employee.id, day.date);
                                        const dirty = key in edits;

                                        if (!value && day.is_holiday) {
                                            return (
                                                <td
                                                    key={day.date}
                                                    className="border-r border-slate-100 bg-rose-50/60 px-1 py-1 text-rose-400"
                                                    title="Holiday"
                                                >
                                                    0
                                                </td>
                                            );
                                        }

                                        return (
                                            <td key={day.date} className="border-r border-slate-100 p-0.5">
                                                <select
                                                    value={optionValue(value)}
                                                    onChange={(e) => setCell(employee.id, day.date, e.target.value)}
                                                    className={`w-12 rounded-lg border px-1 py-1 text-center text-xs ${
                                                        dirty
                                                            ? 'border-teal-400 bg-teal-50'
                                                            : 'border-transparent bg-transparent hover:border-slate-200'
                                                    }`}
                                                >
                                                    <option value="">—</option>
                                                    {shiftTypes.map((shiftType) => (
                                                        <option key={shiftType.id} value={shiftType.id}>
                                                            {shiftType.code}
                                                        </option>
                                                    ))}
                                                    <option value="leave">25</option>
                                                    <option value="business_trip">26</option>
                                                </select>
                                            </td>
                                        );
                                    })}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <p className="text-xs text-slate-500">
                    Codes come from Shift Types above · <strong>25</strong> = Leave · <strong>26</strong> =
                    Business trip · <strong>0</strong> = Holiday (shown automatically, not editable unless
                    overridden).
                </p>
            </div>
        </>
    );
}

SchedulesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Schedules', href: '/schedules' },
    ],
};
