import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Search, UserCircle } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import { OptionSelect } from '@/components/option-select';
import { GENDER_OPTIONS, MARITAL_STATUS_OPTIONS } from '@/lib/hr-options';

type MasterFileRow = {
    id: number;
    employee_code: string;
    biometric_user_id: string | null;
    last_name: string;
    first_name: string;
    middle_name: string | null;
    suffix: string | null;
    photo_url: string | null;
    gender: string | null;
    marital_status: string | null;
    date_of_birth: string | null;
    place_of_birth: string | null;
    nationality: string | null;
    religion: string | null;
    contact_number: string | null;
    personal_email: string | null;
    company_email: string | null;
    current_address: string | null;
    permanent_address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_number: string | null;
    emergency_contact_relationship: string | null;
    status: string;
    basic_salary: number;
    daily_rate: number;
    hourly_rate: number;
};

type Props = {
    employees: {
        data: MasterFileRow[];
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

export default function MasterFileIndex({ employees, filters, perPageOptions }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const reload = (params: Record<string, string | number>) =>
        router.get(
            '/employees/master-file',
            { search: filters.search, per_page: filters.per_page, ...params },
            { preserveState: true, preserveScroll: true },
        );

    const patch = (row: MasterFileRow, changes: Partial<MasterFileRow>) => {
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
    const dateInputClass =
        'w-28 border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';
    const wideInputClass =
        'w-full min-w-[220px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';

    const TextCell = ({
        value,
        onCommit,
        wide,
    }: {
        value: string | null;
        onCommit: (value: string) => void;
        wide?: boolean;
    }) => (
        <input
            className={wide ? wideInputClass : inputClass}
            defaultValue={value ?? ''}
            onBlur={(e) => e.target.value !== (value ?? '') && onCommit(e.target.value)}
        />
    );

    return (
        <>
            <Head title="Employee Master File" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />

                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Employee Master File</h1>
                        <p className="text-sm text-slate-500">
                            The full 201-file record for every employee — synced with Profile and Employment
                            Details.
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
                                    {[
                                        'Employee ID',
                                        'Biometrics ID',
                                        'Last Name',
                                        'First Name',
                                        'Middle Name, Suffix',
                                        'Photo',
                                        'Gender',
                                        'Civil Status',
                                        'Date of Birth',
                                        'Place of Birth',
                                        'Nationality',
                                        'Religion',
                                        'Contact Number',
                                        'Personal Email',
                                        'Company Email',
                                        'Current Address',
                                        'Permanent Address',
                                        'Emergency Contact Name',
                                        'Emergency Contact Number',
                                        'Relationship',
                                        'Status',
                                        'Profile',
                                    ].map((label, i) => (
                                        <th
                                            key={label}
                                            className={
                                                i === 0
                                                    ? 'sticky left-0 z-10 border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold whitespace-nowrap text-rose-950'
                                                    : 'border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold whitespace-nowrap text-rose-950'
                                            }
                                        >
                                            {label}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {employees.data.length === 0 && (
                                    <tr>
                                        <td colSpan={22} className="border border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                            No employees found{filters.search ? ` for "${filters.search}"` : ''}.
                                        </td>
                                    </tr>
                                )}
                                {employees.data.map((row, index) => (
                                    <tr key={row.id} className={index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}>
                                        <td className="sticky left-0 z-10 border border-slate-300 bg-inherit px-2 py-1 font-mono font-medium whitespace-nowrap text-slate-700">
                                            {row.employee_code}
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.biometric_user_id}
                                                onCommit={(v) => patch(row, { biometric_user_id: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.last_name} onCommit={(v) => patch(row, { last_name: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.first_name} onCommit={(v) => patch(row, { first_name: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <div className="flex">
                                                <input
                                                    className={inputClass}
                                                    defaultValue={row.middle_name ?? ''}
                                                    placeholder="Middle"
                                                    onBlur={(e) =>
                                                        e.target.value !== (row.middle_name ?? '') &&
                                                        patch(row, { middle_name: e.target.value })
                                                    }
                                                />
                                                <input
                                                    className={`${inputClass} w-16 min-w-0 border-l border-slate-200`}
                                                    defaultValue={row.suffix ?? ''}
                                                    placeholder="Suffix"
                                                    onBlur={(e) =>
                                                        e.target.value !== (row.suffix ?? '') &&
                                                        patch(row, { suffix: e.target.value })
                                                    }
                                                />
                                            </div>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <div className="flex items-center gap-1">
                                                <input
                                                    className={inputClass}
                                                    defaultValue={row.photo_url ?? ''}
                                                    placeholder="Drive link"
                                                    onBlur={(e) =>
                                                        e.target.value !== (row.photo_url ?? '') &&
                                                        patch(row, { photo_url: e.target.value })
                                                    }
                                                />
                                                {row.photo_url && (
                                                    <a
                                                        href={row.photo_url}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="shrink-0 pr-1 text-emerald-600 hover:text-emerald-800"
                                                        title="View photo"
                                                    >
                                                        <ExternalLink className="size-3.5" />
                                                    </a>
                                                )}
                                            </div>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <select
                                                value={row.gender ?? ''}
                                                onChange={(e) => patch(row, { gender: e.target.value })}
                                                className={inputClass}
                                            >
                                                <option value="">—</option>
                                                {GENDER_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <select
                                                value={row.marital_status ?? ''}
                                                onChange={(e) => patch(row, { marital_status: e.target.value })}
                                                className={inputClass}
                                            >
                                                <option value="">—</option>
                                                {MARITAL_STATUS_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={row.date_of_birth ?? ''}
                                                onChange={(e) => patch(row, { date_of_birth: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.place_of_birth} onCommit={(v) => patch(row, { place_of_birth: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.nationality} onCommit={(v) => patch(row, { nationality: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.religion} onCommit={(v) => patch(row, { religion: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell value={row.contact_number} onCommit={(v) => patch(row, { contact_number: v })} />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.personal_email}
                                                onCommit={(v) => patch(row, { personal_email: v })}
                                                wide
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.company_email}
                                                onCommit={(v) => patch(row, { company_email: v })}
                                                wide
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.current_address}
                                                onCommit={(v) => patch(row, { current_address: v })}
                                                wide
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.permanent_address}
                                                onCommit={(v) => patch(row, { permanent_address: v })}
                                                wide
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.emergency_contact_name}
                                                onCommit={(v) => patch(row, { emergency_contact_name: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.emergency_contact_number}
                                                onCommit={(v) => patch(row, { emergency_contact_number: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <TextCell
                                                value={row.emergency_contact_relationship}
                                                onCommit={(v) => patch(row, { emergency_contact_relationship: v })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <OptionSelect
                                                category="employee_status"
                                                value={row.status}
                                                onChange={(v) => patch(row, { status: v })}
                                                className={inputClass}
                                                allowEmpty={false}
                                            />
                                        </td>
                                        <td className="border border-slate-300 px-1.5 py-1 text-center">
                                            <Link
                                                href={`/employees/profile?search=${encodeURIComponent(row.employee_code)}`}
                                                className="inline-flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-rose-700 hover:bg-rose-50"
                                            >
                                                <UserCircle className="size-3.5" />
                                                View
                                            </Link>
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

MasterFileIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Employee Master File', href: '/employees/master-file' },
    ],
};
