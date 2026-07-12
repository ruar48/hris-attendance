import { Head, router } from '@inertiajs/react';
import { Fingerprint, RefreshCw } from 'lucide-react';

type Device = {
    id: number;
    name: string;
    serial_number: string;
    location: string | null;
    status: string;
    last_synced_at: string | null;
};

type Log = {
    id: number;
    employee: string | null;
    employee_code: string | null;
    device: string | null;
    punched_at: string | null;
    punch_type: string;
};

type Props = {
    devices: Device[];
    logs: Log[];
    enrolled_count: number;
};

export default function BiometricsIndex({ devices, logs, enrolled_count }: Props) {
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

                <div className="grid gap-4 md:grid-cols-2">
                    {devices.map((device) => (
                        <div key={device.id} className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="mb-4 flex items-start justify-between gap-3">
                                <div className="flex items-center gap-3">
                                    <div className="rounded-xl bg-emerald-50 p-3 text-emerald-700">
                                        <Fingerprint className="size-5" />
                                    </div>
                                    <div>
                                        <h2 className="font-semibold text-slate-900">{device.name}</h2>
                                        <p className="text-sm text-slate-500">{device.location}</p>
                                    </div>
                                </div>
                                <span className="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                    {device.status}
                                </span>
                            </div>
                            <p className="text-xs text-slate-400">Serial: {device.serial_number}</p>
                            <p className="mt-1 text-xs text-slate-400">
                                Last sync: {device.last_synced_at ?? 'Never'}
                            </p>
                            <button
                                type="button"
                                onClick={() =>
                                    router.post('/biometrics/sync', {
                                        device_id: device.id,
                                    })
                                }
                                className="mt-4 inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-medium text-white hover:bg-emerald-700"
                            >
                                <RefreshCw className="size-4" />
                                Sync Fingerprints
                            </button>
                        </div>
                    ))}
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="mb-4 text-lg font-semibold text-slate-900">Recent Fingerprint Punches</h2>
                    <div className="overflow-x-auto">
                        <table className="min-w-full text-left text-sm">
                            <thead className="border-b border-slate-200 text-slate-500">
                                <tr>
                                    <th className="px-3 py-2 font-medium">Employee</th>
                                    <th className="px-3 py-2 font-medium">Device</th>
                                    <th className="px-3 py-2 font-medium">Punched At</th>
                                    <th className="px-3 py-2 font-medium">Type</th>
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
                                        <td className="px-3 py-3 text-slate-600">{log.punched_at}</td>
                                        <td className="px-3 py-3 uppercase text-slate-700">{log.punch_type}</td>
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
