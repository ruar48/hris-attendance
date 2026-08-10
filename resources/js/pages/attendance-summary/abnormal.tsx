import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

type AbnormalRow = {
    id: number;
    employee_code: string;
    name: string;
    department: string | null;
    work_date: string;
    time_in: string | null;
    time_out: string | null;
    late_minutes: number;
    early_minutes: number;
    total_minutes: number;
    remark: string;
};

type Props = {
    rows: AbnormalRow[];
    start_date: string;
    end_date: string;
};

export default function AttendanceAbnormalIndex({ rows, start_date, end_date }: Props) {
    const [range, setRange] = useState({ start_date, end_date });

    const applyRange = () => {
        router.get('/attendance-summary/abnormal', range, { preserveState: true });
    };

    return (
        <>
            <Head title="Abnormal Attendance" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Abnormal</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Attendance records with missing punches, late arrivals, or early departures for the
                        selected date range.
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
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 font-medium">
                                    ID
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 text-left font-medium">
                                    Name
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 text-left font-medium">
                                    Dept.
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-3 py-2 font-medium">
                                    Date
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Time1
                                </th>
                                <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Time2
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Late
                                    <br />
                                    time
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Early
                                    <br />
                                    time
                                </th>
                                <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                    Total
                                </th>
                                <th rowSpan={2} className="border-b border-slate-200 px-2 py-1 font-medium">
                                    Remark
                                </th>
                            </tr>
                            <tr>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">In</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Out</th>
                                <th className="border-b border-slate-200 px-2 py-1 font-normal">In</th>
                                <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Out</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.length === 0 && (
                                <tr>
                                    <td colSpan={11} className="px-4 py-8 text-slate-500">
                                        No abnormal attendance found for this range.
                                    </td>
                                </tr>
                            )}
                            {rows.map((row) => (
                                <tr key={row.id} className="border-b border-slate-100 last:border-0">
                                    <td className="border-r border-slate-100 px-3 py-2 text-blue-700">
                                        {row.employee_code}
                                    </td>
                                    <td className="border-r border-slate-100 px-3 py-2 text-left text-blue-700">
                                        {row.name}
                                    </td>
                                    <td className="border-r border-slate-100 px-3 py-2 text-left text-slate-600">
                                        {row.department ?? '—'}
                                    </td>
                                    <td className="border-r border-slate-100 px-3 py-2">{row.work_date}</td>
                                    <td className="px-2 py-2">{row.time_in ?? ''}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.time_out ?? ''}</td>
                                    <td className="px-2 py-2"></td>
                                    <td className="border-r border-slate-100 px-2 py-2"></td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.late_minutes}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.early_minutes}</td>
                                    <td className="border-r border-slate-100 px-2 py-2">{row.total_minutes}</td>
                                    <td className="px-2 py-2 text-left text-slate-600">{row.remark}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}

AttendanceAbnormalIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Attendance Summary', href: '/attendance-summary' },
        { title: 'Abnormal', href: '/attendance-summary/abnormal' },
    ],
};
