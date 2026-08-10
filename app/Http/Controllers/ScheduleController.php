<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\ShiftType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $start = $request->date('start_date') ?? Carbon::today()->subDays(13);
        $end = $request->date('end_date') ?? Carbon::today();

        $holidays = Holiday::mapForPeriod($start, $end);

        $days = collect(CarbonPeriod::create($start, $end))
            ->map(fn (Carbon $day) => [
                'date' => $day->toDateString(),
                'day' => $day->day,
                'dow' => $day->format('D'),
                'is_sunday' => $day->isSunday(),
                'is_holiday' => $holidays->has($day->toDateString()),
            ]);

        // whereBetween with plain "Y-m-d" strings would silently miss the
        // range's last day: work_date is cast to `date`, which Eloquent
        // stores as "Y-m-d 00:00:00", and that string sorts *after* the bare
        // "Y-m-d" upper bound lexicographically.
        $cells = Schedule::query()
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->mapWithKeys(fn (Schedule $schedule) => [
                "{$schedule->employee_id}-{$schedule->work_date->toDateString()}" => [
                    'status' => $schedule->status,
                    'shift_type_id' => $schedule->shift_type_id,
                ],
            ]);

        return Inertia::render('schedules/index', [
            'employees' => Employee::query()
                ->active()
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'first_name', 'last_name', 'department']),
            'shiftTypes' => ShiftType::query()->orderBy('code')->get(),
            'days' => $days,
            'cells' => $cells,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cells' => ['required', 'array'],
            'cells.*.employee_id' => ['required', 'exists:employees,id'],
            'cells.*.work_date' => ['required', 'date'],
            'cells.*.status' => ['nullable', 'in:working,leave,business_trip'],
            'cells.*.shift_type_id' => ['nullable', 'exists:shift_types,id'],
        ]);

        $saved = 0;

        DB::transaction(function () use ($data, &$saved) {
            foreach ($data['cells'] as $cell) {
                // work_date is cast to `date`, which Eloquent stores as a full
                // "Y-m-d 00:00:00" string — a plain "Y-m-d" match via where()
                // never finds the existing row, so both the delete and the
                // updateOrCreate below must use whereDate() instead.
                $existing = Schedule::query()
                    ->where('employee_id', $cell['employee_id'])
                    ->whereDate('work_date', $cell['work_date'])
                    ->first();

                if (empty($cell['status'])) {
                    $existing?->delete();

                    continue;
                }

                $attributes = [
                    'status' => $cell['status'],
                    'shift_type_id' => $cell['status'] === 'working' ? ($cell['shift_type_id'] ?? null) : null,
                ];

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    Schedule::query()->create([
                        'employee_id' => $cell['employee_id'],
                        'work_date' => $cell['work_date'],
                        ...$attributes,
                    ]);
                }

                $saved++;
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Schedule saved ({$saved} entr".($saved === 1 ? 'y' : 'ies').' updated).',
        ]);

        return back();
    }
}
