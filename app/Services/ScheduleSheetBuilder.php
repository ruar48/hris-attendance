<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use App\Models\ShiftType;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Builds the SHEDULE tab of a site's workbook: one block per Monday-to-Sunday
 * week touching the month, a row per person working at the site, and in each
 * day the cell value the sheet shows and edits.
 *
 * Cell values: "s:{shift_type_id}" (working that shift), "off", "leave",
 * "business_trip", or "" (nothing assigned).
 */
class ScheduleSheetBuilder
{
    /**
     * @return array{weeks: list<array<string, mixed>>, shift_types: list<array<string, mixed>>}
     */
    public function build(Carbon $monthStart, ?string $site): array
    {
        $start = $monthStart->copy()->startOfMonth();
        $end = $monthStart->copy()->endOfMonth();
        $weekStart = $start->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $end->copy()->endOfWeek(Carbon::SUNDAY);

        $holidays = Holiday::mapForPeriod($weekStart, $weekEnd);
        $shiftTypes = ShiftType::query()->orderBy('time_in')->orderBy('code')->get();

        $employees = Employee::query()
            ->active()
            ->when($site, fn ($query) => $query->where('work_location', $site))
            ->get();

        $schedules = Schedule::query()
            ->with('shiftType')
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereDate('work_date', '>=', $weekStart->toDateString())
            ->whereDate('work_date', '<=', $weekEnd->toDateString())
            ->get()
            ->groupBy('employee_id')
            ->map(fn (Collection $rows) => $rows->keyBy(fn (Schedule $s) => $s->work_date->toDateString()));

        // Rows are ordered like the sheet: by regular start time, then name.
        $rows = $employees
            ->map(fn (Employee $employee) => [
                'employee' => $employee,
                'regular' => $this->regularShift($employee, $shiftTypes),
            ])
            ->sortBy([
                fn (array $a, array $b) => ($a['regular']?->time_in ?? '99') <=> ($b['regular']?->time_in ?? '99'),
                fn (array $a, array $b) => $a['employee']->last_name <=> $b['employee']->last_name,
            ])
            ->values();

        $weeks = [];

        for ($monday = $weekStart->copy(); $monday->lte($end); $monday->addWeek()) {
            $dates = collect(CarbonPeriod::create($monday, $monday->copy()->addDays(6)))
                ->map(fn ($day) => Carbon::parse($day));

            $weeks[] = [
                'days' => $dates->map(fn (Carbon $d) => [
                    'date' => $d->toDateString(),
                    'label' => $d->format('F j, Y'),
                    'dow' => strtoupper($d->format('D')),
                    'in_month' => $d->between($start, $end),
                    'holiday' => $holidays->get($d->toDateString())?->name,
                ])->all(),
                'rows' => $rows->map(function (array $row) use ($dates, $schedules) {
                    /** @var Employee $employee */
                    $employee = $row['employee'];
                    /** @var Collection<string, Schedule> $byDate */
                    $byDate = $schedules->get($employee->id, collect());
                    $week = $dates->map(fn (Carbon $d) => $byDate->get($d->toDateString()));

                    return [
                        'employee_id' => $employee->id,
                        'name' => "{$employee->first_name} {$employee->last_name}",
                        'position' => $employee->position,
                        'time' => $row['regular'] ? $this->label($row['regular']) : null,
                        'regular_shift_type_id' => $row['regular']?->id,
                        'cells' => $week->map(fn (?Schedule $s) => $this->cellValue($s))->values()->all(),
                        'remarks' => $week->filter(fn (?Schedule $s) => filled($s?->notes))
                            ->map(fn (Schedule $s) => $s->work_date->format('n/j/Y').' '.$s->notes)
                            ->values()
                            ->all(),
                    ];
                })->all(),
            ];
        }

        return [
            'weeks' => $weeks,
            'shift_types' => $shiftTypes
                ->map(fn (ShiftType $shift) => [
                    'id' => $shift->id,
                    'label' => $shift->is_rest_day ? $shift->name : $this->label($shift),
                    'is_rest_day' => $shift->is_rest_day,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * The shift code matching the person's regular Manning schedule time.
     *
     * @param  Collection<int, ShiftType>  $shiftTypes
     */
    public function regularShift(Employee $employee, Collection $shiftTypes): ?ShiftType
    {
        $in = ShiftType::toDbTime($employee->time_in_schedule);
        $out = ShiftType::toDbTime($employee->time_out_schedule);

        if ($in === null || $out === null) {
            return null;
        }

        return $shiftTypes->first(
            fn (ShiftType $shift) => ! $shift->is_rest_day && $shift->time_in === $in && $shift->time_out === $out
        );
    }

    public function cellValue(?Schedule $schedule): string
    {
        return match (true) {
            $schedule === null => '',
            $schedule->status === 'leave' => 'leave',
            $schedule->status === 'business_trip' => 'business_trip',
            (bool) $schedule->shiftType?->is_rest_day => 'off',
            $schedule->shift_type_id !== null => "s:{$schedule->shift_type_id}",
            default => '',
        };
    }

    protected function label(ShiftType $shift): string
    {
        if (! $shift->time_in || ! $shift->time_out) {
            return $shift->name;
        }

        return Carbon::parse($shift->time_in)->format('g:i A').' - '.Carbon::parse($shift->time_out)->format('g:i A');
    }
}
