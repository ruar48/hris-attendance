import { Head, Link, router, useForm } from '@inertiajs/react';
import { CalendarDays, ChevronDown, ChevronUp, Maximize2, Pencil, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { ScheduleSheet } from '@/components/schedule/schedule-sheet';
import type { ScheduleData } from '@/components/schedule/schedule-sheet';

type ShiftType = {
    id: number;
    code: number;
    name: string;
    time_in: string | null;
    time_out: string | null;
    is_rest_day: boolean;
};

type Props = ScheduleData & {
    sites: { value: string; label: string }[];
    /** The selected site's value, or "all". */
    site: string;
    month: string;
    shiftTypes: ShiftType[];
};

const blankShiftTypeForm = {
    code: '',
    name: '',
    time_in: '',
    time_out: '',
    is_rest_day: false as boolean,
};

export default function SchedulesIndex({ weeks, shift_types, sites, site, month, shiftTypes }: Props) {
    const [showShiftTypes, setShowShiftTypes] = useState(false);
    const [editingShiftType, setEditingShiftType] = useState<ShiftType | null>(null);
    const shiftTypeForm = useForm({ ...blankShiftTypeForm });

    const visit = (params: { site?: string; month?: string }) =>
        router.get('/schedules', { site, month, ...params }, { preserveScroll: true });

    const siteLabel = sites.find((s) => s.value === site)?.label ?? 'All sites';
    const monthLabel = new Date(`${month}-01T00:00:00`).toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

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
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            {siteLabel} Schedule · {monthLabel}
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Laid out like the SHEDULE tab: one block per week, each person's name on the days they work.
                            Rest days, leave and business trips are excluded from payroll absence deductions, and each
                            day's shift is what late, undertime and OT are measured against.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Site</span>
                            <select
                                className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-medium"
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
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Month</span>
                            <input
                                type="month"
                                className="rounded-xl border border-slate-200 px-3 py-2.5"
                                value={month}
                                onChange={(event) => event.target.value && visit({ month: event.target.value })}
                            />
                        </label>
                        <Link
                            href="/timesheet/workbook"
                            data={{ site, month, sheet: 'schedule' }}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            <Maximize2 className="size-4" />
                            Full screen
                        </Link>
                    </div>
                </div>

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <ScheduleSheet
                        data={{ weeks, shift_types }}
                        onOpenEmployee={(employeeId) =>
                            router.get('/timesheet/workbook', { site, month, employee_id: employeeId, sheet: 'dtr' })
                        }
                    />
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
