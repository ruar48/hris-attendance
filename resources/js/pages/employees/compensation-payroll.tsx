import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import { OptionSelect } from '@/components/option-select';
import { employeeRowClass, isFlaggedEmploymentStatus } from '@/lib/employee-flags';
import { BANK_ACCOUNT_STATUS_OPTIONS, SALARY_TYPE_OPTIONS } from '@/lib/hr-options';

type CompensationRow = {
    id: number;
    employee_code: string;
    full_name: string;
    first_name: string;
    last_name: string;
    position: string | null;
    salary_type: string | null;
    basic_salary: number;
    tax_status: string | null;
    rice_allowance: number | null;
    transpo_allowance: number | null;
    de_minimis_allowance: number | null;
    meal_allowance: number | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_status: string | null;
    employment_status: string | null;
    daily_rate: number;
    hourly_rate: number;
};

type Props = {
    employees: {
        data: CompensationRow[];
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
};

export default function CompensationPayrollIndex({ employees, filters, perPageOptions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const reload = (params: Record<string, string | number>) =>
        router.get(
            '/employees/compensation-payroll',
            { search: filters.search, per_page: filters.per_page, ...params },
            { preserveState: true, preserveScroll: true },
        );

    const patch = (row: CompensationRow, changes: Partial<CompensationRow>) => {
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
        'w-full min-w-[130px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';
    const narrowInputClass =
        'w-full min-w-[90px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';

    const TextCell = ({ value, onCommit }: { value: string | null; onCommit: (value: string) => void }) => (
        <input
            className={inputClass}
            defaultValue={value ?? ''}
            onBlur={(e) => e.target.value !== (value ?? '') && onCommit(e.target.value)}
        />
    );

    const MoneyCell = ({ value, onCommit }: { value: number | null; onCommit: (value: number | null) => void }) => (
        <input
            type="number"
            min={0}
            step="0.01"
            className={narrowInputClass}
            defaultValue={value ?? ''}
            onBlur={(e) => onCommit(e.target.value === '' ? null : Number(e.target.value))}
        />
    );

    return (
        <>
            <Head title="Compensation & Payroll Details" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />

                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Compensation &amp; Payroll Details</h1>
                        <p className="text-sm text-slate-500">
                            Salary type and basic salary per employee — synced with their Profile and the Payroll
                            module.
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
                                    {['Employee ID', 'Employee Name', 'Position', 'Salary Type', 'Basic Salary', 'Tax Status'].map(
                                        (label, i) => (
                                            <th
                                                key={label}
                                                rowSpan={2}
                                                className={
                                                    i === 0
                                                        ? 'sticky left-0 z-10 border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold whitespace-nowrap text-rose-950'
                                                        : 'border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold whitespace-nowrap text-rose-950'
                                                }
                                            >
                                                {label}
                                            </th>
                                        ),
                                    )}
                                    <th colSpan={4} className="border border-slate-400 bg-rose-800 px-2 py-1.5 text-center font-bold tracking-wide text-white uppercase">
                                        Allowances
                                    </th>
                                    <th colSpan={3} className="border border-slate-400 bg-rose-800 px-2 py-1.5 text-center font-bold tracking-wide text-white uppercase">
                                        Bank Details
                                    </th>
                                </tr>
                                <tr>
                                    {['Rice Allowance', 'Transpo Allowance', 'De Minimis Allowance', 'Meal Allowance', 'Bank Name', 'Account Number', 'Bank Account Status'].map(
                                        (label) => (
                                            <th
                                                key={label}
                                                className="border border-slate-400 bg-rose-100 px-2 py-1.5 font-semibold whitespace-nowrap text-rose-900"
                                            >
                                                {label}
                                            </th>
                                        ),
                                    )}
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
                                            <select
                                                value={row.salary_type ?? ''}
                                                onChange={(e) => patch(row, { salary_type: e.target.value })}
                                                className={inputClass}
                                            >
                                                <option value="">—</option>
                                                {SALARY_TYPE_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="number"
                                                min={0}
                                                step="0.01"
                                                className={inputClass}
                                                defaultValue={row.basic_salary}
                                                onBlur={(e) => patch(row, { basic_salary: e.target.value === '' ? 0 : Number(e.target.value) })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.tax_status} onCommit={(v) => patch(row, { tax_status: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <MoneyCell
                                                value={row.rice_allowance}
                                                onCommit={(v) => patch(row, { rice_allowance: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <MoneyCell
                                                value={row.transpo_allowance}
                                                onCommit={(v) => patch(row, { transpo_allowance: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <MoneyCell
                                                value={row.de_minimis_allowance}
                                                onCommit={(v) => patch(row, { de_minimis_allowance: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <MoneyCell
                                                value={row.meal_allowance}
                                                onCommit={(v) => patch(row, { meal_allowance: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.bank_name} onCommit={(v) => patch(row, { bank_name: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.bank_account_number}
                                                onCommit={(v) => patch(row, { bank_account_number: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <select
                                                value={row.bank_account_status ?? ''}
                                                onChange={(e) => patch(row, { bank_account_status: e.target.value })}
                                                className={inputClass}
                                            >
                                                <option value="">—</option>
                                                {BANK_ACCOUNT_STATUS_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
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

CompensationPayrollIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Compensation & Payroll Details', href: '/employees/compensation-payroll' },
    ],
};
