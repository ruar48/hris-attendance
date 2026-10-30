<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OptionList;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Services\AttendanceResolver;
use App\Services\ScheduleSheetBuilder;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A site's monthly schedule, laid out like the SHEDULE tab of the client's
 * workbook: weekly blocks, a row per person, their name on each day worked.
 */
class ScheduleController extends Controller
{
    public function index(Request $request, ScheduleSheetBuilder $builder): Response
    {
        $site = $this->site($request);
        $month = $this->month($request);

        return Inertia::render('schedules/index', [
            ...$builder->build($month, $site),
            'sites' => OptionList::query()
                ->category('work_location')
                ->orderBy('sort_order')
                ->get()
                ->map(fn (OptionList $option) => ['value' => $option->value, 'label' => $option->label])
                ->all(),
            'site' => $site ?? 'all',
            'month' => $month->format('Y-m'),
            'shiftTypes' => ShiftType::query()->orderBy('code')->get(),
        ]);
    }

    /**
     * Save edited cells. Each cell's value is what the sheet shows: a shift
     * ("s:{id}"), "off", a Schedule::STATUS_LABELS key, or "" to clear the day.
     */
    public function bulkUpdate(Request $request, AttendanceResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'cells' => ['required', 'array'],
            'cells.*.employee_id' => ['required', 'exists:employees,id'],
            'cells.*.work_date' => ['required', 'date_format:Y-m-d'],
            'cells.*.value' => [
                'present', 'nullable', 'string',
                'regex:/^(|off|s:\d+|'.implode('|', array_keys(Schedule::STATUS_LABELS)).')$/',
            ],
        ]);

        $saved = 0;

        DB::transaction(function () use ($data, $resolver, &$saved) {
            foreach ($data['cells'] as $cell) {
                // work_date is cast to `date`, which Eloquent stores as a full
                // "Y-m-d 00:00:00" string — a plain "Y-m-d" match via where()
                // never finds the existing row, so this must use whereDate().
                $existing = Schedule::query()
                    ->where('employee_id', $cell['employee_id'])
                    ->whereDate('work_date', $cell['work_date'])
                    ->first();

                $attributes = $this->attributesFor((string) ($cell['value'] ?? ''));

                if ($attributes === null) {
                    $existing?->delete();
                } elseif ($existing) {
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

            // Late, undertime and OT are measured against the day's shift.
            collect($data['cells'])
                ->groupBy('employee_id')
                ->each(fn ($cells, $employeeId) => $resolver->refreshEncodedDays(
                    Employee::query()->findOrFail((int) $employeeId),
                    $cells->pluck('work_date')
                ));
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Schedule saved ({$saved} ".($saved === 1 ? 'day' : 'days').' updated).',
        ]);

        return back();
    }

    /**
     * @return array{status: string, shift_type_id: int|null}|null
     */
    protected function attributesFor(string $value): ?array
    {
        return match (true) {
            $value === '' => null,
            $value === 'off' => ['status' => 'working', 'shift_type_id' => ShiftType::restDay()->id],
            array_key_exists($value, Schedule::STATUS_LABELS) => ['status' => $value, 'shift_type_id' => null],
            default => ['status' => 'working', 'shift_type_id' => (int) substr($value, 2)],
        };
    }

    protected function site(Request $request): ?string
    {
        $sites = OptionList::values('work_location');
        $requested = $request->string('site')->toString();

        if ($requested === 'all') {
            return null;
        }

        return in_array($requested, $sites, true) ? $requested : ($sites[0] ?? null);
    }

    protected function month(Request $request): Carbon
    {
        $month = $request->string('month')->toString();

        return preg_match('/^\d{4}-\d{2}$/', $month)
            ? Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay()
            : Carbon::today()->startOfMonth();
    }
}
