import { Head, Link, router, useForm } from '@inertiajs/react';
import { Archive, ArchiveRestore, Pencil, Search, UserPlus, Users, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { formatPeso } from '@/lib/money';

type EmployeeRow = {
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
    archived: boolean;
};

type Props = {
    employees: {
        data: EmployeeRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
        current_page: number;
        last_page: number;
        per_page: number;
        from: number | null;
        to: number | null;
        total: number;
    };
    filters: {
        search: string;
        per_page: number;
        archived: boolean;
    };
    perPageOptions: number[];
    nextEmployeeCode: string;
    archivedCount: number;
};

const emptyForm = {
    employee_code: '',
    first_name: '',
    last_name: '',
    email: '',
    position: '',
    department: '',
    basic_salary: '0',
    daily_rate: '0',
    sunday_route_rate: '0',
    hourly_rate: '0',
    biometric_user_id: '',
    hire_date: new Date().toISOString().slice(0, 10),
    status: 'active',
};

export default function EmployeesIndex({
    employees,
    filters,
    perPageOptions,
    nextEmployeeCode,
    archivedCount,
}: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState<EmployeeRow | null>(null);
    const [archiving, setArchiving] = useState<EmployeeRow | null>(null);
    const [lastWorkingDay, setLastWorkingDay] = useState(() => new Date().toISOString().slice(0, 10));

    const form = useForm({ ...emptyForm });

    const inputClass =
        'w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400';

    const closeForm = () => {
        form.reset();
        form.clearErrors();
        setEditing(null);
        setShowForm(false);
    };

    const startCreate = () => {
        form.setData({ ...emptyForm });
        form.clearErrors();
        setEditing(null);
        setShowForm(true);
    };

    const startEdit = (employee: EmployeeRow) => {
        form.setData({
            employee_code: employee.employee_code,
            first_name: employee.first_name,
            last_name: employee.last_name,
            email: employee.email ?? '',
            position: employee.position ?? '',
            department: employee.department ?? '',
            basic_salary: String(employee.basic_salary),
            daily_rate: String(employee.daily_rate),
            sunday_route_rate: String(employee.sunday_route_rate),
            hourly_rate: String(employee.hourly_rate),
            biometric_user_id: employee.biometric_user_id ?? '',
            hire_date: employee.hire_date ?? '',
            status: employee.status,
        });
        form.clearErrors();
        setEditing(employee);
        setShowForm(true);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => closeForm(),
            onError: (errors: Record<string, string>) => {
                const count = Object.keys(errors).length;

                toast.error(
                    count === 1
                        ? Object.values(errors)[0]
                        : `Please fix ${count} fields before saving.`,
                );
            },
        };

        if (editing) {
            form.put(`/employees/${editing.id}`, options);
        } else {
            form.post('/employees', options);
        }
    };

    const archive = () => {
        if (!archiving) {
            return;
        }

        router.delete(`/employees/${archiving.id}`, {
            data: { last_working_day: lastWorkingDay },
            preserveScroll: true,
            onFinish: () => setArchiving(null),
        });
    };

    const openArchive = (employee: EmployeeRow) => {
        setLastWorkingDay(new Date().toISOString().slice(0, 10));
        setArchiving(employee);
    };

    const restore = (employee: EmployeeRow) => {
        router.put(`/employees/${employee.id}/restore`, {}, { preserveScroll: true });
    };

    const reload = (params: Record<string, string | number | boolean>) =>
        router.get(
            '/employees',
            { search: filters.search, per_page: filters.per_page, archived: filters.archived, ...params },
            { preserveState: true, preserveScroll: true },
        );

    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">
                            {filters.archived ? 'Archived Employees' : 'Employees'}
                        </h1>
                        <p className="text-sm text-slate-500">
                            {filters.archived
                                ? 'Archived employees are excluded from payroll. Restore them to bring them back.'
                                : 'Each employee is enrolled with a biometric fingerprint ID.'}
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:flex-row md:w-auto md:items-end">
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
                                placeholder="Search name, code, biometric ID"
                                className="w-full rounded-xl border border-slate-200 bg-white py-2.5 pr-3 pl-10 text-sm outline-none focus:border-emerald-400"
                            />
                        </form>
                        <button
                            type="button"
                            onClick={() => reload({ archived: !filters.archived, page: 1 })}
                            className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            {filters.archived ? <Users className="size-4" /> : <Archive className="size-4" />}
                            {filters.archived ? 'Active' : `Archived (${archivedCount})`}
                        </button>
                        {!filters.archived && (
                            <button
                                type="button"
                                onClick={() => (showForm && !editing ? closeForm() : startCreate())}
                                className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                {showForm && !editing ? <X className="size-4" /> : <UserPlus className="size-4" />}
                                {showForm && !editing ? 'Close' : 'Add Employee'}
                            </button>
                        )}
                    </div>
                </div>

                {showForm && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <div className="rounded-xl bg-emerald-100 p-2 text-emerald-600">
                                {editing ? <Pencil className="size-4" /> : <UserPlus className="size-4" />}
                            </div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                {editing ? `Edit ${editing.full_name}` : 'Register New Employee'}
                            </h2>
                        </div>
                        <form className="grid gap-4 md:grid-cols-2 lg:grid-cols-3" onSubmit={submit}>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Employee Code</span>
                                <input
                                    className={inputClass}
                                    value={form.data.employee_code}
                                    onChange={(e) => form.setData('employee_code', e.target.value)}
                                    placeholder={editing ? '' : `Auto: ${nextEmployeeCode}`}
                                />
                                {form.errors.employee_code && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.employee_code}
                                    </span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">First Name *</span>
                                <input
                                    className={inputClass}
                                    value={form.data.first_name}
                                    onChange={(e) => form.setData('first_name', e.target.value)}
                                    required
                                />
                                {form.errors.first_name && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.first_name}
                                    </span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Last Name *</span>
                                <input
                                    className={inputClass}
                                    value={form.data.last_name}
                                    onChange={(e) => form.setData('last_name', e.target.value)}
                                    required
                                />
                                {form.errors.last_name && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.last_name}
                                    </span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Email</span>
                                <input
                                    type="email"
                                    className={inputClass}
                                    value={form.data.email}
                                    onChange={(e) => form.setData('email', e.target.value)}
                                />
                                {form.errors.email && (
                                    <span className="mt-1 block text-xs text-rose-600">{form.errors.email}</span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Position</span>
                                <input
                                    className={inputClass}
                                    value={form.data.position}
                                    onChange={(e) => form.setData('position', e.target.value)}
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Department</span>
                                <input
                                    className={inputClass}
                                    value={form.data.department}
                                    onChange={(e) => form.setData('department', e.target.value)}
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Basic Salary (₱) *</span>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={inputClass}
                                    value={form.data.basic_salary}
                                    onChange={(e) => form.setData('basic_salary', e.target.value)}
                                    required
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Daily Rate (₱) *</span>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={inputClass}
                                    value={form.data.daily_rate}
                                    onChange={(e) => form.setData('daily_rate', e.target.value)}
                                    required
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Sunday Route Rate (₱)</span>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={inputClass}
                                    value={form.data.sunday_route_rate}
                                    onChange={(e) => form.setData('sunday_route_rate', e.target.value)}
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Hourly Rate (₱) *</span>
                                <input
                                    type="number"
                                    min={0}
                                    step="0.01"
                                    className={inputClass}
                                    value={form.data.hourly_rate}
                                    onChange={(e) => form.setData('hourly_rate', e.target.value)}
                                    required
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Biometric User ID</span>
                                <input
                                    className={inputClass}
                                    value={form.data.biometric_user_id}
                                    onChange={(e) => form.setData('biometric_user_id', e.target.value)}
                                    placeholder="e.g. BIO-129"
                                />
                                {form.errors.biometric_user_id && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.biometric_user_id}
                                    </span>
                                )}
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Hire Date</span>
                                <input
                                    type="date"
                                    className={inputClass}
                                    value={form.data.hire_date}
                                    onChange={(e) => form.setData('hire_date', e.target.value)}
                                />
                            </label>
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Status</span>
                                <select
                                    className={inputClass}
                                    value={form.data.status}
                                    onChange={(e) => form.setData('status', e.target.value)}
                                >
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </label>
                            <div className="flex items-center gap-3 md:col-span-2 lg:col-span-3">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    {editing ? 'Save Changes' : 'Save Employee'}
                                </button>
                                <button
                                    type="button"
                                    onClick={closeForm}
                                    className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </section>
                )}

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Code</th>
                                    <th className="px-4 py-3 font-medium">Name</th>
                                    <th className="px-4 py-3 font-medium">Position</th>
                                    <th className="px-4 py-3 font-medium">Biometric ID</th>
                                    <th className="px-4 py-3 font-medium">Basic Salary</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {employees.data.length === 0 && (
                                    <tr>
                                        <td colSpan={7} className="px-4 py-10 text-center text-sm text-slate-500">
                                            No {filters.archived ? 'archived ' : ''}employees found
                                            {filters.search ? ` for “${filters.search}”` : ''}.
                                        </td>
                                    </tr>
                                )}
                                {employees.data.map((employee) => (
                                    <tr key={employee.id} className="border-b border-slate-100 last:border-0">
                                        <td className="px-4 py-3 font-medium text-slate-800">
                                            {employee.employee_code}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {employee.full_name}
                                            {employee.archived && employee.last_working_day && (
                                                <span className="block text-xs text-slate-400">
                                                    Paid through {employee.last_working_day}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{employee.position}</td>
                                        <td className="px-4 py-3 font-mono text-xs text-emerald-700">
                                            {employee.biometric_user_id ?? 'Not enrolled'}
                                        </td>
                                        <td className="px-4 py-3 text-slate-700">
                                            {formatPeso(employee.basic_salary)}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={
                                                    employee.archived
                                                        ? 'rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-600'
                                                        : employee.status === 'active'
                                                          ? 'rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700'
                                                          : 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700'
                                                }
                                            >
                                                {employee.archived ? 'archived' : employee.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center justify-end gap-1">
                                                {employee.archived ? (
                                                    <button
                                                        type="button"
                                                        onClick={() => restore(employee)}
                                                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-emerald-700 hover:bg-emerald-50"
                                                    >
                                                        <ArchiveRestore className="size-3.5" />
                                                        Restore
                                                    </button>
                                                ) : (
                                                    <>
                                                        <button
                                                            type="button"
                                                            onClick={() => startEdit(employee)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
                                                        >
                                                            <Pencil className="size-3.5" />
                                                            Edit
                                                        </button>
                                                        <button
                                                            type="button"
                                                            onClick={() => openArchive(employee)}
                                                            className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50"
                                                        >
                                                            <Archive className="size-3.5" />
                                                            Archive
                                                        </button>
                                                    </>
                                                )}
                                            </div>
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

            <ConfirmDialog
                open={archiving !== null}
                onOpenChange={(open) => !open && setArchiving(null)}
                destructive
                title={`Archive ${archiving?.full_name ?? 'employee'}?`}
                description={
                    <>
                        They keep earning up to their last working day, then drop off later payroll runs.
                        Their history is kept and you can restore them any time from the{' '}
                        <span className="font-medium">Archived</span> tab.
                    </>
                }
                confirmLabel="Archive"
                onConfirm={archive}
            >
                <label className="text-sm">
                    <span className="mb-1.5 block font-medium text-slate-700">Last working day</span>
                    <input
                        type="date"
                        value={lastWorkingDay}
                        onChange={(event) => setLastWorkingDay(event.target.value)}
                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                    />
                    <span className="mt-1.5 block text-xs text-slate-500">
                        Basic pay is prorated to this date — someone who worked 10 of 15 days is paid
                        two-thirds of the period.
                    </span>
                </label>
            </ConfirmDialog>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
    ],
};
