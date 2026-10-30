<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use App\Models\Schedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendancePeriodSummary
{
    /**
     * Count how a period breaks down for one employee: how many days were
     * actually scheduled work, how many of those were worked vs absent, and
     * how many were leave / business trip. Both the Schedule and the time
     * sheet's day marks (Day Off, Leave) are honoured, and paid leave is
     * limited per year: leave past the limit is unpaid and counts as an
     * absence. Shared by payroll's absence deduction and the Attendance
     * Summary report so both agree on what counts as a scheduled day.
     *
     * @param  Collection<int, AttendanceRecord>  $attendance
     * @return array{scheduled: int, worked: int, absent: int, leave: int, unpaidLeave: int, businessTrip: int}
     */
    public static function build(
        Employee $employee,
        CarbonInterface $start,
        CarbonInterface $end,
        Collection $attendance
    ): array {
        $holidays = Holiday::mapForPeriod($start, $end, $employee->work_location);
        $schedules = Schedule::mapForPeriod($employee->id, $start, $end);
        $marked = static::dtrMarks($employee, $start, $end);

        $workedDates = $attendance
            ->where('is_incomplete', false)
            ->map(fn (AttendanceRecord $record) => $record->work_date->toDateString())
            ->all();

        // Paid leave left this year, after the leave already taken before this period.
        $paidLeaveLeft = max(
            0,
            PayrollSetting::int('paid_leave_days_per_year') - static::leaveTakenBefore($employee, $start)
        );

        $lastDay = $employee->last_working_day;
        $scheduled = 0;
        $worked = 0;
        $absent = 0;
        $leave = 0;
        $unpaidLeave = 0;
        $businessTrip = 0;

        foreach (CarbonPeriod::create($start, $end) as $day) {
            /** @var CarbonInterface $day */
            if ($lastDay !== null && $day->gt($lastDay)) {
                continue;
            }

            $date = $day->toDateString();
            $schedule = $schedules->get($date);
            $dtrStatus = $marked->get($date);

            // A rest day on the schedule, or a day marked Day Off on the time
            // sheet. Absent and AWOL fall through and count as missed days.
            $isRestDay = (bool) ($schedule?->shiftType?->is_rest_day ?? false) || $dtrStatus === 'day_off';

            if ($day->isSunday() || $holidays->has($date) || $isRestDay) {
                continue;
            }

            if ($schedule?->status === 'business_trip') {
                $businessTrip++;

                continue;
            }

            if (static::isLeave($schedule, $dtrStatus)) {
                if ($paidLeaveLeft > 0) {
                    $paidLeaveLeft--;
                    $leave++;

                    continue;
                }

                // Out of paid leave: the day is a missed working day.
                $unpaidLeave++;
            }

            $scheduled++;

            if (in_array($date, $workedDates, true)) {
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
            'unpaidLeave' => $unpaidLeave,
            'businessTrip' => $businessTrip,
        ];
    }

    /**
     * Leave days already taken this calendar year before a date — the working
     * days marked leave on the schedule or the time sheet.
     */
    public static function leaveTakenBefore(Employee $employee, CarbonInterface $date): int
    {
        $yearStart = Carbon::parse($date)->startOfYear();
        $dayBefore = Carbon::parse($date)->subDay();

        if ($dayBefore->lt($yearStart)) {
            return 0;
        }

        $holidays = Holiday::mapForPeriod($yearStart, $dayBefore, $employee->work_location);
        $schedules = Schedule::mapForPeriod($employee->id, $yearStart, $dayBefore);
        $marked = static::dtrMarks($employee, $yearStart, $dayBefore);

        return $schedules->keys()
            ->merge($marked->keys())
            ->unique()
            ->filter(function (string $date) use ($schedules, $marked, $holidays) {
                $schedule = $schedules->get($date);

                return static::isLeave($schedule, $marked->get($date))
                    && ! Carbon::parse($date)->isSunday()
                    && ! $holidays->has($date)
                    && ! ($schedule?->shiftType?->is_rest_day ?? false);
            })
            ->count();
    }

    protected static function isLeave(?Schedule $schedule, ?string $dtrStatus): bool
    {
        return in_array($schedule?->status, Schedule::LEAVE_STATUSES, true) || $dtrStatus === 'leave';
    }

    /**
     * How each day is marked on the time sheet (Day Off, Leave, Absent, AWOL).
     *
     * @return Collection<string, string>
     */
    protected static function dtrMarks(Employee $employee, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return DtrLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get(['work_date', 'day_status'])
            ->mapWithKeys(fn (DtrLog $log) => [$log->work_date->toDateString() => $log->day_status]);
    }
}
