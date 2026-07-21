import { Head, router } from '@inertiajs/react';
import { CalendarDays, Eye, Play, Users, Wallet } from 'lucide-react';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type PeriodType = '1st_kinsena' | '2nd_kinsena' | '13th_month';

type Period = {
    id: number;
    name: string;
    year: number;
    month: number;
    status: string;
    start_date: string | null;
    end_date: string | null;
    cutoff_date: string | null;
    payday: string | null;
    is_thirteenth_month: boolean;
    half: number;
    type: PeriodType;
    latest_run: {
        id: number;
        total_employees: number;
        total_payroll: number;
        total_deductions: number;
        net_payroll: number;
        status: string;
        processed_at: string | null;
    } | null;
};

type Props = {
    filters: {
        year: number;
    };
    years: number[];
    summary: {
        cutoffs: number;
        completed: number;
        pending: number;
        total_payroll: number;
        net_payroll: number;
    };
    periods: Period[];
};

const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export default function PayrollIndex({ filters, years, summary, periods }: Props) {
    function changeYear(year: number) {
        router.get('/payroll', { year }, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Payroll" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Payroll</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Kinsenas cutoffs for {filters.year}. Biometric attendance syncs first,
                            then earnings and deductions are applied automatically.
                        </p>
                    </div>
                    <label className="flex w-full flex-col gap-1.5 text-sm md:w-44">
                        <span className="font-medium text-slate-700">Year</span>
                        <select
                            value={filters.year}
                            onChange={(e) => changeYear(Number(e.target.value))}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-emerald-400"
                        >
                            {years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        icon={CalendarDays}
                        label="Cutoffs"
                        value={String(summary.cutoffs)}
                        hint={`${summary.completed} completed · ${summary.pending} pending`}
                        tone="blue"
                    />
                    <SummaryCard
                        icon={Users}
                        label="Latest headcount"
                        value={
                            periods.find((p) => p.latest_run)?.latest_run?.total_employees.toString() ??
                            '—'
                        }
                        hint="From most recent processed cutoff"
                        tone="violet"
                    />
                    <SummaryCard
                        icon={Wallet}
                        label="Total payroll"
                        value={formatPeso(summary.total_payroll)}
                        hint={`${filters.year} gross (completed cutoffs)`}
                        tone="emerald"
                    />
                    <SummaryCard
                        icon={Wallet}
                        label="Net payroll"
                        value={formatPeso(summary.net_payroll)}
                        hint={`${filters.year} after deductions`}
                        tone="cyan"
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-semibold text-slate-900">
                            {filters.year} Kinsena Schedule
                        </h2>
                        <p className="text-sm text-slate-500">
                            1st kinsena = days 1–15 · 2nd kinsena = days 16–end · 13th month is a
                            separate slip after December 2nd kinsena
                        </p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Month</th>
                                    <th className="px-4 py-3 font-medium">Cutoff</th>
                                    <th className="px-4 py-3 font-medium">Coverage</th>
                                    <th className="px-4 py-3 font-medium">Payday</th>
                                    <th className="px-4 py-3 font-medium text-right">Employees</th>
                                    <th className="px-4 py-3 font-medium text-right">Gross</th>
                                    <th className="px-4 py-3 font-medium text-right">Deductions</th>
                                    <th className="px-4 py-3 font-medium text-right">Net</th>
                                    <th className="px-4 py-3 font-medium">Status</th>
                                    <th className="px-4 py-3 font-medium text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={10}
                                            className="px-4 py-12 text-center text-slate-500"
                                        >
                                            No payroll cutoffs found for {filters.year}.
                                        </td>
                                    </tr>
                                ) : (
                                    periods.map((period) => (
                                        <tr
                                            key={period.id}
                                            className="border-b border-slate-100 last:border-0 hover:bg-slate-50/60"
                                        >
                                            <td className="px-4 py-3 font-medium text-slate-900">
                                                {monthNames[period.month - 1] ?? period.month}
                                            </td>
                                            <td className="px-4 py-3">
                                                <TypeBadge type={period.type} />
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                <span className="font-mono text-xs">
                                                    {period.start_date} → {period.end_date}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3 text-slate-600">
                                                {period.payday ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {period.latest_run?.total_employees ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {period.latest_run
                                                    ? formatPeso(period.latest_run.total_payroll)
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right text-slate-700">
                                                {period.latest_run
                                                    ? formatPeso(period.latest_run.total_deductions)
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-right font-medium text-emerald-700">
                                                {period.latest_run
                                                    ? formatPeso(period.latest_run.net_payroll)
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge status={period.status} />
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex justify-end gap-2">
                                                    <button
                                                        type="button"
                                                        disabled={period.status === 'completed'}
                                                        title={
                                                            period.status === 'completed'
                                                                ? 'Already processed — a cutoff can only be run once'
                                                                : 'Run payroll for this cutoff'
                                                        }
                                                        onClick={() =>
                                                            router.post('/payroll/run', {
                                                                payroll_period_id: period.id,
                                                            })
                                                        }
                                                        className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-200 disabled:text-slate-400"
                                                    >
                                                        <Play className="size-3.5" />
                                                        {period.status === 'completed' ? 'Processed' : 'Run'}
                                                    </button>
                                                    {period.latest_run && (
                                                        <button
                                                            type="button"
                                                            title="View payslips"
                                                            onClick={() =>
                                                                router.visit(
                                                                    `/payroll/${period.latest_run!.id}`,
                                                                )
                                                            }
                                                            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            <Eye className="size-3.5" />
                                                            Payslips
                                                        </button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                )}
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

function SummaryCard({
    icon: Icon,
    label,
    value,
    hint,
    tone,
}: {
    icon: typeof CalendarDays;
    label: string;
    value: string;
    hint: string;
    tone: 'blue' | 'violet' | 'emerald' | 'cyan';
}) {
    const tones = {
        blue: 'bg-blue-100 text-blue-600',
        violet: 'bg-violet-100 text-violet-600',
        emerald: 'bg-emerald-100 text-emerald-600',
        cyan: 'bg-cyan-100 text-cyan-600',
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start gap-3">
                <div className={cn('rounded-xl p-2.5', tones[tone])}>
                    <Icon className="size-5" />
                </div>
                <div className="min-w-0">
                    <p className="text-sm text-slate-500">{label}</p>
                    <p className="mt-1 truncate text-xl font-semibold text-slate-900">{value}</p>
                    <p className="mt-1 text-xs text-slate-400">{hint}</p>
                </div>
            </div>
        </div>
    );
}

function TypeBadge({ type }: { type: PeriodType }) {
    const styles: Record<PeriodType, string> = {
        '1st_kinsena': 'bg-emerald-100 text-emerald-700',
        '2nd_kinsena': 'bg-teal-100 text-teal-700',
        '13th_month': 'bg-pink-100 text-pink-700',
    };

    const labels: Record<PeriodType, string> = {
        '1st_kinsena': '1st Kinsena',
        '2nd_kinsena': '2nd Kinsena',
        '13th_month': '13th Month',
    };

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                styles[type],
            )}
        >
            {labels[type]}
        </span>
    );
}

function StatusBadge({ status }: { status: string }) {
    const completed = status === 'completed';

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-medium capitalize',
                completed
                    ? 'bg-emerald-100 text-emerald-700'
                    : 'bg-amber-100 text-amber-700',
            )}
        >
            {status}
        </span>
    );
}
