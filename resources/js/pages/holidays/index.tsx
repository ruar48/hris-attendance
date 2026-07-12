import { Head, router, useForm } from '@inertiajs/react';
import { CalendarDays, Trash2 } from 'lucide-react';

type Holiday = {
    id: number;
    name: string;
    is_recurring: boolean;
    month: number | null;
    day: number | null;
    date: string | null;
    display: string;
    type: string;
    pay_multiplier: number;
};

type Props = {
    holidays: Holiday[];
    default_multiplier: number;
};

const months = [
    { value: 1, label: 'January' },
    { value: 2, label: 'February' },
    { value: 3, label: 'March' },
    { value: 4, label: 'April' },
    { value: 5, label: 'May' },
    { value: 6, label: 'June' },
    { value: 7, label: 'July' },
    { value: 8, label: 'August' },
    { value: 9, label: 'September' },
    { value: 10, label: 'October' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
];

export default function HolidaysIndex({ holidays, default_multiplier }: Props) {
    const form = useForm({
        name: '',
        is_recurring: true,
        month: 5,
        day: 1,
        date: '',
        type: 'regular',
        pay_multiplier: default_multiplier || 2,
    });

    return (
        <>
            <Head title="Holidays" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Holidays</h1>
                    <p className="mt-1 text-sm text-slate-500">
                        <strong>Regular yearly holidays</strong> (Labor Day, Christmas, etc.) only need
                        month + day — no year. Movable holidays still use a specific date.
                    </p>
                </div>

                <div className="rounded-xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                    <p className="font-semibold">How it works</p>
                    <ol className="mt-1 list-decimal space-y-1 pl-4">
                        <li>
                            Regular holidays like May 1 / Dec 25 are set once and repeat every year.
                        </li>
                        <li>If an employee has attendance on that day, holiday pay is added.</li>
                        <li>
                            Formula: <strong>daily rate × (multiplier − 1)</strong>
                        </li>
                    </ol>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center gap-2">
                        <div className="rounded-xl bg-rose-100 p-2 text-rose-600">
                            <CalendarDays className="size-4" />
                        </div>
                        <h2 className="text-lg font-semibold text-slate-900">Add Holiday</h2>
                    </div>
                    <form
                        className="grid gap-4 md:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/holidays', {
                                onSuccess: () => form.reset('name', 'date'),
                            });
                        }}
                    >
                        <label className="text-sm md:col-span-2">
                            <span className="mb-1.5 block text-slate-600">Holiday Name</span>
                            <input
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Labor Day"
                                required
                            />
                        </label>

                        <label className="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm md:col-span-2">
                            <input
                                type="checkbox"
                                className="mt-1"
                                checked={form.data.is_recurring}
                                onChange={(e) => {
                                    form.setData('is_recurring', e.target.checked);
                                    if (e.target.checked) {
                                        form.setData('type', 'regular');
                                        form.setData('pay_multiplier', 2);
                                    }
                                }}
                            />
                            <span>
                                <span className="font-semibold text-slate-800">
                                    Repeats every year (no year needed)
                                </span>
                                <span className="mt-0.5 block text-xs text-slate-500">
                                    Use for fixed regular holidays like New Year (Jan 1), Labor Day
                                    (May 1), Independence Day (Jun 12), Christmas (Dec 25).
                                </span>
                            </span>
                        </label>

                        {form.data.is_recurring ? (
                            <>
                                <label className="text-sm">
                                    <span className="mb-1.5 block font-medium text-slate-700">
                                        Month
                                    </span>
                                    <select
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={form.data.month}
                                        onChange={(e) =>
                                            form.setData('month', Number(e.target.value))
                                        }
                                        required
                                    >
                                        {months.map((month) => (
                                            <option key={month.value} value={month.value}>
                                                {month.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="text-sm">
                                    <span className="mb-1.5 block font-medium text-slate-700">
                                        Day
                                    </span>
                                    <input
                                        type="number"
                                        min={1}
                                        max={31}
                                        className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                        value={form.data.day}
                                        onChange={(e) =>
                                            form.setData('day', Number(e.target.value))
                                        }
                                        required
                                    />
                                    <span className="mt-1 block text-xs text-slate-500">
                                        Applies every year on this month and day.
                                    </span>
                                    {form.errors.month && (
                                        <span className="mt-1 block text-xs text-rose-600">
                                            {form.errors.month}
                                        </span>
                                    )}
                                </label>
                            </>
                        ) : (
                            <label className="text-sm md:col-span-2">
                                <span className="mb-1.5 block font-medium text-slate-700">
                                    Specific Date (this year only)
                                </span>
                                <input
                                    type="date"
                                    className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                    value={form.data.date}
                                    onChange={(e) => form.setData('date', e.target.value)}
                                    required
                                />
                                <span className="mt-1 block text-xs text-slate-500">
                                    For movable holidays like Maundy Thursday, Good Friday, National
                                    Heroes Day, Eid.
                                </span>
                            </label>
                        )}

                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Type</span>
                            <select
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.type}
                                onChange={(e) => form.setData('type', e.target.value)}
                            >
                                <option value="regular">Regular Holiday</option>
                                <option value="special">Special Holiday</option>
                            </select>
                        </label>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Pay Multiplier</span>
                            <input
                                type="number"
                                step="0.01"
                                min={1}
                                className="w-full rounded-xl border border-slate-200 px-3 py-2.5"
                                value={form.data.pay_multiplier}
                                onChange={(e) =>
                                    form.setData('pay_multiplier', Number(e.target.value))
                                }
                                required
                            />
                        </label>
                        <div className="md:col-span-2">
                            <button
                                type="submit"
                                disabled={form.processing}
                                className="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-60"
                            >
                                Save Holiday
                            </button>
                        </div>
                    </form>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">When</th>
                                <th className="px-4 py-3 font-medium">Name</th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">Multiplier</th>
                                <th className="px-4 py-3 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {holidays.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="px-4 py-8 text-center text-slate-500">
                                        No holidays yet. Add one so holiday pay can be computed.
                                    </td>
                                </tr>
                            )}
                            {holidays.map((holiday) => (
                                <tr key={holiday.id} className="border-b border-slate-100 last:border-0">
                                    <td className="px-4 py-3">
                                        <div className="font-medium text-slate-800">
                                            {holiday.display}
                                        </div>
                                        {holiday.is_recurring && (
                                            <span className="mt-1 inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold text-emerald-700">
                                                YEARLY
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">{holiday.name}</td>
                                    <td className="px-4 py-3 capitalize text-slate-600">
                                        {holiday.type}
                                    </td>
                                    <td className="px-4 py-3 text-slate-700">
                                        ×{holiday.pay_multiplier.toFixed(2)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 px-2.5 py-1.5 text-xs text-rose-600 hover:bg-rose-50"
                                            onClick={() => {
                                                if (confirm(`Delete ${holiday.name}?`)) {
                                                    router.delete(`/holidays/${holiday.id}`);
                                                }
                                            }}
                                        >
                                            <Trash2 className="size-3.5" />
                                            Delete
                                        </button>
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

HolidaysIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Holidays', href: '/holidays' },
    ],
};
