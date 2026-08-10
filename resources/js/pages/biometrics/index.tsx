import { Head, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Info, Upload } from 'lucide-react';

type Log = {
    id: number;
    employee: string | null;
    employee_code: string | null;
    device: string | null;
    date: string;
    time_in: string | null;
    time_out: string | null;
};

type ImportResult = {
    created: number;
    updated: number;
    duplicates: number;
    employees: number;
    warnings: string[];
    range: { start: string; end: string } | null;
};

type Props = {
    logs: Log[];
    enrolled_count: number;
    importResult: ImportResult | null;
};

export default function BiometricsIndex({ logs, enrolled_count, importResult }: Props) {
    const importForm = useForm<{ file: File | null }>({ file: null });

    const submitImport = (event: React.FormEvent) => {
        event.preventDefault();

        if (!importForm.data.file) {
            return;
        }

        importForm.post('/biometrics/import', {
            forceFormData: true,
            onSuccess: () => importForm.setData('file', null),
        });
    };

    return (
        <>
            <Head title="Biometrics" />
            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-slate-900">Biometric Fingerprint</h1>
                        <p className="text-sm text-slate-500">
                            Primary time source for payroll. DTR is only used when fingerprint sync fails.
                        </p>
                    </div>
                    <div className="rounded-full bg-emerald-50 px-4 py-2 text-sm font-medium text-emerald-800">
                        {enrolled_count} employees enrolled
                    </div>
                </div>

                <div className="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                    <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                    <div>
                        <p className="font-semibold">Import an attendance log export</p>
                        <p className="mt-1">
                            Upload the export straight from the biometric software. A single-sheet file
                            (just the attendance log) works, or a workbook with a sheet named{' '}
                            <strong>"Attendance Logs"</strong> among others. Expected layout: a "Date"
                            cell giving the range (e.g. <code>2026-07-16 ~ 2026-08-04</code>), then a
                            repeating block per employee — an <strong>ID / Name / Dept</strong> header
                            row, a day-of-month row, a weekday row, and a row of cells each holding time
                            in and time out on two lines.
                        </p>
                    </div>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Import Attendance Log</h2>
                    <form className="flex flex-wrap items-end gap-4" onSubmit={submitImport}>
                        <label className="text-sm">
                            <span className="mb-1.5 block text-slate-600">Excel File (.xlsx, .xls)</span>
                            <input
                                type="file"
                                accept=".xlsx,.xls"
                                className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-xl file:border-0 file:bg-slate-900 file:px-4 file:py-2.5 file:text-sm file:font-semibold file:text-white hover:file:bg-slate-800"
                                onChange={(e) => importForm.setData('file', e.target.files?.[0] ?? null)}
                            />
                            {importForm.errors.file && (
                                <span className="mt-1 block text-xs text-rose-600">
                                    {importForm.errors.file}
                                </span>
                            )}
                        </label>
                        <button
                            type="submit"
                            disabled={importForm.processing || !importForm.data.file}
                            className="inline-flex items-center gap-2 rounded-xl bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-700 disabled:opacity-50"
                        >
                            <Upload className="size-4" />
                            {importForm.processing ? 'Importing…' : 'Import'}
                        </button>
                    </form>

                    {importResult && (
                        <div className="mt-5 border-t border-slate-100 pt-5">
                            <div className="mb-3 flex items-center gap-2">
                                <div
                                    className={
                                        importResult.created === 0 && importResult.updated === 0
                                            ? 'rounded-xl bg-slate-100 p-2 text-slate-500'
                                            : 'rounded-xl bg-emerald-100 p-2 text-emerald-600'
                                    }
                                >
                                    {importResult.created === 0 && importResult.updated === 0 ? (
                                        <Info className="size-4" />
                                    ) : (
                                        <CheckCircle2 className="size-4" />
                                    )}
                                </div>
                                <h3 className="font-semibold text-slate-900">Last Import Result</h3>
                            </div>
                            <p className="text-sm text-slate-700">
                                <strong>{importResult.created}</strong> new,{' '}
                                <strong>{importResult.updated}</strong> updated,{' '}
                                <strong>{importResult.duplicates}</strong> already imported (unchanged) across{' '}
                                <strong>{importResult.employees}</strong> employee
                                {importResult.employees === 1 ? '' : 's'}
                                {importResult.range && (
                                    <>
                                        {' '}
                                        for <strong>{importResult.range.start}</strong> to{' '}
                                        <strong>{importResult.range.end}</strong>
                                    </>
                                )}
                                .
                            </p>
                            {importResult.created === 0 && importResult.updated === 0 && importResult.duplicates > 0 && (
                                <p className="mt-1 text-sm text-slate-500">
                                    This file's data was already imported — nothing changed.
                                </p>
                            )}

                            {importResult.warnings.length > 0 && (
                                <div className="mt-4">
                                    <p className="mb-2 text-sm font-semibold text-amber-700">
                                        {importResult.warnings.length} note
                                        {importResult.warnings.length === 1 ? '' : 's'}
                                    </p>
                                    <ul className="max-h-64 space-y-1 overflow-y-auto rounded-xl border border-amber-100 bg-amber-50 p-3 text-xs text-amber-900">
                                        {importResult.warnings.map((warning, index) => (
                                            <li key={index}>{warning}</li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Recent Fingerprint Punches</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Device</th>
                                    <th className="px-3 py-2 font-medium">Date</th>
                                    <th className="px-3 py-2 font-medium">Time In</th>
                                    <th className="px-3 py-2 font-medium">Time Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                {logs.map((log) => (
                                    <tr key={log.id} className="border-b border-slate-100 last:border-0">
                                        <td className="px-3 py-3">
                                            <div className="font-medium text-slate-800">{log.employee}</div>
                                            <div className="text-xs text-slate-400">{log.employee_code}</div>
                                        </td>
                                        <td className="px-3 py-3 text-slate-600">{log.device}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.date}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.time_in ?? '—'}</td>
                                        <td className="px-3 py-3 text-slate-600">{log.time_out ?? '—'}</td>
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

BiometricsIndex.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Biometrics', href: '/biometrics' },
    ],
};
