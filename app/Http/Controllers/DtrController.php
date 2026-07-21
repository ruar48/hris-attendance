<?php

namespace App\Http\Controllers;

use App\Models\DtrLog;
use App\Models\Employee;
use App\Services\AttendanceResolver;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DtrController extends Controller
{
    public function index(): Response
    {
        $logs = DtrLog::query()
            ->with('employee')
            ->latest('work_date')
            ->limit(50)
            ->get()
            ->map(fn (DtrLog $log) => [
                'id' => $log->id,
                'employee' => $log->employee?->full_name,
                'employee_code' => $log->employee?->employee_code,
                'work_date' => $log->work_date?->toDateString(),
                'time_in' => $log->time_in,
                'time_out' => $log->time_out,
                'reason' => $log->reason,
                'status' => $log->status,
            ]);

        return Inertia::render('dtr/index', [
            'logs' => $logs,
            'employees' => Employee::query()->active()->orderBy('employee_code')->get(['id', 'employee_code', 'first_name', 'last_name']),
            'notice' => 'DTR is a fallback only. Use it when the biometric fingerprint device has a problem.',
        ]);
    }

    public function store(Request $request, AttendanceResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'time_in' => ['required', 'date_format:H:i'],
            'time_out' => ['nullable', 'date_format:H:i', 'after:time_in'],
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $log = DtrLog::query()->updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'work_date' => $data['work_date'],
            ],
            [
                'time_in' => $data['time_in'].':00',
                'time_out' => isset($data['time_out']) ? $data['time_out'].':00' : null,
                'reason' => $data['reason'],
                'status' => 'approved',
                'approved_by' => $request->user()?->id,
            ]
        );

        $employee = Employee::query()->findOrFail($data['employee_id']);
        $resolver->resolveDay($employee, Carbon::parse($data['work_date']));

        return back()->with('success', 'DTR fallback saved. It will only apply if biometric data is missing for that day.');
    }
}
