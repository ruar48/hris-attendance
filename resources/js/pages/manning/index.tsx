import { Head, Link, router } from '@inertiajs/react';
import { Maximize2 } from 'lucide-react';
import { ManningSheet } from '@/components/timesheet/dtr-workbook';
import type { Manning } from '@/components/timesheet/dtr-workbook';

type Props = {
    sites: { value: string; label: string }[];
    /** The selected site's value, or "all". */
    site: string;
    manning: Manning;
};

/**
 * A site's Manning tab inside the app. "Full screen" opens the same tab in
 * the workbook, without the sidebar.
 */
export default function ManningIndex({ sites, site, manning }: Props) {
    return (
        <>
            <Head title="Manning" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">Manning</h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Who works at each site and their regular schedule time. Schedule times save as you pick
                            them and are used to fill the Schedules page and measure late and overtime.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-end gap-3">
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Site</span>
                            <select
                                className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 font-medium"
                                value={site}
                                onChange={(event) =>
                                    router.get('/manning', { site: event.target.value }, { preserveScroll: true })
                                }
                            >
                                {sites.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                                <option value="all">All sites</option>
                            </select>
                        </label>
                        <Link
                            href="/timesheet/workbook"
                            data={{ site, sheet: 'manning' }}
                            className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-700 hover:bg-slate-50"
                        >
                            <Maximize2 className="size-4" />
                            Full screen
                        </Link>
                    </div>
                </div>

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <ManningSheet
                        manning={manning}
                        site={site}
                        onOpen={(employeeId) => router.get('/timesheet', { site, employee_id: employeeId })}
                    />
                </section>
            </div>
        </>
    );
}

ManningIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Manning', href: '/manning' },
    ],
};
