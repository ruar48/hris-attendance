import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Download, ExternalLink, History, ShieldCheck } from 'lucide-react';
import { EmployeeCombobox } from '@/components/employee-combobox';
import type { EmployeeOption } from '@/components/employee-combobox';

type LogRow = {
    id: number;
    at: string;
    user: string;
    user_id: number | null;
    ip: string | null;
    employee_id: number;
    employee: string;
    employee_code: string | null;
    site: string | null;
    work_date: string;
    work_date_label: string;
    month: string;
    source: string;
    source_key: string;
    action: 'created' | 'updated' | 'deleted';
    changes: Record<string, [string | null, string | null]>;
};

type Filters = {
    from: string;
    to: string;
    user_id: number | null;
    employee_id: number | null;
    site: string | null;
    source: string | null;
    action: string | null;
    work_date: string | null;
};

type Props = {
    logs: {
        data: LogRow[];
        links: { url: string | null; label: string; active: boolean }[];
        last_page: number;
        total: number;
    };
    summary: { user_id: number | null; name: string; created: number; updated: number; deleted: number; total: number }[];
    filters: Filters;
    users: { id: number; name: string }[];
    employees: EmployeeOption[];
    sites: string[];
    sources: Record<string, string>;
};

const FIELD_LABELS: Record<string, string> = {
    time_in: 'AM arrival',
    break_out: 'AM departure',
    break_in: 'PM arrival',
    time_out: 'PM departure',
    note_time_in: 'Outside note arrival',
    note_time_out: 'Outside note departure',
    day_status: 'Day',
    remarks: 'Remarks',
    schedule: 'Schedule',
};

const ACTION_STYLES: Record<string, { label: string; className: string }> = {
    created: { label: 'Added', className: 'bg-emerald-100 text-emerald-700' },
    updated: { label: 'Edited', className: 'bg-blue-100 text-blue-700' },
    deleted: { label: 'Deleted', className: 'bg-red-100 text-red-700' },
};

const input = 'rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm disabled:bg-slate-50 disabled:text-slate-400';

export default function AttendanceLogsIndex({ logs, summary, filters, users, employees, sites, sources }: Props) {
    const visit = (changes: Partial<Filters>) => {
        const next = { ...filters, ...changes };

        router.get(
            '/attendance-logs',
            Object.fromEntries(Object.entries(next).filter(([, value]) => value !== null && value !== '')),
            { preserveScroll: true, preserveState: true },
        );
    };

    const exportQuery = new URLSearchParams(
        Object.entries(filters)
            .filter(([, value]) => value !== null && value !== '')
            .map(([key, value]) => [key, String(value)]),
    ).toString();

    const dayHistory = filters.work_date !== null;
    const historyEmployee = employees.find((e) => e.id === filters.employee_id);

    return (
        <>
            <Head title="Time Sheet Logs" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold text-slate-900">
                            <ShieldCheck className="size-6 text-slate-400" />
                            Time Sheet Logs
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Every change to time sheets and schedules: who made it, when, from which computer, and the
                            value before and after. Use a day's <b>history</b> to find who entered a wrong time.
                        </p>
                    </div>
                    <a
                        href={`/attendance-logs/export?${exportQuery}`}
                        className="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 hover:bg-slate-50"
                    >
                        <Download className="size-4" />
                        Export CSV
                    </a>
                </div>

                {dayHistory ? (
                    <div className="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-3 text-sm text-amber-900">
                        <History className="size-5" />
                        <span className="flex-1">
                            Full history of{' '}
                            <b>{historyEmployee ? `${historyEmployee.last_name}, ${historyEmployee.first_name}` : 'this employee'}</b>{' '}
                            on <b>{filters.work_date}</b> — every change ever made to this day, newest first.
                        </span>
                        <button
                            type="button"
                            onClick={() => visit({ work_date: null, employee_id: null })}
                            className="inline-flex items-center gap-1 rounded-lg px-2 py-1 font-medium hover:bg-amber-100"
                        >
                            <ArrowLeft className="size-4" />
                            Back to all changes
                        </button>
                    </div>
                ) : (
                    <section className="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">Changed from</span>
                            <input type="date" className={input} value={filters.from} onChange={(e) => e.target.value && visit({ from: e.target.value })} />
                        </label>
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">to</span>
                            <input type="date" className={input} value={filters.to} onChange={(e) => e.target.value && visit({ to: e.target.value })} />
                        </label>
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">Changed by</span>
                            <select
                                className={input}
                                value={filters.user_id ?? ''}
                                onChange={(e) => visit({ user_id: e.target.value ? Number(e.target.value) : null })}
                            >
                                <option value="">Everyone</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <div className="w-64 text-xs text-slate-600">
                            <span className="mb-1 block">Employee</span>
                            <div className="flex items-center gap-1">
                                <div className="flex-1 text-sm">
                                    <EmployeeCombobox
                                        employees={employees}
                                        value={filters.employee_id?.toString() ?? ''}
                                        placeholder="All employees"
                                        onChange={(id) => visit({ employee_id: Number(id) })}
                                    />
                                </div>
                                {filters.employee_id && (
                                    <button type="button" className="px-1 text-slate-400 hover:text-slate-700" onClick={() => visit({ employee_id: null })}>
                                        ×
                                    </button>
                                )}
                            </div>
                        </div>
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">Site</span>
                            <select className={input} value={filters.site ?? ''} onChange={(e) => visit({ site: e.target.value || null })}>
                                <option value="">All sites</option>
                                {sites.map((site) => (
                                    <option key={site} value={site}>
                                        {site}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">Where</span>
                            <select className={input} value={filters.source ?? ''} onChange={(e) => visit({ source: e.target.value || null })}>
                                <option value="">Time Sheet & Schedule</option>
                                {Object.entries(sources).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </label>
                        <label className="text-xs text-slate-600">
                            <span className="mb-1 block">Action</span>
                            <select className={input} value={filters.action ?? ''} onChange={(e) => visit({ action: e.target.value || null })}>
                                <option value="">Any</option>
                                <option value="created">Added</option>
                                <option value="updated">Edited</option>
                                <option value="deleted">Deleted</option>
                            </select>
                        </label>
                    </section>
                )}

                {!dayHistory && summary.length > 0 && (
                    <section>
                        <h2 className="mb-2 text-sm font-semibold text-slate-700">Who made changes in this period</h2>
                        <div className="flex flex-wrap gap-3">
                            {summary.map((person) => (
                                <button
                                    key={person.user_id ?? 'system'}
                                    type="button"
                                    onClick={() => visit({ user_id: person.user_id === filters.user_id ? null : person.user_id })}
                                    className={`min-w-48 rounded-xl border bg-white px-4 py-3 text-left shadow-sm hover:border-slate-400 ${
                                        person.user_id !== null && person.user_id === filters.user_id ? 'border-blue-500 ring-2 ring-blue-100' : 'border-slate-200'
                                    }`}
                                >
                                    <div className="font-semibold text-slate-900">{person.name}</div>
                                    <div className="text-2xl font-bold text-slate-900">{person.total}</div>
                                    <div className="mt-0.5 flex gap-2 text-xs">
                                        <span className="text-emerald-700">{person.created} added</span>
                                        <span className="text-blue-700">{person.updated} edited</span>
                                        <span className="text-red-700">{person.deleted} deleted</span>
                                    </div>
                                </button>
                            ))}
                        </div>
                    </section>
                )}

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full text-left text-sm">
                        <thead className="border-b border-slate-200 bg-slate-50 text-xs text-slate-500">
                            <tr>
                                <th className="px-4 py-3 font-medium">When</th>
                                <th className="px-4 py-3 font-medium">Changed by</th>
                                <th className="px-4 py-3 font-medium">Employee</th>
                                <th className="px-4 py-3 font-medium">Day changed</th>
                                <th className="px-4 py-3 font-medium">Where</th>
                                <th className="px-4 py-3 font-medium">Action</th>
                                <th className="px-4 py-3 font-medium">What changed (before → after)</th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {logs.data.length === 0 && (
                                <tr>
                                    <td colSpan={8} className="px-4 py-10 text-center text-slate-500">
                                        No changes match these filters.
                                    </td>
                                </tr>
                            )}
                            {logs.data.map((log) => (
                                <tr key={log.id} className="border-b border-slate-100 align-top last:border-0">
                                    <td className="px-4 py-3 whitespace-nowrap text-slate-600">{log.at}</td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="font-medium text-slate-900">{log.user}</div>
                                        {log.ip && <div className="text-xs text-slate-400">{log.ip}</div>}
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap">
                                        <div className="font-medium text-slate-800">{log.employee}</div>
                                        <div className="text-xs text-slate-400">
                                            {log.employee_code}
                                            {log.site ? ` · ${log.site}` : ''}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-slate-700">{log.work_date_label}</td>
                                    <td className="px-4 py-3 whitespace-nowrap text-slate-600">{log.source}</td>
                                    <td className="px-4 py-3">
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-medium ${ACTION_STYLES[log.action]?.className}`}>
                                            {ACTION_STYLES[log.action]?.label ?? log.action}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <ul className="space-y-0.5">
                                            {Object.entries(log.changes).map(([field, [before, after]]) => (
                                                <li key={field}>
                                                    <span className="text-slate-500">{FIELD_LABELS[field] ?? field}:</span>{' '}
                                                    <span className={before ? 'text-red-600 line-through' : 'text-slate-400'}>{before ?? 'blank'}</span>
                                                    {' → '}
                                                    <span className={after ? 'font-semibold text-emerald-700' : 'text-slate-400'}>
                                                        {after ?? 'blank'}
                                                    </span>
                                                </li>
                                            ))}
                                        </ul>
                                    </td>
                                    <td className="px-4 py-3 whitespace-nowrap text-right">
                                        {!dayHistory && (
                                            <button
                                                type="button"
                                                title="Every change ever made to this employee's day"
                                                onClick={() => visit({ work_date: log.work_date, employee_id: log.employee_id })}
                                                className="mr-2 inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
                                            >
                                                <History className="size-3.5" />
                                                Day history
                                            </button>
                                        )}
                                        <Link
                                            href={log.source_key === 'schedule' ? '/schedules' : '/timesheet'}
                                            data={
                                                log.source_key === 'schedule'
                                                    ? { site: log.site ?? 'all', month: log.month }
                                                    : { site: log.site ?? 'all', employee_id: log.employee_id, month: log.month }
                                            }
                                            className="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs text-slate-600 hover:bg-slate-100"
                                        >
                                            <ExternalLink className="size-3.5" />
                                            Open
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-slate-500">
                    <span>
                        {logs.total} {logs.total === 1 ? 'change' : 'changes'}
                    </span>
                    {logs.last_page > 1 && (
                        <nav className="flex flex-wrap items-center gap-1" aria-label="Pagination">
                            {logs.links.map((link, index) =>
                                link.url === null ? (
                                    <span
                                        key={index}
                                        className="rounded-lg px-3 py-1.5 text-sm text-slate-300"
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ) : (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        preserveState
                                        aria-current={link.active ? 'page' : undefined}
                                        className={
                                            link.active
                                                ? 'rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white'
                                                : 'rounded-lg px-3 py-1.5 text-sm text-slate-600 hover:bg-white hover:text-slate-900'
                                        }
                                        dangerouslySetInnerHTML={{ __html: link.label }}
                                    />
                                ),
                            )}
                        </nav>
                    )}
                </div>
            </div>
        </>
    );
}

AttendanceLogsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Time Sheet Logs', href: '/attendance-logs' },
    ],
};
