import { Head, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
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
};

export default function EmployeesIndex({ employees, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

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
                    <form
                        className="relative w-full md:w-80"
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
                </div>

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
