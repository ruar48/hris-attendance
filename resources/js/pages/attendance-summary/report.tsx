import { Head, router } from '@inertiajs/react';
import { Download, Search } from 'lucide-react';
import { useState } from 'react';

type DayRow = {
    date: string;
    label: string;
    ot_in: string | null;
    ot_out: string | null;
};

type EmployeeReport = {
    id: number;
    employee_code: string;
    name: string;
    department: string | null;
    absences: number;
    leave: number;
    business_trip: number;
    attend_standard: number;
    ot_normal_hours: number;
    ot_special_hours: number;
    late_frequency: number;
    late_minutes: number;
    early_frequency: number;
    early_minutes: number;
    days: DayRow[];
};

type Props = {
    employees: EmployeeReport[];
    start_date: string;
    end_date: string;
    filters: {
        search: string;
    };
};

export default function AttendanceReportIndex({ employees, start_date, end_date, filters }: Props) {
    const [range, setRange] = useState({ start_date, end_date });
    const [search, setSearch] = useState(filters.search ?? '');

    const applyFilters = () => {
        router.get('/attendance-summary/report', { ...range, search }, { preserveState: true });
    };

    const exportPdfUrl = `/attendance-summary/report/pdf?${new URLSearchParams({
        ...range,
        search,
    }).toString()}`;

    return (
        <>
            <Head title="Attendance Report" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Attendance Report</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Per-employee daily attendance card for the selected date range.
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
                    <label className="text-sm">
                        <span className="mb-1.5 block text-slate-600">Search</span>
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                type="text"
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                                placeholder="Name, ID, or department"
                                className="w-64 rounded-xl border border-slate-200 py-2.5 pr-3 pl-9"
                            />
                        </div>
                    </label>
                    <button
                        type="button"
                        onClick={applyFilters}
                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                    >
                        Apply
                    </button>
                    <a
                        href={exportPdfUrl}
                        className="ml-auto flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700"
                    >
                        <Download className="size-4" />
                        Export PDF
                    </a>
                </section>

                {employees.length === 0 && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                        No active employees.
                    </div>
                )}

                {employees.map((employee) => (
                    <section
                        key={employee.id}
                        className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm"
                    >
                        <div className="grid grid-cols-1 divide-y divide-slate-200 border-b border-slate-200 text-xs md:grid-cols-3 md:divide-x md:divide-y-0">
                            <div className="px-4 py-3">
                                <div className="text-slate-400">Dept.</div>
                                <div className="font-medium text-slate-800">{employee.department ?? '—'}</div>
                            </div>
                            <div className="px-4 py-3">
                                <div className="text-slate-400">Name</div>
                                <div className="font-medium text-slate-800">{employee.name}</div>
                            </div>
                            <div className="px-4 py-3">
                                <div className="text-slate-400">ID</div>
                                <div className="font-medium text-slate-800">{employee.employee_code}</div>
                            </div>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full border-collapse text-center text-xs">
                                <thead className="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th className="border-b border-r border-slate-200 px-2 py-2 font-medium">
                                            Absences
                                            <br />
                                            (Day)
                                        </th>
                                        <th className="border-b border-r border-slate-200 px-2 py-2 font-medium">
                                            Leave
                                            <br />
                                            (Day)
                                        </th>
                                        <th className="border-b border-r border-slate-200 px-2 py-2 font-medium">
                                            Business
                                            <br />
                                            trip (Day)
                                        </th>
                                        <th className="border-b border-r border-slate-200 px-2 py-2 font-medium">
                                            Attendance
                                            <br />
                                            (Day)
                                        </th>
                                        <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                            OT
                                        </th>
                                        <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                            Late
                                        </th>
                                        <th colSpan={2} className="border-b border-slate-200 px-2 py-1 font-medium">
                                            Early
                                        </th>
                                    </tr>
                                    <tr>
                                        <th className="border-b border-r border-slate-200 px-2 py-1"></th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1"></th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1"></th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1"></th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">Normal</th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Special</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">Frequency</th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Min</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">Frequency</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">Min</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.absences}</td>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.leave}</td>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.business_trip}</td>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.attend_standard}</td>
                                        <td className="px-2 py-2">{employee.ot_normal_hours}</td>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.ot_special_hours}</td>
                                        <td className="px-2 py-2">{employee.late_frequency}</td>
                                        <td className="border-r border-slate-100 px-2 py-2">{employee.late_minutes}</td>
                                        <td className="px-2 py-2">{employee.early_frequency}</td>
                                        <td className="px-2 py-2">{employee.early_minutes}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div className="overflow-x-auto border-t border-slate-200">
                            <table className="min-w-full border-collapse text-center text-xs">
                                <thead className="bg-slate-50 text-slate-500">
                                    <tr>
                                        <th rowSpan={2} className="border-b border-r border-slate-200 px-2 py-2 font-medium">
                                            Date/Week
                                        </th>
                                        <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                            Time1
                                        </th>
                                        <th colSpan={2} className="border-b border-r border-slate-200 px-2 py-1 font-medium">
                                            Time2
                                        </th>
                                        <th colSpan={2} className="border-b border-slate-200 px-2 py-1 font-medium">
                                            OT
                                        </th>
                                    </tr>
                                    <tr>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">In</th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Out</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">In</th>
                                        <th className="border-b border-r border-slate-200 px-2 py-1 font-normal">Out</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">In</th>
                                        <th className="border-b border-slate-200 px-2 py-1 font-normal">Out</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {employee.days.map((day) => (
                                        <tr key={day.date} className="border-b border-slate-100 last:border-0">
                                            <td className="border-r border-slate-100 px-2 py-1.5 text-slate-600">
                                                {day.label}
                                            </td>
                                            <td className="px-2 py-1.5"></td>
                                            <td className="border-r border-slate-100 px-2 py-1.5"></td>
                                            <td className="px-2 py-1.5"></td>
                                            <td className="border-r border-slate-100 px-2 py-1.5"></td>
                                            <td className="px-2 py-1.5 text-blue-700">{day.ot_in ?? ''}</td>
                                            <td className="px-2 py-1.5 text-blue-700">{day.ot_out ?? ''}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                ))}
            </div>
        </>
    );
}

AttendanceReportIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Attendance Summary', href: '/attendance-summary' },
        { title: 'Attendance Report', href: '/attendance-summary/report' },
    ],
};
