<?php

namespace App\Http\Controllers;

use App\Models\BiometricLog;
use App\Models\Employee;
use App\Services\AttendanceExcelImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BiometricController extends Controller
{
    public function index(): Response
    {
        // Pull a wider window of raw punches, then collapse each employee's
        // day down to one row — first "in" and last "out" — the same pairing
        // AttendanceResolver::fromBiometric() uses to resolve a day's time in/out.
        $logs = BiometricLog::query()
            ->with(['employee', 'device'])
            ->latest('punched_at')
            ->limit(500)
            ->get()
            ->groupBy(fn (BiometricLog $log) => $log->employee_id.'|'.$log->punched_at->toDateString())
            ->map(function ($group) {
                /** @var BiometricLog $first */
                $first = $group->first();
                $timeIn = $group->where('punch_type', 'in')->sortBy('punched_at')->first();
                $timeOut = $group->where('punch_type', 'out')->sortByDesc('punched_at')->first();

                return [
                    'id' => $first->id,
                    'employee' => $first->employee?->full_name,
                    'employee_code' => $first->employee?->employee_code,
                    'device' => $first->device?->name,
                    'date' => $first->punched_at->toDateString(),
                    'time_in' => $timeIn?->punched_at->format('H:i'),
                    'time_out' => $timeOut?->punched_at->format('H:i'),
                ];
            })
            ->sortByDesc('date')
            ->take(50)
            ->values();

        return Inertia::render('biometrics/index', [
            'logs' => $logs,
            'enrolled_count' => Employee::query()->whereNotNull('biometric_user_id')->count(),
            'importResult' => session('importResult'),
        ]);
    }

    public function import(Request $request, AttendanceExcelImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ]);

        try {
            $result = $importer->import($data['file'], $request->user()?->id);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $summary = "{$result['created']} new, {$result['updated']} updated, {$result['duplicates']} already imported"
            ." — {$result['employees']} employee".($result['employees'] === 1 ? '' : 's').'.';

        Inertia::flash('toast', [
            'type' => $result['duplicates'] > 0 && $result['created'] === 0 && $result['updated'] === 0
                ? 'info'
                : 'success',
            'message' => $summary,
        ]);

        return redirect()->route('biometrics.index')->with('importResult', $result);
    }
}
