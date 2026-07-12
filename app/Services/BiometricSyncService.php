<?php

namespace App\Services;

use App\Models\BiometricDevice;
use App\Models\BiometricLog;
use App\Models\Employee;
use App\Models\SystemNotification;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BiometricSyncService
{
    /**
     * Ingest fingerprint punches from a device payload.
     * Expected rows: biometric_user_id, punched_at, punch_type
     *
     * @param  array<int, array{biometric_user_id: string, punched_at: string, punch_type?: string}>  $punches
     * @return Collection<int, BiometricLog>
     */
    public function ingest(BiometricDevice $device, array $punches): Collection
    {
        $created = collect();

        foreach ($punches as $punch) {
            $employee = Employee::query()
                ->where('biometric_user_id', $punch['biometric_user_id'])
                ->first();

            if (! $employee) {
                continue;
            }

            $created->push(BiometricLog::query()->create([
                'employee_id' => $employee->id,
                'biometric_device_id' => $device->id,
                'punched_at' => Carbon::parse($punch['punched_at']),
                'punch_type' => $punch['punch_type'] ?? 'in',
                'raw_payload' => $punch,
            ]));
        }

        $device->update([
            'last_synced_at' => now(),
            'status' => 'online',
        ]);

        SystemNotification::query()->create([
            'title' => 'Biometric sync completed',
            'message' => "Synced {$created->count()} fingerprint punches from {$device->name}.",
            'type' => 'success',
        ]);

        return $created;
    }
}
