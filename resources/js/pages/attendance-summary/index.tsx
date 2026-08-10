import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { formatPeso } from '@/lib/money';

type SummaryRow = {
    id: number;
    employee_code: string;
    name: string;
    department: string | null;
    standard_hours: number;
    actual_hours: number;
    late_frequency: number;
    late_minutes: number;
    early_frequency: number;
    early_minutes: number;
    overtime_hours: number;
    attend_standard: number;
    attend_actual: number;
    absences: number;
    leave: number;
    business_trip: number;
    overtime_pay: number;
    time_deduction: number;
    absence_deduction: number;
    real_pay: number;
};

type Props = {
    rows: SummaryRow[];
    start_date: string;
    end_date: string;
};

export default function AttendanceSummaryIndex({ rows, start_date, end_date }: Props) {
    const [range, setRange] = useState({ start_date, end_date });

    const applyRange = () => {
        router.get('/attendance-summary', range, { preserveState: true });
    };

    return (
        <>
            <Head title="Attendance Summary" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Attendance Summary</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Per-employee rollup for the selected date range, built from the same schedule and
                        attendance data used by Schedules and payroll.
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
                </section>

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full border-collapse text-center text-xs">
                        <thead className="bg-slate-50 text-slate-500">
                            <tr>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 text-left font-medium">
                                    Employee
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 text-left font-medium">
                                    Dept.
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Working Hours
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Late
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Early
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Overtime
                                    <br />
                                    (hrs)
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Attend
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Absences
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Leave
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Business
                                    <br />
                                    Trip
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    OT Pay
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Late/Early
                                    <br />
                                    Deduction
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Absence
                                    <br />
                                    Deduction
                                </th>
                                <th rowSpan={2} className="border-b border-slate-200 px-2 py-1 font-medium">
                                    Real Pay
                                </th>
                            </tr>
                            <tr>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">Std</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Actual</th>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">Freq</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Min</th>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">Freq</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Min</th>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">Std</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={17} className="px-4 py-8 text-slate-500">
                                        No active employees.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr key={row.id} className="border-b border-slate-100 last:border-0">
                                    <td className="border-r border-slate-100 px-3 py-2 text-left">
                                        <div className="font-medium text-slate-800">{row.name}</div>
                                        <div className="text-[10px] text-slate-400">{row.employee_code}</div>
                                    </td>
                                    <td className="border-r border-slate-100 px-3 py-2 text-left text-slate-600">
                                        {row.department ?? '—'}
                                    </td>
                                    <td className="px-2 py-2">{row.standard_hours}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.actual_hours}</td>
                                    <td className="px-2 py-2">{row.late_frequency}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.late_minutes}</td>
                                    <td className="px-2 py-2">{row.early_frequency}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.early_minutes}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.overtime_hours}</td>
                                    <td className="px-2 py-2">{row.attend_standard}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.attend_actual}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.absences}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.leave}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.business_trip}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">
                                        {formatPeso(row.overtime_pay)}
                                    </td>
                                    <td className="border-r border-slate-100 px-2 py-2">
                                        {formatPeso(row.time_deduction)}
                                    </td>
                                    <td className="border-r border-slate-100 px-2 py-2">
                                        {formatPeso(row.absence_deduction)}
                                    </td>
                                    <td className="px-2 py-2 font-semibold text-teal-700">
                                        {formatPeso(row.real_pay)}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}

AttendanceSummaryIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Attendance Summary', href: '/attendance-summary' },
    ],
};
