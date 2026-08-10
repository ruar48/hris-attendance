<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Schedule;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendancePeriodSummary
{
    /**
     * Count how a period breaks down for one employee: how many days were
     * actually scheduled work, how many of those were worked vs absent, and
     * how many were leave / business trip. Shared by payroll's absence
     * deduction and the Attendance Summary report so both agree on what
     * counts as a scheduled day.
     *
     * @param  Collection<int, AttendanceRecord>  $attendance
     * @return array{scheduled: int, worked: int, absent: int, leave: int, businessTrip: int}
     */
    public static function build(
        Employee $employee,
        CarbonInterface $start,
        CarbonInterface $end,
        Collection $attendance
    ): array {
        $holidays = Holiday::mapForPeriod($start, $end);
        $schedules = Schedule::mapForPeriod($employee->id, $start, $end);

        $workedDates = $attendance
            ->where('is_incomplete', false)
            ->map(fn (AttendanceRecord $record) => $record->work_date->toDateString())
            ->all();

        $lastDay = $employee->last_working_day;
        $scheduled = 0;
        $worked = 0;
        $absent = 0;
        $leave = 0;
        $businessTrip = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var CarbonInterface $day */
            if ($lastDay !== null && $day->gt($lastDay)) {
                continue;
            }

            $schedule = $schedules->get($day->toDateString());

            if ($schedule?->status === 'leave') {
                $leave++;

                continue;
            }

            if ($schedule?->status === 'business_trip') {
                $businessTrip++;

                continue;
            }

            $isRestDay = (bool) ($schedule?->shiftType?->is_rest_day ?? false);

            if ($day->isSunday() || $holidays->has($day->toDateString()) || $isRestDay) {
                continue;
            }

            $scheduled++;

            if (in_array($day->toDateString(), $workedDates, true)) {
                $worked++;
            } else {
                $absent++;
            }
        }

        return [
            'scheduled' => $scheduled,
            'worked' => $worked,
            'absent' => $absent,
            'leave' => $leave,
            'businessTrip' => $businessTrip,
        ];
    }
}
