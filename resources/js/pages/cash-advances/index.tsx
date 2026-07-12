import { Head, useForm } from '@inertiajs/react';
import { Banknote } from 'lucide-react';
import { formatPeso } from '@/lib/money';

type EmployeeOption = {
    id: number;
    employee_code: string;
    first_name: string;
    last_name: string;
};

type Advance = {
    id: number;
    employee_name: string | null;
    employee_code: string | null;
    amount: number;
    balance: number;
    released_at: string | null;
    status: string;
    notes: string | null;
};

type Props = {
    advances: Advance[];
    employees: EmployeeOption[];
};

export default function CashAdvancesIndex({ advances, employees }: Props) {
    const form = useForm({
        employee_id: employees[0]?.id?.toString() ?? '',
        amount: '1000',
        released_at: new Date().toISOString().slice(0, 10),
        notes: '',
    });

    return (
        <>
            <Head title="Cash Advances" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Cash Advances</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        Record advances here. Payroll auto-deducts active balances using the max per
                        period in Payroll Settings.
                    </p>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center gap-2">
                        <div className="rounded-xl bg-violet-100 p-2 text-violet-600">
                            <Banknote className="size-4" />
                        </div>
                        <h2 className="text-lg font-semibold text-slate-900">Add Cash Advance</h2>
                    </div>
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/cash-advances', { onSuccess: () => form.reset('notes') });
                        }}
                    >
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Employee</span>
                            <select
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.employee_id}
                                onChange={(e) => form.setData('employee_id', e.target.value)}
                            >
                                {employees.map((employee) => (
                                    <option key={employee.id} value={employee.id}>
                                        {employee.employee_code} — {employee.first_name}{' '}
                                        {employee.last_name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Amount (₱)</span>
                            <input
                                type="number"
                                min={1}
                                step="0.01"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.amount}
                                onChange={(e) => form.setData('amount', e.target.value)}
                                required
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Released Date</span>
                            <input
                                type="date"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.released_at}
                                onChange={(e) => form.setData('released_at', e.target.value)}
                                required
                            />
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Notes</span>
                            <input
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.notes}
                                onChange={(e) => form.setData('notes', e.target.value)}
                                placeholder="Optional"
                            />
                        </label>
                        <div className="md:col-span-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-60"
                            >
                                Save Cash Advance
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">Employee</th>
                                <th className="px-4 py-3 font-medium">Amount</th>
                                <th className="px-4 py-3 font-medium">Balance</th>
                                <th className="px-4 py-3 font-medium">Released</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {advances.map((advance) => (
                                <tr key={advance.id} className="border-b border-slate-100 last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-800">
                                            {advance.employee_name}
                                        </div>
                                        <div className="text-xs text-slate-400">
                                            {advance.employee_code}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">{formatPeso(advance.amount)}</td>
                                    <td className="px-4 py-3 font-semibold text-violet-700">
                                        {formatPeso(advance.balance)}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">{advance.released_at}</td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={
                                                advance.status === 'active'
                                                    ? 'rounded-full bg-amber-100 px-2.5 py-1 text-xs font-medium text-amber-700'
                                                    : 'rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700'
                                            }
                                        >
                                            {advance.status}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3 text-slate-500">{advance.notes ?? '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>
        </>
    );
}

CashAdvancesIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Cash Advances', href: '/cash-advances' },
    ],
};
