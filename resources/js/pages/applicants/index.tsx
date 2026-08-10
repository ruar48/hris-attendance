import { Head, router, useForm } from '@inertiajs/react';
import { Search, Trash2, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { EmployeeSectionTabs } from '@/components/employee-section-tabs';
import { OptionSelect } from '@/components/option-select';
import { useOptionList } from '@/hooks/use-option-list';

type RequirementField =
    | 'req_psa_birth_certificate'
    | 'req_government_ids'
    | 'req_nbi_clearance'
    | 'req_police_clearance'
    | 'req_barangay_clearance'
    | 'req_sss_number'
    | 'req_philhealth_number'
    | 'req_pagibig_id'
    | 'req_tin_number'
    | 'req_college_diploma_tor'
    | 'req_marriage_certificate'
    | 'req_solo_parent_id'
    | 'req_bir_form_2305_1905'
    | 'req_latest_bir_form_2316'
    | 'req_certificate_of_employment';

type ApplicationStatus =
    | 'for_initial_interview'
    | 'initial_interview_failed'
    | 'for_final_interview'
    | 'final_interview_failed'
    | 'job_offer'
    | 'job_offer_declined'
    | 'cancelled_application'
    | 'onboarding'
    | 'terminated'
    | 'endo'
    | 'resigned';

type RequirementStatus = 'complete' | 'incomplete' | 'pending';

type ApplicantRow = {
    id: number;
    application_number: string;
    applicant_name: string;
    position_applied: string | null;
    date_of_application: string | null;
    application_status: ApplicationStatus;
    date_submitted: string | null;
    requirement_status: RequirementStatus;
    onboarding_date: string | null;
    onboarding_training_1: string | null;
    onboarding_training_2: string | null;
    onboarding_training_3: string | null;
    onboarding_training_4: string | null;
} & Record<RequirementField, boolean>;

type Props = {
    applicants: ApplicantRow[];
    filters: { search: string };
    nextApplicationNumber: string;
};

const REQUIREMENT_COLUMNS: { key: RequirementField; label: string }[] = [
    { key: 'req_psa_birth_certificate', label: 'PSA Birth Certificate' },
    { key: 'req_government_ids', label: 'Government IDs (2) Copy' },
    { key: 'req_nbi_clearance', label: 'NBI Clearance' },
    { key: 'req_police_clearance', label: 'Police Clearance' },
    { key: 'req_barangay_clearance', label: 'Barangay Clearance' },
    { key: 'req_sss_number', label: 'SSS Number' },
    { key: 'req_philhealth_number', label: 'PhilHealth (HD MF)' },
    { key: 'req_pagibig_id', label: 'Pagibig ID' },
    { key: 'req_tin_number', label: 'TIN Number' },
    { key: 'req_college_diploma_tor', label: 'College Diploma / TOR' },
    { key: 'req_marriage_certificate', label: 'Marriage Certificate' },
    { key: 'req_solo_parent_id', label: 'Solo Parent ID' },
    { key: 'req_bir_form_2305_1905', label: 'BIR Form 2305 & 1905' },
    { key: 'req_latest_bir_form_2316', label: 'Latest BIR Form 2316' },
    { key: 'req_certificate_of_employment', label: 'Certificate of Employment' },
];

const APPLICATION_STATUS_OPTIONS: { value: ApplicationStatus; label: string }[] = [
    { value: 'for_initial_interview', label: 'For Initial Interview' },
    { value: 'initial_interview_failed', label: 'Initial Interview Failed' },
    { value: 'for_final_interview', label: 'For Final Interview' },
    { value: 'final_interview_failed', label: 'Final Interview Failed' },
    { value: 'job_offer', label: 'Job Offer' },
    { value: 'job_offer_declined', label: 'Job Offer Declined' },
    { value: 'cancelled_application', label: 'Cancelled Application' },
    { value: 'onboarding', label: 'Onboarding' },
    { value: 'terminated', label: 'Terminated' },
    { value: 'endo', label: 'Endo' },
    { value: 'resigned', label: 'Resigned' },
];

const APPLICATION_STATUS_STYLES: Record<ApplicationStatus, string> = {
    for_initial_interview: 'bg-slate-200 text-slate-700',
    initial_interview_failed: 'bg-rose-500 text-white',
    for_final_interview: 'bg-slate-800 text-white',
    final_interview_failed: 'bg-red-700 text-white',
    job_offer: 'bg-emerald-500 text-white',
    job_offer_declined: 'bg-violet-600 text-white',
    cancelled_application: 'bg-slate-400 text-white',
    onboarding: 'bg-emerald-600 text-white',
    terminated: 'bg-slate-600 text-white',
    endo: 'bg-amber-400 text-amber-950',
    resigned: 'bg-sky-600 text-white',
};

const REQUIREMENT_STATUS_OPTIONS: { value: RequirementStatus; label: string }[] = [
    { value: 'complete', label: 'Complete' },
    { value: 'incomplete', label: 'Incomplete' },
    { value: 'pending', label: 'Pending' },
];

const REQUIREMENT_STATUS_STYLES: Record<RequirementStatus, string> = {
    complete: 'bg-emerald-600 text-white',
    incomplete: 'bg-rose-600 text-white',
    pending: 'bg-violet-500 text-white',
};

const emptyForm = {
    application_number: '',
    applicant_name: '',
    position_applied: '',
    date_of_application: new Date().toISOString().slice(0, 10),
    application_status: 'for_initial_interview' as ApplicationStatus,
};

export default function ApplicantsIndex({ applicants, filters, nextApplicationNumber }: Props) {
    const positionOptions = useOptionList('position');
    const [search, setSearch] = useState(filters.search ?? '');
    const [showForm, setShowForm] = useState(false);
    const [deleting, setDeleting] = useState<ApplicantRow | null>(null);
    const form = useForm({ ...emptyForm });

    const reload = (params: Record<string, string>) =>
        router.get('/applicants', { search: filters.search, ...params }, { preserveState: true, preserveScroll: true });

    const patch = (applicant: ApplicantRow, changes: Partial<ApplicantRow>) => {
        router.put(
            `/applicants/${applicant.id}`,
            { ...applicant, ...changes },
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

    const submitCreate = (event: React.FormEvent) => {
        event.preventDefault();
        form.post('/applicants', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                form.clearErrors();
                setShowForm(false);
            },
            onError: (errors) => {
                const count = Object.keys(errors).length;
                toast.error(count === 1 ? Object.values(errors)[0] : `Please fix ${count} fields.`);
            },
        });
    };

    const remove = () => {
        if (!deleting) return;
        router.delete(`/applicants/${deleting.id}`, { preserveScroll: true, onFinish: () => setDeleting(null) });
    };

    const inputClass =
        'w-full min-w-[120px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';
    const dateInputClass =
        'w-28 border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400';

    return (
        <>
            <Head title="Applicants" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <EmployeeSectionTabs />
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Application / Onboarding & Requirements Tracker</h1>
                        <p className="text-sm text-slate-500">
                            Track applicants from application through onboarding requirements and orientation.
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:flex-row md:w-auto md:items-end">
                        <form
                            className="relative w-full sm:w-72"
                            onSubmit={(event) => {
                                event.preventDefault();
                                reload({ search });
                            }}
                        >
                            <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <input
                                value={search}
                                onChange={(event) => setSearch(event.target.value)}
                                placeholder="Search name, position, application #"
                                className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-10 text-sm outline-none focus:border-emerald-400"
                            />
                        </form>
                        <button
                            type="button"
                            onClick={() => (showForm ? (form.reset(), form.clearErrors(), setShowForm(false)) : setShowForm(true))}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            {showForm ? <X className="size-4" /> : <UserPlus className="size-4" />}
                            {showForm ? 'Close' : 'Add Applicant'}
                        </button>
                    </div>
                </div>

                {showForm && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <div className="rounded-xl bg-emerald-100 p-2 text-emerald-600">
                                <UserPlus className="size-4" />
                            </div>
                            <h2 className="text-lg font-semibold text-slate-900">New Applicant</h2>
                        </div>
                        <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-3" onSubmit={submitCreate}>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Application #</span>
                                <input
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                                    value={form.data.application_number}
                                    onChange={(e) => form.setData('application_number', e.target.value)}
                                    placeholder={`Auto: ${nextApplicationNumber}`}
                                />
                                {form.errors.application_number && (
                                    <span className="mt-1 block text-xs text-rose-600">{form.errors.application_number}</span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Applicant Name *</span>
                                <input
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                                    value={form.data.applicant_name}
                                    onChange={(e) => form.setData('applicant_name', e.target.value)}
                                    required
                                />
                                {form.errors.applicant_name && (
                                    <span className="mt-1 block text-xs text-rose-600">{form.errors.applicant_name}</span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Position Applied</span>
                                <select
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                                    value={form.data.position_applied}
                                    onChange={(e) => form.setData('position_applied', e.target.value)}
                                >
                                    <option value="">Select position</option>
                                    {positionOptions.map((position) => (
                                        <option key={position.value} value={position.value}>
                                            {position.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Date of Application</span>
                                <input
                                    type="date"
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                                    value={form.data.date_of_application}
                                    onChange={(e) => form.setData('date_of_application', e.target.value)}
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Application Status</span>
                                <select
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                                    value={form.data.application_status}
                                    onChange={(e) => form.setData('application_status', e.target.value as ApplicationStatus)}
                                >
                                    {APPLICATION_STATUS_OPTIONS.map((option) => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <div className="flex items-center gap-3 md:col-span-2 lg:col-span-3">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    Save Applicant
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        form.reset();
                                        form.clearErrors();
                                        setShowForm(false);
                                    }}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <div className="overflow-hidden border border-slate-400 bg-white">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-[11px] leading-tight">
                            <thead>
                                <tr>
                                    <th rowSpan={2} className="sticky left-0 z-10 border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Application #
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Applicant Name
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Position Applied
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Date of Application
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Application Status
                                    </th>
                                    <th colSpan={REQUIREMENT_COLUMNS.length} className="border border-slate-400 bg-rose-800 px-2 py-1.5 text-center font-bold tracking-wide text-white uppercase">
                                        Requirements Checklist
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Date Submitted
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Requirement Status
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 font-semibold text-rose-950">
                                        Onboarding Date
                                    </th>
                                    <th colSpan={4} className="border border-slate-400 bg-rose-800 px-2 py-1.5 text-center font-bold tracking-wide text-white uppercase">
                                        Onboarding Training / Orientation
                                    </th>
                                    <th rowSpan={2} className="border border-slate-400 bg-rose-200 px-2 py-1.5 text-right font-semibold text-rose-950">
                                        Actions
                                    </th>
                                </tr>
                                <tr>
                                    {REQUIREMENT_COLUMNS.map((column) => (
                                        <th
                                            key={column.key}
                                            className="border border-slate-400 bg-rose-100 px-0.5 py-1 text-center align-bottom font-semibold text-rose-900"
                                        >
                                            <span
                                                className="inline-block h-28 [writing-mode:vertical-rl]"
                                                style={{ transform: 'rotate(180deg)' }}
                                            >
                                                {column.label}
                                            </span>
                                        </th>
                                    ))}
                                    {[1, 2, 3, 4].map((n) => (
                                        <th key={n} className="border border-slate-400 bg-rose-100 px-2 py-1.5 text-center font-semibold text-rose-900">
                                            {n}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {applicants.length === 0 && (
                                    <tr>
                                        <td colSpan={9 + REQUIREMENT_COLUMNS.length} className="border border-slate-300 px-4 py-10 text-center text-sm text-slate-500">
                                            No applicants found{filters.search ? ` for "${filters.search}"` : ''}.
                                        </td>
                                    </tr>
                                )}
                                {applicants.map((applicant, index) => (
                                    <tr key={applicant.id} className={index % 2 === 0 ? 'bg-white' : 'bg-slate-50'}>
                                        <td className="sticky left-0 z-10 border border-slate-300 bg-inherit px-2 py-1 font-mono font-medium whitespace-nowrap text-slate-700">
                                            {applicant.application_number}
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                className={inputClass}
                                                defaultValue={applicant.applicant_name}
                                                onBlur={(e) =>
                                                    e.target.value !== applicant.applicant_name &&
                                                    patch(applicant, { applicant_name: e.target.value })
                                                }
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <OptionSelect
                                                category="position"
                                                value={applicant.position_applied}
                                                onChange={(v) => patch(applicant, { position_applied: v || null })}
                                                className="w-full min-w-[130px] border-0 bg-transparent px-2 py-1 text-[11px] outline-none focus:bg-blue-50 focus:ring-1 focus:ring-inset focus:ring-blue-400"
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={applicant.date_of_application ?? ''}
                                                onChange={(e) => patch(applicant, { date_of_application: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0 text-center">
                                            <select
                                                value={applicant.application_status}
                                                onChange={(e) =>
                                                    patch(applicant, {
                                                        application_status: e.target.value as ApplicationStatus,
                                                    })
                                                }
                                                className={`w-full appearance-none border-0 px-2 py-1.5 text-center text-[11px] font-bold outline-none ${APPLICATION_STATUS_STYLES[applicant.application_status]}`}
                                            >
                                                {APPLICATION_STATUS_OPTIONS.map((option) => (
                                                    <option key={option.value} value={option.value}>
                                                        {option.label}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        {REQUIREMENT_COLUMNS.map((column) => (
                                            <td key={column.key} className="border border-slate-300 px-1 py-1 text-center">
                                                <input
                                                    type="checkbox"
                                                    checked={applicant[column.key]}
                                                    onChange={(e) => patch(applicant, { [column.key]: e.target.checked } as Partial<ApplicantRow>)}
                                                    className="size-3.5 rounded-none border-slate-400 text-emerald-600 focus:ring-emerald-400"
                                                />
                                            </td>
                                        ))}
                                        <td className="border border-slate-300 p-0">
                                            <input
                                                type="date"
                                                className={dateInputClass}
                                                defaultValue={applicant.date_submitted ?? ''}
                                                onChange={(e) => patch(applicant, { date_submitted: e.target.value || null })}
                                            />
                                        </td>
                                        <td className="border border-slate-300 p-0 text-center">
                                            <select
                                                value={applicant.requirement_status}
                                                onChange={(e) =>
                                                    patch(applicant, {
                                                        requirement_status: e.target.value as RequirementStatus,
                                                    })
                                                }
                                                className={`w-full appearance-none border-0 px-2 py-1.5 text-center text-[11px] font-bold outline-none ${REQUIREMENT_STATUS_STYLES[applicant.requirement_status]}`}
                                            >
                                                {REQUIREMENT_STATUS_OPTIONS.map((option) => (
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
                                                defaultValue={applicant.onboarding_date ?? ''}
                                                onChange={(e) => patch(applicant, { onboarding_date: e.target.value || null })}
                                            />
                                        </td>
                                        {(['onboarding_training_1', 'onboarding_training_2', 'onboarding_training_3', 'onboarding_training_4'] as const).map(
                                            (field) => (
                                                <td key={field} className="border border-slate-300 p-0">
                                                    <input
                                                        type="date"
                                                        className={dateInputClass}
                                                        defaultValue={applicant[field] ?? ''}
                                                        onChange={(e) => patch(applicant, { [field]: e.target.value || null } as Partial<ApplicantRow>)}
                                                    />
                                                </td>
                                            ),
                                        )}
                                        <td className="border border-slate-300 px-1.5 py-1 text-right">
                                            <button
                                                type="button"
                                                onClick={() => setDeleting(applicant)}
                                                className="inline-flex items-center gap-1 rounded px-1.5 py-1 text-[11px] font-medium text-rose-600 hover:bg-rose-50"
                                            >
                                                <Trash2 className="size-3" />
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => !open && setDeleting(null)}
                destructive
                title={`Remove ${deleting?.applicant_name ?? 'applicant'}?`}
                description="This permanently deletes their application and onboarding tracking record."
                confirmLabel="Delete"
                onConfirm={remove}
            />
        </>
    );
}

ApplicantsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
        { title: 'Applicants & Onboarding', href: '/applicants' },
    ],
};
