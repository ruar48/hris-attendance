import { Head, useForm } from '@inertiajs/react';
import { Clock3, Gift, MapPin, PiggyBank, Save, Wallet } from 'lucide-react';

type Settings = Record<string, string>;

type Props = {
    settings: Settings;
};

function toBool(value: string | boolean | undefined): boolean {
    return value === true || value === '1' || value === 'true';
}

export default function PayrollSettingsPage({ settings }: Props) {
    const form = useForm({
        shift_start: settings.shift_start?.slice(0, 5) ?? '08:00',
        shift_end: settings.shift_end?.slice(0, 5) ?? '17:00',
        late_grace_minutes: Number(settings.late_grace_minutes ?? 5),
        undertime_grace_minutes: Number(settings.undertime_grace_minutes ?? 5),
        undertime_deduction_unit: (settings.undertime_deduction_unit ?? 'exact') as
            | 'exact'
            | '30_minutes'
            | 'hour',

        ot_minimum_minutes: Number(settings.ot_minimum_minutes ?? 60),
        ot_rate_multiplier: Number(settings.ot_rate_multiplier ?? 1.25),
        ot_use_employee_hourly: toBool(settings.ot_use_employee_hourly),
        ot_fixed_hourly_rate: Number(settings.ot_fixed_hourly_rate ?? 0),

        sunday_route_default_amount: Number(settings.sunday_route_default_amount ?? 800),
        sunday_route_use_employee_rate: toBool(settings.sunday_route_use_employee_rate),

        holiday_pay_multiplier: Number(settings.holiday_pay_multiplier ?? 2),

        cash_advance_max_deduction: Number(settings.cash_advance_max_deduction ?? 1000),
        cash_advance_auto_deduct: toBool(settings.cash_advance_auto_deduct),

        sss_rate: Number(settings.sss_rate ?? 0.05),
        philhealth_rate: Number(settings.philhealth_rate ?? 0.025),
        pagibig_fixed: Number(settings.pagibig_fixed ?? 200),

        auto_13th_month: toBool(settings.auto_13th_month),
        thirteenth_month_divisor: Number(settings.thirteenth_month_divisor ?? 12),
    });

    return (
        <>
            <Head title="Payroll Settings" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Payroll Settings</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Set rates and rules so payroll knows how to compute OT, Sunday route, late,
                            cash advance, holiday, government benefits, and 13th month.
                        </p>
                    </div>
                    <button
                        type="button"
                        disabled={form.processing}
                        onClick={() => form.put('/payroll-settings')}
                        className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                    >
                        <Save className="size-4" />
                        Save Settings
                    </button>
                </div>

                <div className="grid gap-5 xl:grid-cols-2">
                    <Section
                        icon={Wallet}
                        title="Pay Schedule (Kinsenas)"
                        tone="emerald"
                        description="Company pays twice a month — 1st and 2nd kinsena."
                    >
                        <HelpBox>
                            <strong>1st Kinsena:</strong> days 1–15 · basic = monthly salary ÷ 2
                            <br />
                            <strong>2nd Kinsena:</strong> days 16–end · basic = monthly salary ÷ 2
                            <br />
                            <strong>13th month:</strong> (total basic earned in the year) ÷ 12. Paid on
                            its own payslip after December 2nd kinsena.
                        </HelpBox>
                    </Section>

                    <Section
                        icon={Clock3}
                        title="Work Schedule, Late & Undertime"
                        tone="blue"
                        description="Used when biometric/DTR punches are synced into attendance."
                    >
                        <Field label="Shift Start" hint="Official time-in">
                            <input
                                type="time"
                                className={inputClass}
                                value={form.data.shift_start}
                                onChange={(e) => form.setData('shift_start', e.target.value)}
                            />
                        </Field>
                        <Field label="Shift End" hint="Official time-out. Leaving earlier may count as undertime.">
                            <input
                                type="time"
                                className={inputClass}
                                value={form.data.shift_end}
                                onChange={(e) => form.setData('shift_end', e.target.value)}
                            />
                        </Field>
                        <Field
                            label="Late Grace (minutes)"
                            hint="Late arrivals within this grace are not deducted"
                        >
                            <input
                                type="number"
                                min={0}
                                className={inputClass}
                                value={form.data.late_grace_minutes}
                                onChange={(e) =>
                                    form.setData('late_grace_minutes', Number(e.target.value))
                                }
                            />
                        </Field>
                        <Field
                            label="Undertime Grace (minutes)"
                            hint="Early departures within this grace are not deducted"
                        >
                            <input
                                type="number"
                                min={0}
                                className={inputClass}
                                value={form.data.undertime_grace_minutes}
                                onChange={(e) =>
                                    form.setData(
                                        'undertime_grace_minutes',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <label className="text-sm">
                            <span className="mb-1.5 block font-medium text-slate-700">
                                Undertime deduction rule
                            </span>
                            <select
                                className={inputClass}
                                value={form.data.undertime_deduction_unit}
                                onChange={(e) =>
                                    form.setData(
                                        'undertime_deduction_unit',
                                        e.target.value as 'exact' | '30_minutes' | 'hour',
                                    )
                                }
                            >
                                <option value="exact">Exact minutes (pro-rated)</option>
                                <option value="30_minutes">
                                    Per 30 minutes (round up to 30-min blocks)
                                </option>
                                <option value="hour">Per hour (round up to full hours)</option>
                            </select>
                        </label>
                        <HelpBox>
                            <strong>Example:</strong> left 55 minutes early after grace.
                            <br />
                            <strong>Exact:</strong> deduct 55 minutes.
                            <br />
                            <strong>30 minutes:</strong> deduct 60 minutes (2 × 30-min blocks).
                            <br />
                            <strong>Per hour:</strong> deduct 60 minutes (1 full hour).
                        </HelpBox>
                    </Section>

                    <Section
                        icon={Clock3}
                        title="Overtime (OT)"
                        tone="cyan"
                        description="How the system decides OT and the price of OT hours."
                    >
                        <Field
                            label="Minimum minutes after shift end to count as OT"
                            hint="Example: 60 = must work at least 1 hour past shift end before OT pay starts. OT minutes below this become 0."
                        >
                            <input
                                type="number"
                                min={0}
                                className={inputClass}
                                value={form.data.ot_minimum_minutes}
                                onChange={(e) =>
                                    form.setData('ot_minimum_minutes', Number(e.target.value))
                                }
                            />
                        </Field>
                        <Field
                            label="OT rate multiplier"
                            hint="Example: 1.25 = OT hourly = employee hourly × 1.25"
                        >
                            <input
                                type="number"
                                step="0.01"
                                min={1}
                                className={inputClass}
                                value={form.data.ot_rate_multiplier}
                                onChange={(e) =>
                                    form.setData('ot_rate_multiplier', Number(e.target.value))
                                }
                            />
                        </Field>
                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.ot_use_employee_hourly}
                                onChange={(e) =>
                                    form.setData('ot_use_employee_hourly', e.target.checked)
                                }
                            />
                            <span>
                                <span className="font-semibold text-slate-800">
                                    Use employee hourly rate
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    If off, use the fixed OT hourly rate below for everyone.
                                </span>
                            </span>
                        </label>
                        <Field
                            label="Fixed OT hourly rate (₱)"
                            hint="Only used when “Use employee hourly rate” is off"
                        >
                            <input
                                type="number"
                                step="0.01"
                                min={0}
                                className={inputClass}
                                value={form.data.ot_fixed_hourly_rate}
                                onChange={(e) =>
                                    form.setData('ot_fixed_hourly_rate', Number(e.target.value))
                                }
                            />
                        </Field>
                        <HelpBox>
                            Formula: <code>OT pay = OT hours × hourly rate × multiplier</code>. OT
                            hours come from biometric/DTR time-out after shift end, only if they meet
                            the minimum minutes.
                        </HelpBox>
                    </Section>

                    <Section
                        icon={MapPin}
                        title="Sunday Route"
                        tone="orange"
                        description="Extra wage when the employee works a Sunday route."
                    >
                        <Field
                            label="Default Sunday route amount (₱)"
                            hint="Company default amount added on Sundays"
                        >
                            <input
                                type="number"
                                step="0.01"
                                min={0}
                                className={inputClass}
                                value={form.data.sunday_route_default_amount}
                                onChange={(e) =>
                                    form.setData(
                                        'sunday_route_default_amount',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.sunday_route_use_employee_rate}
                                onChange={(e) =>
                                    form.setData('sunday_route_use_employee_rate', e.target.checked)
                                }
                            />
                            <span>
                                <span className="font-semibold text-slate-800">
                                    Prefer employee Sunday route rate
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    If the employee profile has a Sunday route rate, use that. Otherwise
                                    use the default amount above.
                                </span>
                            </span>
                        </label>
                    </Section>

                    <Section
                        icon={Wallet}
                        title="Cash Advance"
                        tone="violet"
                        description="System only deducts advances you recorded under Cash Advances."
                    >
                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.cash_advance_auto_deduct}
                                onChange={(e) =>
                                    form.setData('cash_advance_auto_deduct', e.target.checked)
                                }
                            />
                            <span>
                                <span className="font-semibold text-slate-800">
                                    Auto-deduct active cash advances on payroll
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    Oldest unpaid advance is deducted first, then balance is reduced.
                                </span>
                            </span>
                        </label>
                        <Field
                            label="Max deduction per kinsena (₱)"
                            hint="Example: 1000 = deduct up to ₱500 per kinsena cutoff (½ of monthly max)"
                        >
                            <input
                                type="number"
                                step="0.01"
                                min={0}
                                className={inputClass}
                                value={form.data.cash_advance_max_deduction}
                                onChange={(e) =>
                                    form.setData(
                                        'cash_advance_max_deduction',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                        <HelpBox>
                            Record cash advances in <strong>Cash Advances</strong>. Payroll will not
                            invent advances — it only deducts what HR encoded.
                        </HelpBox>
                    </Section>

                    <Section
                        icon={Gift}
                        title="Holiday & 13th Month"
                        tone="rose"
                        description="Automatic earnings when holiday/December rules apply."
                    >
                        <Field
                            label="Holiday pay multiplier"
                            hint="Example: 2.00 = holiday day pay = daily rate × 2 (extra = daily × 1)"
                        >
                            <input
                                type="number"
                                step="0.01"
                                min={1}
                                className={inputClass}
                                value={form.data.holiday_pay_multiplier}
                                onChange={(e) =>
                                    form.setData('holiday_pay_multiplier', Number(e.target.value))
                                }
                            />
                        </Field>
                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.auto_13th_month}
                                onChange={(e) => form.setData('auto_13th_month', e.target.checked)}
                            />
                            <span>
                                <span className="font-semibold text-slate-800">
                                    Auto-issue 13th month as a separate payslip
                                </span>
                                <span className="mt-0.5 block text-slate-500">
                                    After December 2nd kinsena. Not combined with that cutoff’s
                                    salary payslip.
                                </span>
                            </span>
                        </label>
                        <Field
                            label="13th month divisor"
                            hint="PH rule: (total basic salary earned in the year) ÷ divisor. Usually 12. Full-year employee at ₱15,000/mo → ₱180,000 ÷ 12 = ₱15,000."
                        >
                            <input
                                type="number"
                                min={1}
                                className={inputClass}
                                value={form.data.thirteenth_month_divisor}
                                onChange={(e) =>
                                    form.setData(
                                        'thirteenth_month_divisor',
                                        Number(e.target.value),
                                    )
                                }
                            />
                        </Field>
                    </Section>

                    <Section
                        icon={PiggyBank}
                        title="Government Benefits"
                        tone="indigo"
                        description="Automatic deductions computed from basic salary."
                    >
                        <Field label="SSS rate" hint="Example: 0.05 = 5% of basic">
                            <input
                                type="number"
                                step="0.001"
                                min={0}
                                max={1}
                                className={inputClass}
                                value={form.data.sss_rate}
                                onChange={(e) => form.setData('sss_rate', Number(e.target.value))}
                            />
                        </Field>
                        <Field label="PhilHealth rate" hint="Example: 0.025 = 2.5% of basic">
                            <input
                                type="number"
                                step="0.001"
                                min={0}
                                max={1}
                                className={inputClass}
                                value={form.data.philhealth_rate}
                                onChange={(e) =>
                                    form.setData('philhealth_rate', Number(e.target.value))
                                }
                            />
                        </Field>
                        <Field label="Pag-IBIG fixed (₱)" hint="Fixed amount per payroll">
                            <input
                                type="number"
                                step="0.01"
                                min={0}
                                className={inputClass}
                                value={form.data.pagibig_fixed}
                                onChange={(e) =>
                                    form.setData('pagibig_fixed', Number(e.target.value))
                                }
                            />
                        </Field>
                    </Section>
                </div>
            </div>
        </>
    );
}

PayrollSettingsPage.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Payroll Settings', href: '/payroll-settings' },
    ],
};

const inputClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm outline-none focus:border-blue-400';

function Section({
    icon: Icon,
    title,
    description,
    tone,
    children,
}: {
    icon: typeof Clock3;
    title: string;
    description: string;
    tone: string;
    children: React.ReactNode;
}) {
    const tones: Record<string, string> = {
        blue: 'bg-blue-100 text-blue-600',
        cyan: 'bg-cyan-100 text-cyan-600',
        orange: 'bg-orange-100 text-orange-600',
        violet: 'bg-violet-100 text-violet-600',
        rose: 'bg-rose-100 text-rose-600',
        indigo: 'bg-indigo-100 text-indigo-600',
        emerald: 'bg-emerald-100 text-emerald-600',
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-4 flex items-start gap-3">
                <div className={`rounded-xl p-2.5 ${tones[tone]}`}>
                    <Icon className="size-5" />
                </div>
                <div>
                    <h2 className="text-lg font-bold text-slate-900">{title}</h2>
                    <p className="text-sm text-slate-500">{description}</p>
                </div>
            </div>
            <div className="grid gap-3">{children}</div>
        </section>
    );
}

function Field({
    label,
    hint,
    children,
}: {
    label: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <label className="block text-sm">
            <span className="mb-1.5 block font-medium text-slate-700">{label}</span>
            {children}
            {hint && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
        </label>
    );
}

function HelpBox({ children }: { children: React.ReactNode }) {
    return (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50 px-3 py-2.5 text-xs leading-relaxed text-slate-600">
            {children}
        </div>
    );
}
