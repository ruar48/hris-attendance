<?php

namespace App\Http\Controllers;

use App\Models\BiometricDevice;
use App\Models\BiometricLog;
use App\Models\Employee;
use App\Services\AttendanceResolver;
use App\Services\BiometricSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BiometricController extends Controller
{
    public function index(): Response
    {
        $devices = BiometricDevice::query()
            ->latest()
            ->get()
            ->map(fn (BiometricDevice $device) => [
                'id' => $device->id,
                'name' => $device->name,
                'serial_number' => $device->serial_number,
                'location' => $device->location,
                'status' => $device->status,
                'last_synced_at' => $device->last_synced_at?->toDateTimeString(),
            ]);

        $logs = BiometricLog::query()
            ->with(['employee', 'device'])
            ->latest('punched_at')
            ->limit(50)
            ->get()
            ->map(fn (BiometricLog $log) => [
                'id' => $log->id,
                'employee' => $log->employee?->full_name,
                'employee_code' => $log->employee?->employee_code,
                'device' => $log->device?->name,
                'punched_at' => $log->punched_at?->toDateTimeString(),
                'punch_type' => $log->punch_type,
            ]);

        return Inertia::render('biometrics/index', [
            'devices' => $devices,
            'logs' => $logs,
            'enrolled_count' => Employee::query()->whereNotNull('biometric_user_id')->count(),
        ]);
    }

    public function sync(Request $request, BiometricSyncService $sync, AttendanceResolver $resolver): RedirectResponse
    {
        $device = BiometricDevice::query()->findOrFail($request->integer('device_id'));

        // Demo ingest: generate today's sample punches for enrolled employees if no payload provided.
        $punches = $request->input('punches');

        if (! is_array($punches) || $punches === []) {
            $punches = Employee::query()
                ->active()
                ->whereNotNull('biometric_user_id')
                ->get()
                ->flatMap(function (Employee $employee) {
                    return [
                        [
                            'biometric_user_id' => $employee->biometric_user_id,
                            'punched_at' => now()->startOfDay()->setTime(8, rand(0, 20))->toDateTimeString(),
                            'punch_type' => 'in',
                        ],
                        [
                            'biometric_user_id' => $employee->biometric_user_id,
                            'punched_at' => now()->startOfDay()->setTime(17, rand(0, 90))->toDateTimeString(),
                            'punch_type' => 'out',
                        ],
                    ];
                })
                ->all();
        }

        $created = $sync->ingest($device, $punches);
        $resolver->syncPeriod(now()->startOfMonth(), now()->endOfMonth());

        return back()->with('success', "Synced {$created->count()} biometric punches. Attendance resolved with fingerprint as primary.");
    }
}
