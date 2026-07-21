import { Head, router } from '@inertiajs/react';
import {
    ArrowDownToLine,
    Banknote,
    Bell,
    Building2,
    Calculator,
    CalendarDays,
    CheckCircle2,
    Clock3,
    CreditCard,
    FileText,
    Fingerprint,
    Gift,
    MapPin,
    MoreVertical,
    Play,
    Receipt,
    Users,
    Wallet,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { dashboard } from '@/routes';
import { formatPeso } from '@/lib/money';
import { cn } from '@/lib/utils';

type Feature = {
    key: string;
    title: string;
    description: string;
    status: string;
};

type PayslipPreview = {
    employee_name: string;
    employee_code: string;
    position: string;
    basic_pay: number;
    holiday_pay: number;
    sunday_route: number;
    overtime_pay: number;
    late_deduction: number;
    undertime_deduction: number;
    cash_advance_deduction: number;
    sss: number;
    philhealth: number;
    pagibig: number;
    total_earnings: number;
    total_deductions: number;
    net_pay: number;
};

type Props = {
    stats: {
        total_employees: number;
        total_payroll: number;
        total_deductions: number;
        net_payroll: number;
    };
    period: {
        id: number;
        name: string;
        cutoff_date: string | null;
        process_start: string | null;
        process_end: string | null;
        payslip_release: string | null;
        payday: string | null;
        status: string;
    } | null;
    features: Feature[];
    recentRuns: Array<{
        id: number;
        period: string | null;
        total_employees: number;
        total_payroll: number;
        status: string;
    }>;
    notifications: Array<{
        id: number;
        title: string;
        message: string;
        type: string;
        created_at: string | null;
    }>;
    samplePayslip: PayslipPreview | null;
};

const featureMeta: Record<
    string,
    { icon: LucideIcon; iconClass: string; cardClass: string }
> = {
    biometric_dtr: {
        icon: Fingerprint,
        iconClass: 'bg-blue-100 text-blue-600',
        cardClass: 'hover:border-blue-200 hover:bg-blue-50/40',
    },
    auto_deductions: {
        icon: Calculator,
        iconClass: 'bg-violet-100 text-violet-600',
        cardClass: 'hover:border-violet-200 hover:bg-violet-50/40',
    },
    holiday_pay: {
        icon: CalendarDays,
        iconClass: 'bg-rose-100 text-rose-600',
        cardClass: 'hover:border-rose-200 hover:bg-rose-50/40',
    },
    sunday_route: {
        icon: MapPin,
        iconClass: 'bg-orange-100 text-orange-600',
        cardClass: 'hover:border-orange-200 hover:bg-orange-50/40',
    },
    overtime: {
        icon: Clock3,
        iconClass: 'bg-cyan-100 text-cyan-600',
        cardClass: 'hover:border-cyan-200 hover:bg-cyan-50/40',
    },
    gov_benefits: {
        icon: Building2,
        iconClass: 'bg-indigo-100 text-indigo-600',
        cardClass: 'hover:border-indigo-200 hover:bg-indigo-50/40',
    },
    thirteenth: {
        icon: Gift,
        iconClass: 'bg-pink-100 text-pink-600',
        cardClass: 'hover:border-pink-200 hover:bg-pink-50/40',
    },
};

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-PH', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
}

export default function Dashboard({
    stats,
    period,
    features,
    recentRuns,
    notifications,
    samplePayslip,
}: Props) {
    return (
        <>
            <Head title="Dashboard" />
            <div className="flex flex-1 flex-col gap-6 overflow-x-auto bg-gradient-to-br from-slate-50 via-blue-50/30 to-slate-50 p-4 md:p-6">
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl">
                            Welcome back, Admin! 👋
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Here's what's happening with your payroll.
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <div className="inline-flex items-center gap-2 rounded-xl border border-blue-200 bg-white px-4 py-2.5 text-sm font-semibold text-blue-700 shadow-sm">
                            <CalendarDays className="size-4 text-blue-500" />
                            {period?.name ?? 'No payroll period'}
                        </div>
                        <button
                            type="button"
                            className="relative rounded-xl border border-slate-200 bg-white p-2.5 text-slate-500 shadow-sm transition hover:bg-blue-50 hover:text-blue-600"
                        >
                            <Bell className="size-5" />
                            {notifications.length > 0 && (
                                <span className="absolute -top-1 -right-1 flex size-5 items-center justify-center rounded-full bg-rose-500 text-[10px] font-bold text-white">
                                    {Math.min(notifications.length, 9)}
                                </span>
                            )}
                        </button>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <StatCard
                        label="Total Employees"
                        value={String(stats.total_employees)}
                        hint="Active Employees"
                        icon={Users}
                        tone="blue"
                    />
                    <StatCard
                        label="Total Payroll"
                        value={formatPeso(stats.total_payroll)}
                        hint="This Period"
                        icon={Wallet}
                        tone="green"
                    />
                    <StatCard
                        label="Deductions"
                        value={formatPeso(stats.total_deductions)}
                        hint="Total"
                        icon={ArrowDownToLine}
                        tone="violet"
                    />
                    <StatCard
                        label="Net Payroll"
                        value={formatPeso(stats.net_payroll)}
                        hint="To be Disbursed"
                        icon={CreditCard}
                        tone="orange"
                    />
                </div>

                <div className="grid gap-6 xl:grid-cols-[1.65fr_1fr]">
                    <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                        <div className="mb-5">
                            <h2 className="text-lg font-bold text-slate-900">
                                Automated Features
                            </h2>
                            <p className="text-sm text-slate-500">
                                Active payroll automation rules
                            </p>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {features.map((feature) => {
                                const meta = featureMeta[feature.key] ?? {
                                    icon: CheckCircle2,
                                    iconClass: 'bg-slate-100 text-slate-600',
                                    cardClass: '',
                                };
                                const Icon = meta.icon;

                                return (
                                    <div
                                        key={feature.key}
                                        className={cn(
                                            'rounded-2xl border border-slate-100 bg-white p-4 shadow-sm transition',
                                            meta.cardClass,
                                        )}
                                    >
                                        <div className="mb-3 flex items-start justify-between gap-3">
                                            <div
                                                className={cn(
                                                    'flex size-10 items-center justify-center rounded-xl',
                                                    meta.iconClass,
                                                )}
                                            >
                                                <Icon className="size-5" />
                                            </div>
                                            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                                                <span className="size-1.5 rounded-full bg-emerald-500" />
                                                Active
                                            </span>
                                        </div>
                                        <h3 className="font-semibold text-slate-900">
                                            {feature.title}
                                        </h3>
                                        <p className="mt-1 text-sm leading-relaxed text-slate-500">
                                            {feature.description}
                                        </p>
                                    </div>
                                );
                            })}
                        </div>
                    </section>

                    <div className="space-y-5">
                        <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <h2 className="mb-4 text-lg font-bold text-slate-900">
                                Quick Actions
                            </h2>
                            <div className="space-y-3">
                                <button
                                    type="button"
                                    onClick={() =>
                                        period &&
                                        router.post('/payroll/run', {
                                            payroll_period_id: period.id,
                                        })
                                    }
                                    className="flex w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3.5 text-sm font-semibold text-white shadow-md shadow-blue-600/25 transition hover:bg-blue-700"
                                >
                                    <Play className="size-4 fill-current" />
                                    Run Payroll
                                </button>
                                <QuickAction
                                    label="Sync Biometrics"
                                    hint="Fingerprint primary source"
                                    icon={Fingerprint}
                                    tone="blue"
                                    onClick={() => router.visit('/biometrics')}
                                />
                                <QuickAction
                                    label="Generate Payslips"
                                    hint="View & print payslips"
                                    icon={FileText}
                                    tone="emerald"
                                    onClick={() => router.visit('/payroll')}
                                />
                                <QuickAction
                                    label="View Reports"
                                    hint="Employees & payroll history"
                                    icon={Receipt}
                                    tone="violet"
                                    onClick={() => router.visit('/employees')}
                                />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div className="mb-4 flex items-center gap-2">
                                <div className="flex size-8 items-center justify-center rounded-lg bg-blue-100 text-blue-600">
                                    <CalendarDays className="size-4" />
                                </div>
                                <h2 className="text-lg font-bold text-slate-900">
                                    Payroll Calendar
                                </h2>
                            </div>
                            <div className="space-y-2.5">
                                <CalendarRow
                                    label="Cut-off Date"
                                    value={formatDate(period?.cutoff_date ?? null)}
                                    color="bg-rose-500"
                                />
                                <CalendarRow
                                    label="Payroll Process"
                                    value={`${formatDate(period?.process_start ?? null)} – ${formatDate(period?.process_end ?? null)}`}
                                    color="bg-blue-500"
                                />
                                <CalendarRow
                                    label="Payslip Release"
                                    value={formatDate(period?.payslip_release ?? null)}
                                    color="bg-violet-500"
                                />
                                <CalendarRow
                                    label="Payday"
                                    value={formatDate(period?.payday ?? null)}
                                    color="bg-emerald-500"
                                />
                            </div>
                        </section>

                        <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                            <div className="mb-4 flex items-center gap-2">
                                <div className="flex size-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                                    <Bell className="size-4" />
                                </div>
                                <h2 className="text-lg font-bold text-slate-900">
                                    Notifications
                                </h2>
                            </div>
                            <div className="space-y-3">
                                {notifications.length === 0 && (
                                    <p className="text-sm text-slate-500">No notifications yet.</p>
                                )}
                                {notifications.map((item, index) => (
                                    <div key={item.id} className="flex gap-3">
                                        <span
                                            className={cn(
                                                'mt-1.5 size-2.5 shrink-0 rounded-full',
                                                index % 3 === 0 && 'bg-emerald-500',
                                                index % 3 === 1 && 'bg-blue-500',
                                                index % 3 === 2 && 'bg-amber-500',
                                            )}
                                        />
                                        <div>
                                            <p className="text-sm font-medium text-slate-800">
                                                {item.title}
                                            </p>
                                            <p className="text-xs text-slate-500">{item.message}</p>
                                            <p className="mt-0.5 text-xs text-slate-400">
                                                {item.created_at}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>
                </div>

                <section className="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-bold text-slate-900">
                        Recent Payroll Runs
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-100 text-slate-500">
                                <tr>
                                    <th className="px-3 py-3 font-semibold">Payroll Period</th>
                                    <th className="px-3 py-3 font-semibold">Total Employees</th>
                                    <th className="px-3 py-3 font-semibold">Total Payroll</th>
                                    <th className="px-3 py-3 font-semibold">Status</th>
                                    <th className="px-3 py-3 font-semibold">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recentRuns.map((run) => (
                                    <tr
                                        key={run.id}
                                        className="border-b border-slate-50 transition hover:bg-blue-50/40 last:border-0"
                                    >
                                        <td className="px-3 py-3.5 font-semibold text-slate-800">
                                            {run.period}
                                        </td>
                                        <td className="px-3 py-3.5 text-slate-600">
                                            {run.total_employees}
                                        </td>
                                        <td className="px-3 py-3.5 font-medium text-slate-700">
                                            {formatPeso(run.total_payroll)}
                                        </td>
                                        <td className="px-3 py-3.5">
                                            <StatusBadge status={run.status} />
                                        </td>
                                        <td className="px-3 py-3.5">
                                            <button
                                                type="button"
                                                className="rounded-lg p-1.5 text-slate-400 transition hover:bg-slate-100 hover:text-blue-600"
                                                onClick={() => router.visit(`/payroll/${run.id}`)}
                                            >
                                                <MoreVertical className="size-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                {samplePayslip && (
                    <section className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm">
                        <div className="flex flex-col gap-2 border-b border-slate-100 bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-4 text-white md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 className="text-lg font-bold">Sample Payslip Preview</h2>
                                <p className="text-sm text-blue-100">
                                    {samplePayslip.employee_name} · {samplePayslip.position} ·{' '}
                                    {samplePayslip.employee_code}
                                </p>
                            </div>
                            <div className="rounded-xl bg-white/15 px-4 py-2 text-right backdrop-blur">
                                <p className="text-xs text-blue-100">Net Salary</p>
                                <p className="text-xl font-bold">{formatPeso(samplePayslip.net_pay)}</p>
                            </div>
                        </div>

                        <div className="grid gap-0 md:grid-cols-2">
                            <div className="border-b border-slate-100 p-5 md:border-r md:border-b-0">
                                <div className="mb-3 flex items-center gap-2">
                                    <Banknote className="size-4 text-emerald-600" />
                                    <h3 className="text-sm font-bold uppercase tracking-wide text-emerald-700">
                                        Earnings
                                    </h3>
                                </div>
                                <PayslipRow label="Basic Pay" value={samplePayslip.basic_pay} />
                                <PayslipRow label="Holiday Pay" value={samplePayslip.holiday_pay} />
                                <PayslipRow label="Sunday Route" value={samplePayslip.sunday_route} />
                                <PayslipRow label="Overtime Pay" value={samplePayslip.overtime_pay} />
                                <div className="mt-3 flex justify-between rounded-xl bg-emerald-50 px-3 py-2.5 font-bold text-emerald-700">
                                    <span>Total Earnings</span>
                                    <span>{formatPeso(samplePayslip.total_earnings)}</span>
                                </div>
                            </div>
                            <div className="p-5">
                                <div className="mb-3 flex items-center gap-2">
                                    <ArrowDownToLine className="size-4 text-rose-600" />
                                    <h3 className="text-sm font-bold uppercase tracking-wide text-rose-700">
                                        Deductions
                                    </h3>
                                </div>
                                <PayslipRow label="Late" value={samplePayslip.late_deduction} />
                                <PayslipRow
                                    label="Undertime"
                                    value={samplePayslip.undertime_deduction}
                                />
                                <PayslipRow
                                    label="Cash Advance"
                                    value={samplePayslip.cash_advance_deduction}
                                />
                                <PayslipRow label="SSS" value={samplePayslip.sss} />
                                <PayslipRow label="PhilHealth" value={samplePayslip.philhealth} />
                                <PayslipRow label="Pag-IBIG" value={samplePayslip.pagibig} />
                                <div className="mt-3 flex justify-between rounded-xl bg-rose-50 px-3 py-2.5 font-bold text-rose-700">
                                    <span>Total Deductions</span>
                                    <span>{formatPeso(samplePayslip.total_deductions)}</span>
                                </div>
                            </div>
                        </div>

                        <div className="border-t border-dashed border-slate-200 bg-slate-50 px-5 py-6 text-center">
                            <p className="text-2xl font-bold text-emerald-600">
                                Net Salary {formatPeso(samplePayslip.net_pay)}
                            </p>
                            <div className="mx-auto mt-6 max-w-xs border-t border-slate-300 pt-2 text-sm text-slate-400">
                                Employee Signature
                            </div>
                        </div>
                    </section>
                )}
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};

function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone,
}: {
    label: string;
    value: string;
    hint: string;
    icon: LucideIcon;
    tone: 'blue' | 'green' | 'violet' | 'orange';
}) {
    const tones = {
        blue: {
            card: 'border-blue-100 from-white to-blue-50/60',
            icon: 'bg-blue-100 text-blue-600',
            hint: 'text-blue-600',
        },
        green: {
            card: 'border-emerald-100 from-white to-emerald-50/60',
            icon: 'bg-emerald-100 text-emerald-600',
            hint: 'text-emerald-600',
        },
        violet: {
            card: 'border-violet-100 from-white to-violet-50/60',
            icon: 'bg-violet-100 text-violet-600',
            hint: 'text-violet-600',
        },
        orange: {
            card: 'border-orange-100 from-white to-orange-50/60',
            icon: 'bg-orange-100 text-orange-600',
            hint: 'text-orange-600',
        },
    };

    return (
        <div
            className={cn(
                'rounded-2xl border bg-gradient-to-br p-5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                tones[tone].card,
            )}
        >
            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm font-medium text-slate-500">{label}</p>
                <div className={cn('rounded-xl p-2.5', tones[tone].icon)}>
                    <Icon className="size-5" />
                </div>
            </div>
            <p className="text-2xl font-bold tracking-tight text-slate-900">{value}</p>
            <p className={cn('mt-1 text-xs font-medium', tones[tone].hint)}>{hint}</p>
        </div>
    );
}

function QuickAction({
    label,
    hint,
    onClick,
    icon: Icon,
    tone,
}: {
    label: string;
    hint: string;
    onClick: () => void;
    icon: LucideIcon;
    tone: 'blue' | 'emerald' | 'violet';
}) {
    const tones = {
        blue: 'bg-blue-50 text-blue-600',
        emerald: 'bg-emerald-50 text-emerald-600',
        violet: 'bg-violet-50 text-violet-600',
    };

    return (
        <button
            type="button"
            onClick={onClick}
            className="flex w-full items-center gap-3 rounded-xl border border-slate-100 bg-slate-50/80 px-3.5 py-3 text-left transition hover:border-blue-200 hover:bg-blue-50/50"
        >
            <div className={cn('rounded-lg p-2', tones[tone])}>
                <Icon className="size-4" />
            </div>
            <div>
                <p className="text-sm font-semibold text-slate-800">{label}</p>
                <p className="text-xs text-slate-500">{hint}</p>
            </div>
        </button>
    );
}

function CalendarRow({
    label,
    value,
    color,
}: {
    label: string;
    value: string;
    color: string;
}) {
    return (
        <div className="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5 text-sm">
            <div className="flex items-center gap-2.5">
                <span className={cn('size-2.5 rounded-full', color)} />
                <span className="text-slate-500">{label}</span>
            </div>
            <span className="font-semibold text-slate-800">{value}</span>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const completed = status === 'completed';

    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2.5 py-1 text-xs font-semibold',
                completed ? 'bg-emerald-100 text-emerald-700' : 'bg-blue-100 text-blue-700',
            )}
        >
            {completed ? 'Completed' : status === 'in_progress' ? 'In Progress' : status}
        </span>
    );
}

function PayslipRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between py-1.5 text-sm">
            <span className="text-slate-500">{label}</span>
            <span className="font-medium text-slate-800">{formatPeso(value)}</span>
        </div>
    );
}
