import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import { OptionSelect } from '@/components/option-select';
import { useOptionList } from '@/hooks/use-option-list';
import { GENDER_OPTIONS, MARITAL_STATUS_OPTIONS, SALARY_TYPE_OPTIONS } from '@/lib/hr-options';
import type { OptionListCategory } from '@/types/global';

type EmployeeProfile = {
    id: number;
    employee_code: string;
    full_name: string;
    first_name: string;
    last_name: string;
    email: string | null;
    position: string | null;
    department: string | null;
    basic_salary: number;
    daily_rate: number;
    sunday_route_rate: number;
    hourly_rate: number;
    biometric_user_id: string | null;
    hire_date: string | null;
    last_working_day: string | null;
    status: string;
    gender: string | null;
    marital_status: string | null;
    date_of_birth: string | null;
    place_of_birth: string | null;
    nationality: string | null;
    religion: string | null;
    contact_number: string | null;
    address: string | null;
    emergency_contact_name: string | null;
    emergency_contact_number: string | null;
    emergency_contact_address: string | null;
    emergency_contact_relationship: string | null;
    immediate_superior: string | null;
    regularization_date: string | null;
    separation_date: string | null;
    division: string | null;
    job_level: string | null;
    employment_status: string | null;
    work_location: string | null;
    shift_schedule: string | null;
    time_in_schedule: string | null;
    time_out_schedule: string | null;
    salary_type: string | null;
    tax_status: string | null;
    rice_allowance: number | null;
    transpo_allowance: number | null;
    meal_allowance: number | null;
    de_minimis_allowance: number | null;
    bank_name: string | null;
    bank_account_number: string | null;
    bank_account_status: string | null;
    sss_number: string | null;
    philhealth_number: string | null;
    pagibig_number: string | null;
    tin_number: string | null;
};

type Props = {
    employee: EmployeeProfile | null;
    search: string;
    reportingToOptions: string[];
};

const fieldClass =
    'w-full rounded border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-sm text-slate-800 outline-none focus:border-emerald-400 focus:bg-white';
const labelClass = 'text-sm font-semibold text-slate-700';

function calculateAge(dateOfBirth: string | null): number | null {
    if (!dateOfBirth) return null;
    const dob = new Date(dateOfBirth);
    if (Number.isNaN(dob.getTime())) return null;

    const today = new Date();
    let age = today.getFullYear() - dob.getFullYear();
    const monthDiff = today.getMonth() - dob.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age -= 1;
    }
    return age;
}

export default function EmployeeProfilePage({ employee, search, reportingToOptions }: Props) {
    const shiftOptions = useOptionList('shift_schedule');
    const [searchInput, setSearchInput] = useState(search ?? '');

    const runSearch = (event: React.FormEvent) => {
        event.preventDefault();
        router.get('/employees/profile', { search: searchInput }, { preserveState: true });
    };

    const patch = (changes: Partial<EmployeeProfile>) => {
        if (!employee) return;

        router.put(
            `/employees/${employee.id}`,
            { ...employee, ...changes },
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

    const Text = ({
        label,
        value,
        onCommit,
    }: {
        label: string;
        value: string | null;
        onCommit: (value: string) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <input
                className={fieldClass}
                defaultValue={value ?? ''}
                onBlur={(e) => e.target.value !== (value ?? '') && onCommit(e.target.value)}
            />
        </label>
    );

    const DateInput = ({
        label,
        value,
        onCommit,
    }: {
        label: string;
        value: string | null;
        onCommit: (value: string | null) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <input
                type="date"
                className={fieldClass}
                defaultValue={value ?? ''}
                onChange={(e) => onCommit(e.target.value || null)}
            />
        </label>
    );

    const Number_ = ({
        label,
        value,
        onCommit,
    }: {
        label: string;
        value: number | null;
        onCommit: (value: number | null) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <input
                type="number"
                min={0}
                step="0.01"
                className={fieldClass}
                defaultValue={value ?? ''}
                onBlur={(e) => onCommit(e.target.value === '' ? null : Number(e.target.value))}
            />
        </label>
    );

    const Select = ({
        label,
        value,
        options,
        onCommit,
    }: {
        label: string;
        value: string | null;
        options: { value: string; label: string }[];
        onCommit: (value: string) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <select className={fieldClass} value={value ?? ''} onChange={(e) => onCommit(e.target.value)}>
                <option value="">—</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );

    const ReportingToSelect = ({
        label,
        value,
        onCommit,
    }: {
        label: string;
        value: string | null;
        onCommit: (value: string) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <select className={fieldClass} value={value ?? ''} onChange={(e) => onCommit(e.target.value)}>
                <option value="">—</option>
                {value && !reportingToOptions.includes(value) && <option value={value}>{value}</option>}
                {reportingToOptions.map((name) => (
                    <option key={name} value={name}>
                        {name}
                    </option>
                ))}
            </select>
        </label>
    );

    const CategorySelect = ({
        label,
        category,
        value,
        onCommit,
    }: {
        label: string;
        category: OptionListCategory;
        value: string | null;
        onCommit: (value: string) => void;
    }) => (
        <label className="flex items-center gap-3">
            <span className={`w-44 shrink-0 ${labelClass}`}>{label}:</span>
            <OptionSelect category={category} value={value} onChange={onCommit} className={fieldClass} />
        </label>
    );

    return (
        <>
            <Head title="Employee Profile" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />

                <form onSubmit={runSearch} className="flex items-center gap-3 self-end">
                    <div className="relative w-72">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                        <input
                            value={searchInput}
                            onChange={(e) => setSearchInput(e.target.value)}
                            placeholder="Search Employee ID or name"
                            className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-10 text-sm outline-none focus:border-emerald-400"
                        />
                    </div>
                    <button
                        type="submit"
                        className="rounded-xl bg-rose-800 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-900"
                    >
                        Search
                    </button>
                </form>

                {!employee ? (
                    <div className="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-white p-16 text-center text-sm text-slate-500">
                        {search
                            ? `No employee found for "${search}". Try their Employee Code or name.`
                            : 'Search an Employee Code or name above to view their full profile.'}
                    </div>
                ) : (
                    <div className="overflow-hidden rounded-2xl border border-slate-300 bg-white">
                        <div className="bg-rose-800 px-6 py-5 text-white">
                            <div className="text-xl font-bold tracking-wide">
                                {employee.last_name}, {employee.first_name}
                            </div>
                            <div className="mt-1 text-sm text-rose-100 italic">{employee.position || 'Position/Title'}</div>
                            <div className="mt-1 font-mono text-xs text-rose-200">{employee.employee_code}</div>
                        </div>

                        <div className="grid gap-x-8 gap-y-3 p-6 md:grid-cols-3">
                            <div className="flex flex-col gap-3">
                                <Text label="Contact #" value={employee.contact_number} onCommit={(v) => patch({ contact_number: v })} />
                                <Text label="Email Address" value={employee.email} onCommit={(v) => patch({ email: v })} />
                                <Text label="Address" value={employee.address} onCommit={(v) => patch({ address: v })} />
                            </div>
                            <div className="flex flex-col gap-3">
                                <Select
                                    label="Gender"
                                    value={employee.gender}
                                    options={GENDER_OPTIONS}
                                    onCommit={(v) => patch({ gender: v })}
                                />
                                <Select
                                    label="Marital Status"
                                    value={employee.marital_status}
                                    options={MARITAL_STATUS_OPTIONS}
                                    onCommit={(v) => patch({ marital_status: v })}
                                />
                                <Text label="Nationality" value={employee.nationality} onCommit={(v) => patch({ nationality: v })} />
                                <Text label="Religion" value={employee.religion} onCommit={(v) => patch({ religion: v })} />
                            </div>
                            <div className="flex flex-col gap-3">
                                <DateInput
                                    label="Date of Birth"
                                    value={employee.date_of_birth}
                                    onCommit={(v) => patch({ date_of_birth: v })}
                                />
                                <Text
                                    label="Place of Birth"
                                    value={employee.place_of_birth}
                                    onCommit={(v) => patch({ place_of_birth: v })}
                                />
                                <div className="flex items-center gap-3">
                                    <span className={`w-44 shrink-0 ${labelClass}`}>Age:</span>
                                    <span className="text-sm text-slate-600">{calculateAge(employee.date_of_birth) ?? '—'}</span>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-slate-200 px-6 py-4">
                            <h2 className="mb-3 text-sm font-bold tracking-wide text-rose-800 uppercase">Emergency Contact Details</h2>
                            <div className="grid gap-3 md:w-1/2">
                                <Text
                                    label="Name"
                                    value={employee.emergency_contact_name}
                                    onCommit={(v) => patch({ emergency_contact_name: v })}
                                />
                                <Text
                                    label="Contact #"
                                    value={employee.emergency_contact_number}
                                    onCommit={(v) => patch({ emergency_contact_number: v })}
                                />
                                <Text
                                    label="Address"
                                    value={employee.emergency_contact_address}
                                    onCommit={(v) => patch({ emergency_contact_address: v })}
                                />
                                <Text
                                    label="Relationship"
                                    value={employee.emergency_contact_relationship}
                                    onCommit={(v) => patch({ emergency_contact_relationship: v })}
                                />
                            </div>
                        </div>

                        <div className="bg-rose-800 px-6 py-2.5 text-sm font-bold tracking-wide text-white uppercase">
                            Employment Details
                        </div>
                        <div className="grid gap-x-8 gap-y-3 p-6 md:grid-cols-2">
                            <div className="flex flex-col gap-3">
                                <CategorySelect
                                    label="Job Title"
                                    category="position"
                                    value={employee.position}
                                    onCommit={(v) => patch({ position: v })}
                                />
                                <CategorySelect
                                    label="Employee Status"
                                    category="employee_status"
                                    value={employee.status}
                                    onCommit={(v) => patch({ status: v })}
                                />
                                <CategorySelect
                                    label="Employment Status"
                                    category="employment_status"
                                    value={employee.employment_status}
                                    onCommit={(v) => patch({ employment_status: v })}
                                />
                                <DateInput label="Date Hired" value={employee.hire_date} onCommit={(v) => patch({ hire_date: v })} />
                                <Text label="Division" value={employee.division} onCommit={(v) => patch({ division: v })} />
                                <CategorySelect
                                    label="Department"
                                    category="department"
                                    value={employee.department}
                                    onCommit={(v) => patch({ department: v })}
                                />
                                <CategorySelect
                                    label="Job Level/Rank"
                                    category="job_level"
                                    value={employee.job_level}
                                    onCommit={(v) => patch({ job_level: v })}
                                />
                            </div>
                            <div className="flex flex-col gap-3">
                                <ReportingToSelect
                                    label="Immediate Superior"
                                    value={employee.immediate_superior}
                                    onCommit={(v) => patch({ immediate_superior: v })}
                                />
                                <DateInput
                                    label="Regularization Date"
                                    value={employee.regularization_date}
                                    onCommit={(v) => patch({ regularization_date: v })}
                                />
                                <DateInput
                                    label="Separation Date"
                                    value={employee.separation_date}
                                    onCommit={(v) => patch({ separation_date: v })}
                                />
                                <Text label="Work Location" value={employee.work_location} onCommit={(v) => patch({ work_location: v })} />
                                <label className="flex items-center gap-3">
                                    <span className={`w-44 shrink-0 ${labelClass}`}>Shift Schedule:</span>
                                    <OptionSelect
                                        category="shift_schedule"
                                        value={employee.shift_schedule}
                                        onChange={(v) => {
                                            const match = shiftOptions.find((option) => option.value === v);
                                            patch({
                                                shift_schedule: v,
                                                time_in_schedule: match?.time_in ?? null,
                                                time_out_schedule: match?.time_out ?? null,
                                            });
                                        }}
                                        className={fieldClass}
                                    />
                                </label>
                                <Text
                                    label="Time In Schedule"
                                    value={employee.time_in_schedule}
                                    onCommit={(v) => patch({ time_in_schedule: v })}
                                />
                                <Text
                                    label="Time Out Schedule"
                                    value={employee.time_out_schedule}
                                    onCommit={(v) => patch({ time_out_schedule: v })}
                                />
                            </div>
                        </div>

                        <div className="bg-rose-800 px-6 py-2.5 text-sm font-bold tracking-wide text-white uppercase">
                            Compensation &amp; Payroll Details
                        </div>
                        <div className="grid gap-x-8 gap-y-3 p-6 md:grid-cols-2">
                            <div className="flex flex-col gap-3">
                                <Select
                                    label="Salary Type"
                                    value={employee.salary_type}
                                    options={SALARY_TYPE_OPTIONS}
                                    onCommit={(v) => patch({ salary_type: v })}
                                />
                                <Number_
                                    label="Basic Salary (₱)"
                                    value={employee.basic_salary}
                                    onCommit={(v) => patch({ basic_salary: v ?? 0 })}
                                />
                                <Text label="Tax Status" value={employee.tax_status} onCommit={(v) => patch({ tax_status: v })} />
                                <Number_
                                    label="Rice Allowance (₱)"
                                    value={employee.rice_allowance}
                                    onCommit={(v) => patch({ rice_allowance: v })}
                                />
                                <Number_
                                    label="Transpo Allowance (₱)"
                                    value={employee.transpo_allowance}
                                    onCommit={(v) => patch({ transpo_allowance: v })}
                                />
                                <Number_
                                    label="Meal Allowance (₱)"
                                    value={employee.meal_allowance}
                                    onCommit={(v) => patch({ meal_allowance: v })}
                                />
                                <Number_
                                    label="De minimis Allowance (₱)"
                                    value={employee.de_minimis_allowance}
                                    onCommit={(v) => patch({ de_minimis_allowance: v })}
                                />
                            </div>
                            <div className="flex flex-col gap-3">
                                <Text label="Bank Name" value={employee.bank_name} onCommit={(v) => patch({ bank_name: v })} />
                                <Text
                                    label="Account Number"
                                    value={employee.bank_account_number}
                                    onCommit={(v) => patch({ bank_account_number: v })}
                                />
                                <Text
                                    label="Bank Account Status"
                                    value={employee.bank_account_status}
                                    onCommit={(v) => patch({ bank_account_status: v })}
                                />
                            </div>
                        </div>

                        <div className="bg-rose-800 px-6 py-2.5 text-sm font-bold tracking-wide text-white uppercase">
                            Government Benefits Details
                        </div>
                        <div className="grid gap-x-8 gap-y-3 p-6 md:grid-cols-2">
                            <div className="flex flex-col gap-3">
                                <Text label="SSS Number" value={employee.sss_number} onCommit={(v) => patch({ sss_number: v })} />
                                <Text
                                    label="PhilHealth Number"
                                    value={employee.philhealth_number}
                                    onCommit={(v) => patch({ philhealth_number: v })}
                                />
                            </div>
                            <div className="flex flex-col gap-3">
                                <Text label="Pagibig Number" value={employee.pagibig_number} onCommit={(v) => patch({ pagibig_number: v })} />
                                <Text label="TIN Number" value={employee.tin_number} onCommit={(v) => patch({ tin_number: v })} />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

EmployeeProfilePage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Profile', href: '/employees/profile' },
    ],
};
