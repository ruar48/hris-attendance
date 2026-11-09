import { Head, router } from '@inertiajs/react';
import { Download, FileDown, LoaderCircle, Pencil } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatPeso } from '@/lib/money';
import { ADJUSTMENT_FIELDS, WAREHOUSE_SUBSIDY_FIELDS } from '@/lib/payslip-fields';
import type { PayslipAmountField } from '@/lib/payslip-fields';
import type { PayslipPdfData } from '@/lib/payslip-pdf';

type Payslip = PayslipPdfData;

type Props = {
    run: {
        id: number;
        period: string | null;
        total_employees: number;
        total_payroll: number;
        total_deductions: number;
        net_payroll: number;
        status: string;
        processed_at: string | null;
    };
    payslips: Payslip[];
};

export default function PayrollShow({ run, payslips }: Props) {
    const [busy, setBusy] = useState<'all' | number | null>(null);
    const [editing, setEditing] = useState<Payslip | null>(null);

    const meta = {
        period: run.period,
        processed_at: run.processed_at,
    };

    const totalAdjustments = payslips.reduce((sum, p) => sum + (p.total_adjustments || 0), 0);
    const totalSubsidy = payslips.reduce((sum, p) => sum + (p.warehouse_subsidy || 0), 0);

    async function handleDownloadAll() {
        setBusy('all');

        try {
            const { downloadPayslipsPdf, allPayslipsFilename } = await import('@/lib/payslip-pdf');
            await downloadPayslipsPdf(payslips, meta, allPayslipsFilename(run.period));
        } finally {
            setBusy(null);
        }
    }

    async function handleDownloadOne(payslip: Payslip) {
        setBusy(payslip.id);

        try {
            const { downloadPayslipsPdf, payslipFilename } = await import('@/lib/payslip-pdf');
            await downloadPayslipsPdf(
                [payslip],
                meta,
                payslipFilename(payslip, run.period),
            );
        } finally {
            setBusy(null);
        }
    }

    return (
        <>
            <Head title={run.period ?? 'Payroll Run'} />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">{run.period}</h1>
                        <p className="text-sm text-slate-500">
                            Processed {run.processed_at ?? '—'} · {run.total_employees} employees · Net{' '}
                            {formatPeso(run.net_payroll)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={handleDownloadAll}
                        disabled={busy !== null || payslips.length === 0}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-blue-600/25 transition hover:bg-blue-700 disabled:opacity-60"
                    >
                        {busy === 'all' ? (
                            <LoaderCircle className="size-4 animate-spin" />
                        ) : (
                            <Download className="size-4" />
                        )}
                        {busy === 'all' ? 'Generating PDF…' : 'Download All Payslips (PDF)'}
                    </button>
                </div>

                <div className="rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Each PDF page includes <strong>Employee copy</strong> (top) and{' '}
                    <strong>HR copy</strong> (bottom), ready to cut and file. Use{' '}
                    <strong>Edit</strong> on a row to enter Adjustments (added to net pay) and the
                    Warehouse Subsidy (listed under net pay).
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <Summary label="Total Payroll" value={formatPeso(run.total_payroll)} />
                    <Summary label="Total Deductions" value={formatPeso(run.total_deductions)} />
                    <Summary label="Total Adjustments" value={formatPeso(totalAdjustments)} />
                    <Summary label="Net Payroll" value={formatPeso(run.net_payroll)} />
                    <Summary label="Warehouse Subsidy" value={formatPeso(totalSubsidy)} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Employee</th>
                                    <th className="px-4 py-3 font-medium">Earnings</th>
                                    <th className="px-4 py-3 font-medium">Deductions</th>
                                    <th className="px-4 py-3 font-medium">Adjustments</th>
                                    <th className="px-4 py-3 font-medium">Net Pay</th>
                                    <th className="px-4 py-3 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payslips.map((payslip) => {
                                    const isThirteenthMonth =
                                        payslip.thirteenth_month > 0 && payslip.basic_pay === 0;

                                    return (
                                    <tr
                                        key={payslip.id}
                                        className="border-b border-slate-100 align-top last:border-0"
                                    >
                                        <td className="px-4 py-4">
                                            <div className="font-medium text-slate-900">
                                                {payslip.employee_name}
                                            </div>
                                            <div className="text-xs text-slate-400">
                                                {payslip.employee_code} · {payslip.position}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            <Line label="Basic" value={payslip.basic_pay} />
                                            <Line label="Holiday" value={payslip.holiday_pay} />
                                            <Line label="Sunday Route" value={payslip.sunday_route} />
                                            <Line label="OT" value={payslip.overtime_pay} />
                                            <Line label="Night diff" value={payslip.night_diff_pay} />
                                            {payslip.thirteenth_month > 0 && (
                                                <Line label="13th Month" value={payslip.thirteenth_month} />
                                            )}
                                            <div className="mt-1 font-medium text-slate-800">
                                                {formatPeso(payslip.total_earnings)}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            {isThirteenthMonth ? (
                                                <span className="text-slate-400">—</span>
                                            ) : (
                                                <>
                                                    <DeductionLine
                                                        label="Late"
                                                        value={payslip.late_deduction}
                                                    />
                                                    <DeductionLine
                                                        label="Undertime"
                                                        value={payslip.undertime_deduction}
                                                    />
                                                    <DeductionLine
                                                        label={
                                                            payslip.absent_days > 0
                                                                ? `Absences (${payslip.absent_days})`
                                                                : 'Absences'
                                                        }
                                                        value={payslip.absence_deduction}
                                                    />
                                                    <DeductionLine
                                                        label="Cash Advance"
                                                        value={payslip.cash_advance_deduction}
                                                    />
                                                    <DeductionLine label="SSS" value={payslip.sss} />
                                                    <DeductionLine
                                                        label="PhilHealth"
                                                        value={payslip.philhealth}
                                                    />
                                                    <DeductionLine
                                                        label="Pag-IBIG"
                                                        value={payslip.pagibig}
                                                    />
                                                    <div className="mt-1 font-medium text-slate-800">
                                                        {formatPeso(payslip.total_deductions)}
                                                    </div>
                                                </>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            {payslip.total_adjustments > 0 ? (
                                                <>
                                                    {ADJUSTMENT_FIELDS.map(({ key, label }) => (
                                                        <Line key={key} label={label} value={payslip[key]} />
                                                    ))}
                                                    <div className="mt-1 font-medium text-violet-700">
                                                        +{formatPeso(payslip.total_adjustments)}
                                                    </div>
                                                </>
                                            ) : (
                                                <span className="text-slate-400">—</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="text-lg font-semibold text-emerald-600">
                                                {formatPeso(payslip.net_pay)}
                                            </div>
                                            {payslip.warehouse_subsidy > 0 && (
                                                <div className="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2.5 py-2 text-slate-600">
                                                    <div className="mb-1 text-[11px] font-semibold tracking-wide text-amber-700 uppercase">
                                                        Warehouse Subsidy
                                                    </div>
                                                    {WAREHOUSE_SUBSIDY_FIELDS.map(({ key, label }) => (
                                                        <Line key={key} label={label} value={payslip[key]} />
                                                    ))}
                                                    <div className="mt-1 text-sm font-medium text-amber-800">
                                                        {formatPeso(payslip.warehouse_subsidy)}
                                                    </div>
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-4">
                                            <div className="flex flex-col gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() => setEditing(payslip)}
                                                    disabled={busy !== null}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 disabled:opacity-60"
                                                    title="Edit adjustments and warehouse subsidy"
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Edit
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDownloadOne(payslip)}
                                                    disabled={busy !== null}
                                                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-300 hover:bg-blue-50 hover:text-blue-700 disabled:opacity-60"
                                                    title="Download employee + HR copies"
                                                >
                                                    {busy === payslip.id ? (
                                                        <LoaderCircle className="size-3.5 animate-spin" />
                                                    ) : (
                                                        <FileDown className="size-3.5" />
                                                    )}
                                                    PDF
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                    {editing && (
                        // Keyed so the form resets whenever another payslip is opened.
                        <AdjustmentsForm
                            key={editing.id}
                            runId={run.id}
                            payslip={editing}
                            onClose={() => setEditing(null)}
                        />
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}

PayrollShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Payroll', href: '/payroll' },
        { title: 'Payslips', href: '#' },
    ],
};

type AmountForm = Record<PayslipAmountField, string>;

const ALL_AMOUNT_FIELDS = [...ADJUSTMENT_FIELDS, ...WAREHOUSE_SUBSIDY_FIELDS];

function toForm(payslip: Payslip): AmountForm {
    return Object.fromEntries(
        ALL_AMOUNT_FIELDS.map(({ key }) => [key, payslip[key] ? String(payslip[key]) : '']),
    ) as AmountForm;
}

function sumOf(form: AmountForm, fields: ReadonlyArray<{ key: PayslipAmountField }>): number {
    return fields.reduce((sum, { key }) => sum + (Number(form[key]) || 0), 0);
}

function AdjustmentsForm({
    runId,
    payslip,
    onClose,
}: {
    runId: number;
    payslip: Payslip;
    onClose: () => void;
}) {
    const [form, setForm] = useState<AmountForm>(() => toForm(payslip));
    const [errors, setErrors] = useState<Partial<Record<PayslipAmountField, string>>>({});
    const [saving, setSaving] = useState(false);

    const adjustments = sumOf(form, ADJUSTMENT_FIELDS);
    const subsidy = sumOf(form, WAREHOUSE_SUBSIDY_FIELDS);
    const netPay = payslip.total_earnings - payslip.total_deductions + adjustments;

    function submit(event: FormEvent) {
        event.preventDefault();
        setSaving(true);

        const payload = Object.fromEntries(
            ALL_AMOUNT_FIELDS.map(({ key }) => [key, form[key] === '' ? 0 : Number(form[key])]),
        );

        router.patch(`/payroll/${runId}/payslips/${payslip.id}/adjustments`, payload, {
            preserveScroll: true,
            onSuccess: () => onClose(),
            onError: (errs) => {
                setErrors(errs as Partial<Record<PayslipAmountField, string>>);
                toast.error('Please check the amounts entered.');
            },
            onFinish: () => setSaving(false),
        });
    }

    const renderField = ({ key, label }: { key: PayslipAmountField; label: string }) => (
        <label key={key} className="flex flex-col gap-1 text-xs font-medium text-slate-600">
            {label}
            <input
                type="number"
                inputMode="decimal"
                min={0}
                step="0.01"
                value={form[key]}
                placeholder="0.00"
                onChange={(e) => setForm((prev) => ({ ...prev, [key]: e.target.value }))}
                className="rounded-lg border border-slate-200 px-3 py-2 text-sm font-normal text-slate-900 outline-none focus:border-blue-400 focus:ring-2 focus:ring-blue-100"
            />
            {errors[key] && <span className="text-[11px] text-red-600">{errors[key]}</span>}
        </label>
    );

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <DialogHeader>
                <DialogTitle>Adjustments &amp; Warehouse Subsidy</DialogTitle>
                <DialogDescription>
                    {payslip.employee_name} · {payslip.employee_code}
                </DialogDescription>
            </DialogHeader>

            <fieldset className="flex flex-col gap-3">
                <legend className="mb-2 text-xs font-semibold tracking-wide text-violet-700 uppercase">
                    Adjustments{' '}
                    <span className="font-normal text-slate-400 normal-case">— added to net pay</span>
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">{ADJUSTMENT_FIELDS.map(renderField)}</div>
            </fieldset>

            <div className="flex items-center justify-between rounded-xl border border-blue-100 bg-blue-50 px-4 py-3 text-sm">
                <span className="text-blue-800">Net Pay</span>
                <span className="font-semibold text-blue-900">{formatPeso(netPay)}</span>
            </div>

            <fieldset className="flex flex-col gap-3">
                <legend className="mb-2 text-xs font-semibold tracking-wide text-amber-700 uppercase">
                    Warehouse Subsidy{' '}
                    <span className="font-normal text-slate-400 normal-case">— listed under net pay</span>
                </legend>
                <div className="grid gap-3 sm:grid-cols-2">{WAREHOUSE_SUBSIDY_FIELDS.map(renderField)}</div>
                <div className="flex justify-between text-sm text-slate-600">
                    <span>Total Warehouse Subsidy</span>
                    <span className="font-semibold text-amber-800">{formatPeso(subsidy)}</span>
                </div>
            </fieldset>

            <DialogFooter>
                <button
                    type="button"
                    onClick={onClose}
                    className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    disabled={saving}
                    className="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                    {saving && <LoaderCircle className="size-4 animate-spin" />}
                    Save
                </button>
            </DialogFooter>
        </form>
    );
}

function Summary({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-2 text-xl font-semibold text-slate-900">{value}</p>
        </div>
    );
}

function Line({ label, value }: { label: string; value: number }) {
    if (!value) {
        return null;
    }

    return (
        <div className="flex justify-between gap-4 text-xs">
            <span>{label}</span>
            <span>{formatPeso(value)}</span>
        </div>
    );
}

function DeductionLine({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex justify-between gap-4 text-xs">
            <span>{label}</span>
            <span>{formatPeso(value)}</span>
        </div>
    );
}
