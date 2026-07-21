import { Head, router } from '@inertiajs/react';
import { Building2 } from 'lucide-react';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type PeriodType = '1st_kinsena' | '2nd_kinsena';

type PeriodRow = {
    id: number;
    name: string;
    month: number;
    half: number;
    type: PeriodType;
    start_date: string | null;
    end_date: string | null;
    employees: number;
    sss: number;
    philhealth: number;
    pagibig: number;
    total: number;
    status: string;
};

type Props = {
    filters: {
        year: number;
    };
    years: number[];
    formulas: {
        sss: string;
        philhealth: string;
        pagibig: string;
    };
    summary: {
        sss: number;
        philhealth: number;
        pagibig: number;
        total: number;
        cutoffs: number;
    };
    periods: PeriodRow[];
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

export default function GovernmentBenefitsIndex({
    filters,
    years,
    formulas,
    summary,
    periods,
}: Props) {
    function changeYear(year: number) {
        router.get('/government-benefits', { year }, { preserveState: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Government Benefits" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">
                            Government Benefits Contribution
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            SSS, PhilHealth, and Pag-IBIG totals per kinsena cutoff for {filters.year}.
                        </p>
                    </div>
                    <label className="flex w-full flex-col gap-1.5 text-sm md:w-44">
                        <span className="font-medium text-slate-700">Year</span>
                        <select
                            value={filters.year}
                            onChange={(e) => changeYear(Number(e.target.value))}
                            className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-indigo-400"
                        >
                            {years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                    </label>
                </div>

                <div className="flex flex-col gap-1.5 rounded-xl border border-indigo-100 bg-indigo-50/60 px-4 py-3 text-sm text-indigo-900">
                    <span className="font-semibold">Statutory formulas (not editable):</span>
                    <span>SSS — {formulas.sss}</span>
                    <span>PhilHealth — {formulas.philhealth}</span>
                    <span>Pag-IBIG — {formulas.pagibig}</span>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard label="Total SSS" value={formatPeso(summary.sss)} tone="blue" />
                    <SummaryCard
                        label="Total PhilHealth"
                        value={formatPeso(summary.philhealth)}
                        tone="emerald"
                    />
                    <SummaryCard
                        label="Total Pag-IBIG"
                        value={formatPeso(summary.pagibig)}
                        tone="violet"
                    />
                    <SummaryCard
                        label="Grand Total"
                        value={formatPeso(summary.total)}
                        tone="indigo"
                        hint={`${summary.cutoffs} kinsena cutoffs`}
                    />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="flex items-center gap-3">
                            <div className="rounded-xl bg-blue-100 p-2.5 text-blue-600">
                                <Building2 className="size-5" />
                            </div>
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    {filters.year} Contribution Breakdown
                                </h2>
                                <p className="text-sm text-slate-500">
                                    Totals from processed payslips per kinsena cutoff
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                                <tr>
                                    <th className="px-4 py-3 font-medium">Month</th>
                                    <th className="px-4 py-3 font-medium">Cutoff</th>
                                    <th className="px-4 py-3 font-medium">Coverage</th>
                                    <th className="px-4 py-3 font-medium text-right">Employees</th>
                                    <th className="px-4 py-3 font-medium text-right">SSS</th>
                                    <th className="px-4 py-3 font-medium text-right">PhilHealth</th>
                                    <th className="px-4 py-3 font-medium text-right">Pag-IBIG</th>
                                    <th className="px-4 py-3 font-medium text-right">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {periods.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={8}
                                            className="px-4 py-12 text-center text-slate-500"
                                        >
                                            No contribution data for {filters.year}.
                                        </td>
                                    </tr>
                                ) : (
                                    <>
                                        {periods.map((period) => (
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
                                                <td className="px-4 py-3 font-mono text-xs text-slate-600">
                                                    {period.start_date} → {period.end_date}
                                                </td>
                                                <td className="px-4 py-3 text-right text-slate-700">
                                                    {period.employees || '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-slate-700">
                                                    {period.total > 0
                                                        ? formatPeso(period.sss)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-slate-700">
                                                    {period.total > 0
                                                        ? formatPeso(period.philhealth)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right text-slate-700">
                                                    {period.total > 0
                                                        ? formatPeso(period.pagibig)
                                                        : '—'}
                                                </td>
                                                <td className="px-4 py-3 text-right font-medium text-indigo-700">
                                                    {period.total > 0
                                                        ? formatPeso(period.total)
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                        <tr className="bg-slate-50 font-semibold text-slate-900">
                                            <td className="px-4 py-3" colSpan={3}>
                                                {filters.year} Total
                                            </td>
                                            <td className="px-4 py-3 text-right">—</td>
                                            <td className="px-4 py-3 text-right">
                                                {formatPeso(summary.sss)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatPeso(summary.philhealth)}
                                            </td>
                                            <td className="px-4 py-3 text-right">
                                                {formatPeso(summary.pagibig)}
                                            </td>
                                            <td className="px-4 py-3 text-right text-indigo-700">
                                                {formatPeso(summary.total)}
                                            </td>
                                        </tr>
                                    </>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

GovernmentBenefitsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Government Benefits', href: '/government-benefits' },
    ],
};

function SummaryCard({
    label,
    value,
    hint,
    tone,
}: {
    label: string;
    value: string;
    hint?: string;
    tone: 'blue' | 'emerald' | 'violet' | 'indigo';
}) {
    const tones = {
        blue: 'border-blue-100 bg-blue-50/50',
        emerald: 'border-emerald-100 bg-emerald-50/50',
        violet: 'border-violet-100 bg-violet-50/50',
        indigo: 'border-indigo-100 bg-indigo-50/50',
    };

    return (
        <div className={cn('rounded-2xl border p-5 shadow-sm', tones[tone])}>
            <p className="text-sm text-slate-500">{label}</p>
            <p className="mt-1 text-xl font-semibold text-slate-900">{value}</p>
            {hint && <p className="mt-1 text-xs text-slate-400">{hint}</p>}
        </div>
    );
}

function TypeBadge({ type }: { type: PeriodType }) {
    const isFirst = type === '1st_kinsena';

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                isFirst ? 'bg-emerald-100 text-emerald-700' : 'bg-teal-100 text-teal-700',
            )}
        >
            {isFirst ? '1st Kinsena' : '2nd Kinsena'}
        </span>
    );
}
