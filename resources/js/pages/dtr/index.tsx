import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';
import { EmployeeCombobox  } from '@/components/employee-combobox';
import type {EmployeeOption} from '@/components/employee-combobox';

type Log = {
    id: number;
    employee: string | null;
    employee_code: string | null;
    work_date: string | null;
    time_in: string | null;
    time_out: string | null;
    reason: string | null;
    status: string;
};

type Props = {
    logs: Log[];
    employees: EmployeeOption[];
    notice: string;
};

export default function DtrIndex({ logs, employees, notice }: Props) {
    const form = useForm({
        employee_id: employees[0]?.id?.toString() ?? '',
        work_date: '',
        time_in: '08:00',
        time_out: '17:00',
        reason: 'Biometric fingerprint device problem',
    });

    return (
        <>
            <Head title="DTR Fallback" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">DTR Fallback</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Alternative only — used when biometric fingerprint data is missing.
                    </p>
                </div>

                <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <p>{notice}</p>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Add Fallback DTR</h2>
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/dtr');
                        }}
                    >
                        <div className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Employee</span>
                            <EmployeeCombobox
                                employees={employees}
                                value={form.data.employee_id}
                                onChange={(id) => form.setData('employee_id', id)}
                            />
                        </div>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Work Date</span>
                            <input
                                type="date"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.work_date}
                                onChange={(event) => form.setData('work_date', event.target.value)}
                                required
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Time In</span>
                            <input
                                type="time"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.time_in}
                                onChange={(event) => form.setData('time_in', event.target.value)}
                                required
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Time Out</span>
                            <input
                                type="time"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.time_out}
                                onChange={(event) => form.setData('time_out', event.target.value)}
                            />
                        </label>
                        <label className="text-sm md:col-span-2">
                            <span className="mb-1.5 block text-slate-600">Reason (device issue)</span>
                            <input
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.reason}
                                onChange={(event) => form.setData('reason', event.target.value)}
                                required
                            />
                        </label>
                        <div className="md:col-span-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-medium text-white hover:bg-slate-800 disabled:opacity-60"
                            >
                                Save DTR Fallback
                            </button>
                        </div>
                    </form>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Fallback Entries</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                    <th className="px-3 py-2 font-medium">In</th>
                                    <th className="px-3 py-2 font-medium">Out</th>
                                    <th className="px-3 py-2 font-medium">Reason</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log.id} className="border-b border-slate-100 last:border-0">
                                        <td className="px-3 py-3">
                                            <div className="font-medium text-slate-800">{log.employee}</div>
                                            <div className="text-xs text-slate-400">{log.employee_code}</div>
                                        </td>
                                        <td className="px-3 py-3 text-slate-600">{log.work_date}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.time_in}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.time_out}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.reason}</td>
                                        <td className="px-3 py-3">
                                            <span className="rounded-full bg-blue-100 px-2.5 py-1 text-xs font-medium text-blue-700">
                                                {log.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

DtrIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'DTR Fallback', href: '/dtr' },
    ],
};
