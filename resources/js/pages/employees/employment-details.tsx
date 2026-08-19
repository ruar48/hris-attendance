import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import { OptionSelect } from '@/components/option-select';
import { useOptionList } from '@/hooks/use-option-list';
import { employeeRowClass, isFlaggedEmploymentStatus } from '@/lib/employee-flags';

type EmploymentRow = {
    id: number;
    employee_code: string;
    full_name: string;
    first_name: string;
    last_name: string;
    basic_salary: number;
    daily_rate: number;
    sunday_route_rate: number;
    hourly_rate: number;
    biometric_user_id: string | null;
    status: string;
    position: string | null;
    hire_date: string | null;
    regularization_date: string | null;
    separation_date: string | null;
    years_of_service: string | null;
    work_location: string | null;
    shift_schedule: string | null;
    time_in_schedule: string | null;
    time_out_schedule: string | null;
    immediate_superior: string | null;
    employment_status: string | null;
};

type Props = {
    employees: {
        data: EmploymentRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        per_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: { search: string; per_page: number };
    perPageOptions: number[];
    reportingToOptions: string[];
};

export default function EmploymentDetailsIndex({ employees, filters, perPageOptions, reportingToOptions }: Props) {
    const shiftOptions = useOptionList('shift_schedule');
    const [search, setSearch] = useState(filters.search ?? '');

    const reload = (params: Record<string, string | number>) =>
        router.get(
            '/employees/employment-details',
            { search: filters.search, per_page: filters.per_page, ...params },
            { preserveState: true, preserveScroll: true },
        );

    const patch = (row: EmploymentRow, changes: Partial<EmploymentRow>) => {
        router.put(
            `/employees/${row.id}`,
            { ...row, ...changes },
            {
                preserveScroll: true,
                preserveState: true,
                onError: (errors) => {
                    const count = Object.keys(errors).length;
                    toast.error(count === 1 ? Object.values(errors)[0] : `Please fix ${count} fields.`);
                },
            },
        );
    };

    const inputClass =
        'w-full min-w-[110px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';
    const dateInputClass =
        'w-28 border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';

    return (
        <>
            <Head title="Employment Details" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />

                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Employment Details</h1>
                        <p className="text-sm text-slate-500">
                            Hire dates, schedules, and reporting lines — edits here sync straight to each
                            employee's Profile and Roster record.
                        </p>
                    </div>
                    <form
                        className="relative w-full sm:w-80"
                        onSubmit={(event) => {
                            event.preventDefault();
                            reload({ search });
                        }}
                    >
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name or employee code"
                            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-10 text-sm outline-none focus:border-emerald-400"
                        />
                    </form>
                </div>

                <div className="overflow-hidden border border-slate-400 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-[11px] leading-tight">
                            <thead>
                                <tr>
                                    <th className="sticky left-0 z-10 border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Employee ID
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Employee Name
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Position
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Date Hired
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Regularization Date
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Separation Date
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Total years of service
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Work Location
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Shift Schedule
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Time In Schedule
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Time Out Schedule
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Reporting To
                                    </th>
                                    <th className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Employment Status
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.data.length === 0 && (
                                    <tr>
                                        <td colSpan={13} className="border border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                            No employees found{filters.search ? ` for "${filters.search}"` : ''}.
                                        </td>
                                    </tr>
                                )}
                                {employees.data.map((row, index) => (
                                    <tr key={row.id} className={employeeRowClass(isFlaggedEmploymentStatus(row.employment_status), index)}>
                                        <td className="sticky left-0 z-10 border border-slate-300 bg-inherit px-2 py-1 font-mono font-medium whitespace-nowrap text-slate-700">
                                            {row.employee_code}
                                        </td>
                                        <td className="border border-slate-300 px-2 py-1 whitespace-nowrap text-slate-700">
                                            {row.full_name}
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <OptionSelect
                                                category="position"
                                                value={row.position}
                                                onChange={(v) => patch(row, { position: v })}
                                                className={inputClass}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={row.hire_date ?? ''}
                                                onChange={(e) => patch(row, { hire_date: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={row.regularization_date ?? ''}
                                                onChange={(e) => patch(row, { regularization_date: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={row.separation_date ?? ''}
                                                onChange={(e) => patch(row, { separation_date: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 px-2 py-1 whitespace-nowrap text-slate-600">
                                            {row.years_of_service ?? '—'}
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                className={inputClass}
                                                defaultValue={row.work_location ?? ''}
                                                onBlur={(e) =>
                                                    e.target.value !== (row.work_location ?? '') &&
                                                    patch(row, { work_location: e.target.value })
                                                }
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <OptionSelect
                                                category="shift_schedule"
                                                value={row.shift_schedule}
                                                onChange={(v) => {
                                                    const match = shiftOptions.find((option) => option.value === v);
                                                    patch(row, {
                                                        shift_schedule: v,
                                                        time_in_schedule: match?.time_in ?? null,
                                                        time_out_schedule: match?.time_out ?? null,
                                                    });
                                                }}
                                                className={inputClass}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                className={inputClass}
                                                defaultValue={row.time_in_schedule ?? ''}
                                                onBlur={(e) =>
                                                    e.target.value !== (row.time_in_schedule ?? '') &&
                                                    patch(row, { time_in_schedule: e.target.value })
                                                }
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                className={inputClass}
                                                defaultValue={row.time_out_schedule ?? ''}
                                                onBlur={(e) =>
                                                    e.target.value !== (row.time_out_schedule ?? '') &&
                                                    patch(row, { time_out_schedule: e.target.value })
                                                }
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <select
                                                value={row.immediate_superior ?? ''}
                                                onChange={(e) => patch(row, { immediate_superior: e.target.value })}
                                                className={inputClass}
                                            >
                                                <option value="">—</option>
                                                {row.immediate_superior && !reportingToOptions.includes(row.immediate_superior) && (
                                                    <option value={row.immediate_superior}>{row.immediate_superior}</option>
                                                )}
                                                {reportingToOptions.map((name) => (
                                                    <option key={name} value={name}>
                                                        {name}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <OptionSelect
                                                category="employment_status"
                                                value={row.employment_status}
                                                onChange={(v) => patch(row, { employment_status: v })}
                                                className={inputClass}
                                            />
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-3 text-sm text-slate-600">
                            <span>
                                Showing <span className="font-medium text-slate-800">{employees.from ?? 0}</span>–
                                <span className="font-medium text-slate-800">{employees.to ?? 0}</span> of{' '}
                                <span className="font-medium text-slate-800">{employees.total}</span> employees
                            </span>
                            <label className="flex items-center gap-2">
                                <span className="text-slate-500">Rows</span>
                                <select
                                    value={filters.per_page}
                                    onChange={(event) => reload({ per_page: event.target.value, page: 1 })}
                                    className="rounded-lg border border-slate-200 bg-white px-2 py-1 text-sm outline-none focus:border-emerald-400"
                                >
                                    {perPageOptions.map((option) => (
                                        <option key={option} value={option}>
                                            {option}
                                        </option>
                                    ))}
                                </select>
                            </label>
                        </div>

                        {employees.last_page > 1 && (
                            <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
                                {employees.links.map((link, index) =>
                                    link.url === null ? (
                                        <span
                                            key={index}
                                            className="rounded-lg px-3 py-1.5 text-sm text-slate-300"
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ) : (
                                        <Link
                                            key={index}
                                            href={link.url}
                                            preserveScroll
                                            preserveState
                                            aria-current={link.active ? 'page' : undefined}
                                            className={
                                                link.active
                                                    ? 'rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-semibold text-white'
                                                    : 'rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-white hover:text-slate-900'
                                            }
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ),
                                )}
                            </nav>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

EmploymentDetailsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Employment Details', href: '/employees/employment-details' },
    ],
};
