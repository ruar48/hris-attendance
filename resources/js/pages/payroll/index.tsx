import { Head, router } from '@inertiajs/react';
import { Play } from 'lucide-react';
import { formatPeso } from '@/lib/money';

type Period = {
    id: number;
    name: string;
    month?: number;
    status: string;
    start_date: string | null;
    end_date: string | null;
    includes_13th_month?: boolean;
    is_thirteenth_month?: boolean;
    half?: number;
    is_kinsena?: boolean;
    latest_run: {
        id: number;
        total_employees: number;
        total_payroll: number;
        net_payroll: number;
        status: string;
    } | null;
};

type Run = {
    id: number;
    period: string | null;
    month?: number;
    half?: number;
    total_employees: number;
    total_payroll: number;
    total_deductions: number;
    net_payroll: number;
    status: string;
    processed_at: string | null;
    includes_13th_month?: boolean;
};

type Props = {
    periods: Period[];
    runs: Run[];
};

export default function PayrollIndex({ periods, runs }: Props) {
    return (
        <>
            <Head title="Payroll" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-slate-900">Payroll</h1>
                    <p className="text-sm text-slate-500">
                        Kinsenas payroll — syncs biometric attendance first, then applies automatic
                        earnings and deductions per cutoff (1–15 and 16–end).
                    </p>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    {periods.map((period) => (
                        <div key={period.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="mb-3 flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900">{period.name}</h2>
                                    <p className="text-sm text-slate-500">
                                        {period.start_date} → {period.end_date}
                                    </p>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {period.is_thirteenth_month || period.includes_13th_month ? (
                                            <span className="inline-flex rounded-full bg-pink-100 px-2.5 py-1 text-xs font-semibold text-pink-700">
                                                13th Month Only
                                            </span>
                                        ) : (
                                            period.is_kinsena && (
                                                <span className="inline-flex rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                    {period.half === 2
                                                        ? '2nd Kinsena (16–end)'
                                                        : '1st Kinsena (1–15)'}
                                                </span>
                                            )
                                        )}
                                    </div>
                                </div>
                                <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
                                    {period.status}
                                </span>
                            </div>
                            {period.latest_run && (
                                <div className="mb-4 space-y-1 text-sm text-slate-600">
                                    <p>Employees: {period.latest_run.total_employees}</p>
                                    <p>Total: {formatPeso(period.latest_run.total_payroll)}</p>
                                    <p>Net: {formatPeso(period.latest_run.net_payroll)}</p>
                                </div>
                            )}
                            <div className="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    onClick={() =>
                                        router.post('/payroll/run', {
                                            payroll_period_id: period.id,
                                        })
                                    }
                                    className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
                                >
                                    <Play className="size-4" />
                                    Run Payroll
                                </button>
                                {period.latest_run && (
                                    <button
                                        type="button"
                                        onClick={() => router.visit(`/payroll/${period.latest_run!.id}`)}
                                        className="rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        View Payslips
                                    </button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Payroll Runs</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Period</th>
                                    <th className="px-3 py-2 font-medium">Employees</th>
                                    <th className="px-3 py-2 font-medium">Total Payroll</th>
                                    <th className="px-3 py-2 font-medium">Deductions</th>
                                    <th className="px-3 py-2 font-medium">Net</th>
                                    <th className="px-3 py-2 font-medium">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                {runs.map((run) => (
                                    <tr key={run.id} className="border-b border-slate-100 last:border-0">
                                        <td className="px-3 py-3">
                                            <button
                                                type="button"
                                                className="font-medium text-emerald-700 hover:underline"
                                                onClick={() => router.visit(`/payroll/${run.id}`)}
                                            >
                                                {run.period}
                                            </button>
                                        </td>
                                        <td className="px-3 py-3 text-slate-600">{run.total_employees}</td>
                                        <td className="px-3 py-3 text-slate-600">{formatPeso(run.total_payroll)}</td>
                                        <td className="px-3 py-3 text-slate-600">{formatPeso(run.total_deductions)}</td>
                                        <td className="px-3 py-3 text-slate-600">{formatPeso(run.net_payroll)}</td>
                                        <td className="px-3 py-3">
                                            <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                {run.status}
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

PayrollIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Payroll', href: '/payroll' },
    ],
};
