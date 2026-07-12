import { Head } from '@inertiajs/react';
import { Download, FileDown, LoaderCircle } from 'lucide-react';
import { useState } from 'react';
import { formatPeso } from '@/lib/money';
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

    const meta = {
        period: run.period,
        processed_at: run.processed_at,
    };

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
                    <strong>HR copy</strong> (bottom), ready to cut and file.
                </div>

                <div className="grid gap-4 sm:grid-cols-3">
                    <Summary label="Total Payroll" value={formatPeso(run.total_payroll)} />
                    <Summary label="Total Deductions" value={formatPeso(run.total_deductions)} />
                    <Summary label="Net Payroll" value={formatPeso(run.net_payroll)} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Employee</th>
                                    <th className="px-4 py-3 font-medium">Earnings</th>
                                    <th className="px-4 py-3 font-medium">Deductions</th>
                                    <th className="px-4 py-3 font-medium">Net Pay</th>
                                    <th className="px-4 py-3 font-medium">PDF</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payslips.map((payslip) => (
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
                                            {payslip.thirteenth_month > 0 && (
                                                <Line label="13th Month" value={payslip.thirteenth_month} />
                                            )}
                                            <div className="mt-1 font-medium text-slate-800">
                                                {formatPeso(payslip.total_earnings)}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-slate-600">
                                            <Line label="Late" value={payslip.late_deduction} />
                                            <Line
                                                label="Undertime"
                                                value={payslip.undertime_deduction}
                                            />
                                            <Line
                                                label="Cash Advance"
                                                value={payslip.cash_advance_deduction}
                                            />
                                            <Line label="SSS" value={payslip.sss} />
                                            <Line label="PhilHealth" value={payslip.philhealth} />
                                            <Line label="Pag-IBIG" value={payslip.pagibig} />
                                            <div className="mt-1 font-medium text-slate-800">
                                                {formatPeso(payslip.total_deductions)}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-lg font-semibold text-emerald-600">
                                            {formatPeso(payslip.net_pay)}
                                        </td>
                                        <td className="px-4 py-4">
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

PayrollShow.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Payroll', href: '/payroll' },
        { title: 'Payslips', href: '#' },
    ],
};

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
