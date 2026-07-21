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

        // The DTR fallback also covers a half-captured biometric day, which is
        // exactly the case HR files a correction for.
        if ($resolved === null || $resolved['time_in'] === null || $resolved['time_out'] === null) {
            $resolved = $this->fromDtrFallback($employee, $date) ?? $resolved;
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

        $isSunday = $date->isSunday();
        $isHoliday = $holiday !== null;

        // A day is only payable when BOTH punches are present. A lone punch
        // cannot tell us when the person arrived or left, so rather than guess
        // (and charge a phantom full-day lateness, or let a half day go free)
        // the day is flagged for HR to correct through the DTR fallback.
        if ($resolved['time_in'] === null || $resolved['time_out'] === null) {
            return $this->persist($employee, $date, [
                'time_in' => $resolved['time_in'],
                'time_out' => $resolved['time_out'],
                'source' => $resolved['source'],
                'is_incomplete' => true,
                'worked_minutes' => 0,
                'late_minutes' => 0,
                'undertime_minutes' => 0,
                'ot_minutes' => 0,
                'is_holiday' => $isHoliday,
                'is_sunday' => $isSunday,
                'holiday_pay' => 0,
                'sunday_pay' => 0,
                'ot_pay' => 0,
                'late_deduction' => 0,
                'undertime_deduction' => 0,
            ]);
        }

        $shiftMinutes = max(1, $this->minutesBetween($shiftStart, $shiftEnd));
        $workedMinutes = max(0, $this->minutesBetween($resolved['time_in'], $resolved['time_out']));

        $lateMinutes = $this->calculateLateMinutes($resolved['time_in'], $shiftStart, $graceMinutes);
        // Lateness can never exceed the shift itself.
        $lateMinutes = min($lateMinutes, $shiftMinutes);

        $undertimeMinutes = $this->calculateUndertimeMinutes(
            $resolved['time_out'],
            $shiftEnd,
            $undertimeGraceMinutes
        );
        $undertimeMinutes = min($undertimeMinutes, $shiftMinutes);

        $rawOtMinutes = $this->minutesBeyond($resolved['time_out'], $shiftEnd);

        // OT only counts once worked time beyond shift end reaches the minimum threshold.
        $otMinutes = $rawOtMinutes >= $otMinimumMinutes ? $rawOtMinutes : 0;

        // Deductions are charged at the employee's own hourly rate. The OT rate
        // setting deliberately does not apply here — it must never reprice a
        // late or undertime deduction.
        $hourly = $this->deductionHourlyRate($employee);
        $otHourly = $this->overtimeHourlyRate($employee);

        // The share of the shift actually worked, used to prorate flat premiums.
        $workedShare = min(1.0, $workedMinutes / $shiftMinutes);

        $holidayPay = 0.0;
        if ($isHoliday) {
            $multiplier = $this->holidayMultiplier($holiday, $holidayMultiplier);
            $holidayPay = round((float) $employee->daily_rate * max(0, $multiplier - 1) * $workedShare, 2);
        }

        $sundayPay = $isSunday
            ? round($this->resolveSundayRouteAmount($employee) * $workedShare, 2)
            : 0.0;

        // A holiday that lands on a Sunday pays the better of the two premiums,
        // never both stacked on top of each other.
        if ($isHoliday && $isSunday) {
            $holidayPay = max($holidayPay, $sundayPay);
            $sundayPay = 0.0;
        }

        $otPay = round(($otMinutes / 60) * $otHourly * $otMultiplier, 2);
        $lateDeduction = round(($lateMinutes / 60) * $hourly, 2);

        // Store the minutes actually charged so the payslip reconciles against
        // the stored figure under every rounding mode.
        $billableUndertime = min($this->billableUndertimeMinutes($undertimeMinutes), $shiftMinutes);
        $undertimeDeduction = round(($billableUndertime / 60) * $hourly, 2);

        $attributes = [
            'time_in' => $resolved['time_in'],
            'time_out' => $resolved['time_out'],
            'source' => $resolved['source'],
            'is_incomplete' => false,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'undertime_minutes' => $billableUndertime,
            'ot_minutes' => $otMinutes,
            'is_holiday' => $isHoliday,
            'is_sunday' => $isSunday,
            'holiday_pay' => $holidayPay,
            'sunday_pay' => $sundayPay,
            'ot_pay' => $otPay,
            'late_deduction' => $lateDeduction,
            'undertime_deduction' => $undertimeDeduction,
        ];

        return $this->persist($employee, $date, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function persist(Employee $employee, Carbon $date, array $attributes): AttendanceRecord
    {
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

    /**
     * Rate used for late and undertime deductions — always the employee's own.
     */
    protected function deductionHourlyRate(Employee $employee): float
    {
        $hourly = (float) $employee->hourly_rate;

        if ($hourly > 0) {
            return $hourly;
        }

        // Fallback: monthly basic over the configured working days and shift hours.
        return round(((float) $employee->basic_salary) / 22 / 8, 2);
    }

    /**
     * Rate used for overtime, which may be overridden in payroll settings.
     */
    protected function overtimeHourlyRate(Employee $employee): float
    {
        if (! PayrollSetting::bool('ot_use_employee_hourly')) {
            $fixed = PayrollSetting::float('ot_fixed_hourly_rate');

            if ($fixed > 0) {
                return $fixed;
            }
        }

        return $this->deductionHourlyRate($employee);
    }

    /**
     * A holiday's own multiplier wins, but only when it is a usable one —
     * a stored 0 must not silently wipe out the premium.
     */
    protected function holidayMultiplier(?Holiday $holiday, float $default): float
    {
        $own = $holiday?->pay_multiplier === null ? 0.0 : (float) $holiday->pay_multiplier;

        return $own > 0 ? $own : $default;
    }

    protected function resolveSundayRouteAmount(Employee $employee): float
    {
        if (PayrollSetting::bool('sunday_route_use_employee_rate')) {
            // Route pay is per-employee: someone with no route rate is not a
            // route driver and must not collect the default amount.
            return (float) $employee->sunday_route_rate;
        }

        return PayrollSetting::float('sunday_route_default_amount');
    }

    protected function minutesBetween(string $from, string $to): int
    {
        $start = Carbon::createFromFormat('H:i:s', $this->normalizeTime($from));
        $end = Carbon::createFromFormat('H:i:s', $this->normalizeTime($to));

        return (int) $start->diffInMinutes($end, false);
    }

    /**
     * @return array{time_in: string|null, time_out: string|null, source: string}|null
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

        // Only a real "in" punch may set time_in. Falling back to the first log
        // of the day turns a lone evening out-punch into a 9-hour lateness.
        $timeIn = $logs->firstWhere('punch_type', 'in');
        $timeOut = $logs->where('punch_type', 'out')->last();

        return [
            'time_in' => $timeIn?->punched_at->format('H:i:s'),
            'time_out' => $timeOut?->punched_at->format('H:i:s'),
            'source' => 'biometric',
        ];
    }

    /**
     * @return array{time_in: string|null, time_out: string|null, source: string}|null
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
