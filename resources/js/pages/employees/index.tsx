import { Head, router, useForm } from '@inertiajs/react';
import { Search, UserPlus, X } from 'lucide-react';
import { useState } from 'react';
import { formatPeso } from '@/lib/money';

type EmployeeRow = {
    id: number;
    employee_code: string;
    full_name: string;
    position: string | null;
    department: string | null;
    basic_salary: number;
    biometric_user_id: string | null;
    status: string;
};

type Props = {
    employees: {
        data: EmployeeRow[];
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search: string;
    };
    nextEmployeeCode: string;
};

export default function EmployeesIndex({ employees, filters, nextEmployeeCode }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [showForm, setShowForm] = useState(false);

    const form = useForm({
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
    });

    const inputClass =
        'w-full rounded-xl border border-slate-200 px-3 py-2.5 text-sm outline-none focus:border-emerald-400';

    return (
        <>
            <Head title="Employees" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Employees</h1>
                        <p className="text-sm text-slate-500">
                            Each employee is enrolled with a biometric fingerprint ID.
                        </p>
                    </div>
                    <div className="flex w-full flex-col gap-3 sm:flex-row md:w-auto md:items-end">
                        <form
                            className="relative w-full sm:w-80"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.get('/employees', { search }, { preserveState: true });
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
                            onClick={() => setShowForm((value) => !value)}
                            className="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700"
                        >
                            {showForm ? <X className="size-4" /> : <UserPlus className="size-4" />}
                            {showForm ? 'Close' : 'Add Employee'}
                        </button>
                    </div>
                </div>

                {showForm && (
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-4 flex items-center gap-2">
                            <div className="rounded-xl bg-emerald-100 p-2 text-emerald-600">
                                <UserPlus className="size-4" />
                            </div>
                            <h2 className="text-lg font-semibold text-slate-900">Register New Employee</h2>
                        </div>
                        <form
                            className="grid gap-4 md:grid-cols-2 lg:grid-cols-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                form.post('/employees', {
                                    preserveScroll: true,
                                    onSuccess: () => {
                                        form.reset();
                                        setShowForm(false);
                                    },
                                });
                            }}
                        >
                            <label className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Employee Code</span>
                                <input
                                    className={inputClass}
                                    value={form.data.employee_code}
                                    onChange={(e) => form.setData('employee_code', e.target.value)}
                                    placeholder={`Auto: ${nextEmployeeCode}`}
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
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.email}
                                    </span>
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
                            <div className="flex items-center gap-3 md:col-span-2 lg:col-span-3">
                                <button
                                    type="submit"
                                    disabled={form.processing}
                                    className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                                >
                                    Save Employee
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

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Code</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Position</th>
                                <th className="px-4 py-3 font-medium">Biometric ID</th>
                                <th className="px-4 py-3 font-medium">Basic Salary</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {employees.data.map((employee) => (
                                <tr key={employee.id} className="border-b border-slate-100 last:border-0">
                                    <td className="px-4 py-3 font-medium text-slate-800">{employee.employee_code}</td>
                                    <td className="px-4 py-3 text-slate-700">{employee.full_name}</td>
                                    <td className="px-4 py-3 text-slate-600">{employee.position}</td>
                                    <td className="px-4 py-3 font-mono text-xs text-emerald-700">
                                        {employee.biometric_user_id ?? 'Not enrolled'}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{formatPeso(employee.basic_salary)}</td>
                                    <td className="px-4 py-3">
                                        <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                            {employee.status}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

EmployeesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Employees', href: '/employees' },
    ],
};
