import { Head, useForm } from '@inertiajs/react';
import { Banknote, Pencil, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { EmployeeCombobox  } from '@/components/employee-combobox';
import type {EmployeeOption} from '@/components/employee-combobox';
import { formatPeso } from '@/lib/money';

type Advance = {
    id: number;
    employee_id: number;
    employee_name: string | null;
    employee_code: string | null;
    amount: number;
    balance: number;
    deduction_per_payroll: number | null;
    effective_deduction: number;
    amount_locked: boolean;
    released_at: string | null;
    status: string;
    notes: string | null;
};

type Props = {
    advances: Advance[];
    employees: EmployeeOption[];
    defaultDeduction: number;
    autoDeduct: boolean;
};

export default function CashAdvancesIndex({
    advances,
    employees,
    defaultDeduction,
    autoDeduct,
}: Props) {
    const [editing, setEditing] = useState<Advance | null>(null);

    const blankForm = {
        employee_id: employees[0]?.id?.toString() ?? '',
        amount: '1000',
        deduction_per_payroll: '',
        released_at: new Date().toISOString().slice(0, 10),
        notes: '',
    };

    const form = useForm({ ...blankForm });

    // Preview how the advance will actually be paid off. Once payroll has
    // collected some of it, what's left to clear is the balance, not the amount.
    const owed =
        editing && editing.amount_locked ? editing.balance : Number(form.data.amount) || 0;
    const instalment = Number(form.data.deduction_per_payroll) || defaultDeduction;
    const runs = instalment > 0 ? Math.ceil(owed / instalment) : 0;

    // Already collected by payroll — this part cannot be edited away.
    const collected = editing ? Math.round((editing.amount - editing.balance) * 100) / 100 : 0;

    const cancelEdit = () => {
        form.setData({ ...blankForm });
        form.clearErrors();
        setEditing(null);
    };

    const startEdit = (advance: Advance) => {
        form.setData({
            employee_id: String(advance.employee_id),
            amount: String(advance.amount),
            deduction_per_payroll:
                advance.deduction_per_payroll === null ? '' : String(advance.deduction_per_payroll),
            released_at: advance.released_at ?? new Date().toISOString().slice(0, 10),
            notes: advance.notes ?? '',
        });
        form.clearErrors();
        setEditing(advance);
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const options = {
            preserveScroll: true,
            onError: (errors: Record<string, string>) => {
                const messages = Object.values(errors);

                toast.error(
                    messages.length === 1 ? messages[0] : `Please fix ${messages.length} fields.`,
                );
            },
        };

        if (editing) {
            form.put(`/cash-advances/${editing.id}`, { ...options, onSuccess: () => cancelEdit() });
        } else {
            form.post('/cash-advances', { ...options, onSuccess: () => form.reset('notes') });
        }
    };

    return (
        <>
            <Head title="Cash Advances" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Cash Advances</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        {autoDeduct ? (
                            <>
                                Payroll deducts <strong>{formatPeso(defaultDeduction)}</strong> per run by
                                default. Set a different amount on an advance to override it.
                            </>
                        ) : (
                            <>
                                Auto-deduction is currently <strong>off</strong> in Payroll Settings, so
                                these balances will not be collected.
                            </>
                        )}
                    </p>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center gap-2">
                        <div className="rounded-xl bg-violet-100 p-2 text-violet-600">
                            {editing ? <Pencil className="size-4" /> : <Banknote className="size-4" />}
                        </div>
                        <h2 className="text-lg font-semibold text-slate-900">
                            {editing
                                ? `Edit advance — ${editing.employee_name ?? 'employee'}`
                                : 'Add Cash Advance'}
                        </h2>
                    </div>
                    {editing && (
                        <div className="mb-4 grid gap-3 rounded-xl bg-slate-50 px-4 py-3 text-sm sm:grid-cols-3">
                            <div>
                                <span className="block text-xs text-slate-500">Borrower</span>
                                <span className="font-medium text-slate-800">
                                    {editing.employee_code} — {editing.employee_name}
                                </span>
                            </div>
                            {editing.amount_locked && (
                                <div>
                                    <span className="block text-xs text-slate-500">Amount borrowed</span>
                                    <span className="font-medium text-slate-800">
                                        {formatPeso(editing.amount)}
                                    </span>
                                </div>
                            )}
                            <div>
                                <span className="block text-xs text-slate-500">
                                    Balance{collected > 0 ? ` (${formatPeso(collected)} collected)` : ''}
                                </span>
                                <span className="font-medium text-violet-700">
                                    {formatPeso(editing.balance)}
                                </span>
                            </div>
                            <p className="text-xs text-slate-500 sm:col-span-3">
                                {editing.amount_locked ? (
                                    <>
                                        Payroll already collected {formatPeso(collected)} on this advance, so
                                        the amount is locked. Only the per-payroll deduction can still be
                                        changed.
                                    </>
                                ) : (
                                    <>
                                        Nothing has been deducted yet, so the amount can still be corrected.
                                        The borrower cannot be changed.
                                    </>
                                )}
                            </p>
                        </div>
                    )}
                    <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                        {!editing && (
                            <div className="text-sm">
                                <span className="mb-1.5 block text-slate-600">Employee</span>
                                <EmployeeCombobox
                                    employees={employees}
                                    value={form.data.employee_id}
                                    onChange={(id) => form.setData('employee_id', id)}
                                />
                                {form.errors.employee_id && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.employee_id}
                                    </span>
                                )}
                            </div>
                        )}
                        {(!editing || !editing.amount_locked) && (
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
                                {form.errors.amount && (
                                    <span className="mt-1 block text-xs text-rose-600">
                                        {form.errors.amount}
                                    </span>
                                )}
                            </label>
                        )}
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Deduct per payroll (₱)</span>
                            <input
                                type="number"
                                min={1}
                                step="0.01"
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.deduction_per_payroll}
                                onChange={(e) => form.setData('deduction_per_payroll', e.target.value)}
                                placeholder={`Default: ${formatPeso(defaultDeduction)}`}
                            />
                            {form.errors.deduction_per_payroll ? (
                                <span className="mt-1 block text-xs text-rose-600">
                                    {form.errors.deduction_per_payroll}
                                </span>
                            ) : (
                                <span className="mt-1 block text-xs text-slate-500">
                                    {owed > 0 && instalment > 0
                                        ? `${formatPeso(instalment)} every payroll — about ${runs} run${runs === 1 ? '' : 's'} to clear${editing ? ' the balance' : ''}.`
                                        : 'Leave blank to use the payroll-settings default.'}
                                </span>
                            )}
                        </label>
                        {!editing && (
                            <>
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
                            </>
                        )}
                        <div className="flex items-center gap-3 md:col-span-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-60"
                            >
                                {editing ? 'Save Changes' : 'Save Cash Advance'}
                            </button>
                            {editing && (
                                <button
                                    type="button"
                                    onClick={cancelEdit}
                                    className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 hover:bg-slate-50"
                                >
                                    <X className="size-4" />
                                    Cancel
                                </button>
                            )}
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
                                <th className="px-4 py-3 font-medium">Per Payroll</th>
                                <th className="px-4 py-3 font-medium">Released</th>
                                <th className="px-4 py-3 font-medium">Status</th>
                                <th className="px-4 py-3 font-medium">Notes</th>
                                <th className="px-4 py-3 text-right font-medium">Actions</th>
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
                                    <td className="px-4 py-3">
                                        <span className="text-slate-700">
                                            {formatPeso(advance.effective_deduction)}
                                        </span>
                                        {advance.deduction_per_payroll === null && (
                                            <span className="block text-xs text-slate-400">default</span>
                                        )}
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
                                    <td className="px-4 py-3">
                                        <div className="flex justify-end">
                                            <button
                                                type="button"
                                                onClick={() => startEdit(advance)}
                                                className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-100"
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </button>
                                        </div>
                                    </td>
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
