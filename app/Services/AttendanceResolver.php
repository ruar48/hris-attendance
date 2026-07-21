<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\BiometricLog;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class AttendanceResolver
{
    /**
     * Resolve attendance for a date range.
     * Biometric fingerprint logs are primary; approved DTR is used only as fallback.
     *
     * @return Collection<int, AttendanceRecord>
     */
    public function syncPeriod(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $employees = Employee::query()->forPayrollPeriod($start)->get();
        $holidays = Holiday::mapForPeriod($start, $end);

        $records = collect();

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($start, $end) as $day) {
                /** @var CarbonInterface $day */
                if ($day->isWeekend() && ! $day->isSunday()) {
                    continue;
                }

                // Nothing to resolve after someone's last working day.
                if ($employee->last_working_day !== null && $day->gt($employee->last_working_day)) {
                    continue;
                }

                $record = $this->resolveDay($employee, Carbon::parse($day), $holidays->get($day->toDateString()));
                if ($record) {
                    $records->push($record);
                }
            }
        }

        return $records;
    }

    public function resolveDay(Employee $employee, CarbonInterface $date, ?Holiday $holiday = null): ?AttendanceRecord
    {
        $date = Carbon::parse($date);
        $resolved = $this->fromBiometric($employee, $date);

        if ($resolved === null) {
            $resolved = $this->fromDtrFallback($employee, $date);
        }

        if ($resolved === null) {
            return null;
        }

        $shiftStart = $this->normalizeTime(PayrollSetting::getValue('shift_start', '08:00'));
        $shiftEnd = $this->normalizeTime(PayrollSetting::getValue('shift_end', '17:00'));
        $graceMinutes = PayrollSetting::int('late_grace_minutes');
        $undertimeGraceMinutes = PayrollSetting::int('undertime_grace_minutes');
        $otMinimumMinutes = PayrollSetting::int('ot_minimum_minutes');
        $otMultiplier = PayrollSetting::float('ot_rate_multiplier');
        $holidayMultiplier = PayrollSetting::float('holiday_pay_multiplier');

        $lateMinutes = $this->calculateLateMinutes($resolved['time_in'], $shiftStart, $graceMinutes);
        $undertimeMinutes = $this->calculateUndertimeMinutes(
            $resolved['time_out'],
            $shiftEnd,
            $undertimeGraceMinutes
        );
        $rawOtMinutes = $this->minutesBeyond($resolved['time_out'], $shiftEnd);

        // OT only counts once worked time beyond shift end reaches the minimum threshold.
        $otMinutes = $rawOtMinutes >= $otMinimumMinutes ? $rawOtMinutes : 0;

        $isSunday = $date->isSunday();
        $isHoliday = $holiday !== null;
        $hourly = $this->resolveHourlyRate($employee);

        $holidayPay = 0.0;
        if ($isHoliday) {
            $multiplier = (float) ($holiday->pay_multiplier ?? $holidayMultiplier);
            $holidayPay = round((float) $employee->daily_rate * max(0, $multiplier - 1), 2);
        }

        $sundayPay = $isSunday ? $this->resolveSundayRouteAmount($employee) : 0.0;
        $otPay = round(($otMinutes / 60) * $hourly * $otMultiplier, 2);
        $lateDeduction = round(($lateMinutes / 60) * $hourly, 2);
        $billableUndertime = $this->billableUndertimeMinutes($undertimeMinutes);
        $undertimeDeduction = round(($billableUndertime / 60) * $hourly, 2);

        $attributes = [
            'time_in' => $resolved['time_in'],
            'time_out' => $resolved['time_out'],
            'source' => $resolved['source'],
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $undertimeMinutes,
            'ot_minutes' => $otMinutes,
            'is_holiday' => $isHoliday,
            'is_sunday' => $isSunday,
            'holiday_pay' => $holidayPay,
            'sunday_pay' => $sundayPay,
            'ot_pay' => $otPay,
            'late_deduction' => $lateDeduction,
            'undertime_deduction' => $undertimeDeduction,
        ];

        $record = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first();

        if ($record) {
            $record->update($attributes);

            return $record->fresh();
        }

        return AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $date->toDateString(),
            ...$attributes,
        ]);
    }

    protected function resolveHourlyRate(Employee $employee): float
    {
        if (! PayrollSetting::bool('ot_use_employee_hourly')) {
            $fixed = PayrollSetting::float('ot_fixed_hourly_rate');
            if ($fixed > 0) {
                return $fixed;
            }
        }

        $hourly = (float) $employee->hourly_rate;
        if ($hourly > 0) {
            return $hourly;
        }

        // Fallback: basic monthly / 22 days / 8 hours
        return round(((float) $employee->basic_salary) / 22 / 8, 2);
    }

    protected function resolveSundayRouteAmount(Employee $employee): float
    {
        if (PayrollSetting::bool('sunday_route_use_employee_rate') && (float) $employee->sunday_route_rate > 0) {
            return (float) $employee->sunday_route_rate;
        }

        return PayrollSetting::float('sunday_route_default_amount');
    }

    /**
     * @return array{time_in: string, time_out: string|null, source: string}|null
     */
    protected function fromBiometric(Employee $employee, Carbon $date): ?array
    {
        $logs = BiometricLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('punched_at', $date->toDateString())
            ->orderBy('punched_at')
            ->get();

        if ($logs->isEmpty()) {
            return null;
        }

        $timeIn = $logs->firstWhere('punch_type', 'in') ?? $logs->first();
        $timeOut = $logs->where('punch_type', 'out')->last()
            ?? ($logs->count() > 1 ? $logs->last() : null);

        return [
            'time_in' => $timeIn->punched_at->format('H:i:s'),
            'time_out' => $timeOut?->punched_at->format('H:i:s'),
            'source' => 'biometric',
        ];
    }

    /**
     * @return array{time_in: string, time_out: string|null, source: string}|null
     */
    protected function fromDtrFallback(Employee $employee, Carbon $date): ?array
    {
        $dtr = DtrLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->where('status', 'approved')
            ->first();

        if (! $dtr || ! $dtr->time_in) {
            return null;
        }

        return [
            'time_in' => $dtr->time_in,
            'time_out' => $dtr->time_out,
            'source' => 'dtr',
        ];
    }

    protected function calculateLateMinutes(?string $timeIn, string $shiftStart, int $graceMinutes): int
    {
        if (! $timeIn) {
            return 0;
        }

        $diff = $this->minutesBeyond($timeIn, $shiftStart);

        if ($diff <= $graceMinutes) {
            return 0;
        }

        return $diff;
    }

    protected function calculateUndertimeMinutes(?string $timeOut, string $shiftEnd, int $graceMinutes): int
    {
        if (! $timeOut) {
            return 0;
        }

        $diff = $this->minutesBefore($timeOut, $shiftEnd);

        if ($diff <= $graceMinutes) {
            return 0;
        }

        return $diff;
    }

    /**
     * Convert raw undertime minutes into billable minutes based on company rule.
     */
    protected function billableUndertimeMinutes(int $undertimeMinutes): int
    {
        if ($undertimeMinutes <= 0) {
            return 0;
        }

        $unit = (string) PayrollSetting::getValue('undertime_deduction_unit', 'exact');

        return match ($unit) {
            '30_minutes' => (int) (ceil($undertimeMinutes / 30) * 30),
            'hour' => (int) (ceil($undertimeMinutes / 60) * 60),
            default => $undertimeMinutes,
        };
    }

    protected function minutesBeyond(?string $actualTime, string $reference): int
    {
        if (! $actualTime) {
            return 0;
        }

        $referenceCarbon = Carbon::createFromFormat('H:i:s', $this->normalizeTime($reference));
        $actual = Carbon::createFromFormat('H:i:s', $this->normalizeTime($actualTime));
        $diff = $referenceCarbon->diffInMinutes($actual, false);

        return $diff > 0 ? (int) $diff : 0;
    }

    protected function minutesBefore(?string $actualTime, string $reference): int
    {
        if (! $actualTime) {
            return 0;
        }

        $referenceCarbon = Carbon::createFromFormat('H:i:s', $this->normalizeTime($reference));
        $actual = Carbon::createFromFormat('H:i:s', $this->normalizeTime($actualTime));
        $diff = $referenceCarbon->diffInMinutes($actual, false);

        return $diff < 0 ? (int) abs($diff) : 0;
    }

    protected function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (strlen($time) === 5) {
            return "{$time}:00";
        }

        return $time;
    }
}
