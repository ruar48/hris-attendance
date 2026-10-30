<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use App\Models\Schedule;
use App\Models\ShiftType;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceResolver
{
    /**
     * Resolve attendance for a date range from the encoded time sheet.
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

        // Only a holiday for the person's own site counts: a local holiday
        // elsewhere is an ordinary day here, and a local holiday here may
        // not have been passed in by the caller.
        if ($holiday === null || ! $holiday->appliesTo($employee->work_location)) {
            $holiday = Holiday::findForDate($date, $employee->work_location);
        }

        $resolved = $this->fromTimesheet($employee, $date);

        // A blank day, day off, absence or leave has nothing payable. Drop any
        // record left from an earlier encoding so a cleared cell stops paying.
        // Records from the retired biometric sync have no time sheet entry
        // behind them and are kept as history.
        if ($resolved === null) {
            AttendanceRecord::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $date->toDateString())
                ->where('source', 'dtr')
                ->delete();

            return null;
        }

        [$shiftStart, $shiftEnd] = $this->shiftFor($employee, $date);
        $graceMinutes = PayrollSetting::int('late_grace_minutes');
        $undertimeGraceMinutes = PayrollSetting::int('undertime_grace_minutes');
        $otMinimumMinutes = PayrollSetting::int('ot_minimum_minutes');
        $otMultiplier = PayrollSetting::float('ot_rate_multiplier');
        $holidayMultiplier = PayrollSetting::float('holiday_pay_multiplier');

        $isSunday = $date->isSunday();
        $isHoliday = $holiday !== null;

        // A day is only payable when BOTH times are encoded. A lone time
        // cannot tell us when the person arrived or left, so rather than guess
        // (and charge a phantom full-day lateness, or let a half day go free)
        // the day is flagged for HR to complete on the time sheet.
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
                'night_minutes' => 0,
                'is_holiday' => $isHoliday,
                'is_sunday' => $isSunday,
                'holiday_pay' => 0,
                'sunday_pay' => 0,
                'ot_pay' => 0,
                'night_diff_pay' => 0,
                'late_deduction' => 0,
                'undertime_deduction' => 0,
            ]);
        }

        // Everything below is measured in minutes from the start of the work
        // date, so a shift or a stay that runs past midnight (6:00 PM → 6:00
        // AM is 1080 → 1800) is just a larger number, not a negative span.
        $start = $this->minuteOfDay($shiftStart);
        $end = $this->minuteOfDay($shiftEnd);
        if ($end <= $start) {
            $end += 1440; // an overnight shift ends the next morning
        }

        $in = $this->minuteOfDay($resolved['time_in']);
        $out = $this->minuteOfDay($resolved['time_out']);

        // On a night shift, an arrival just after midnight is that same
        // night's shift, late — not a shift starting the evening before.
        if ($end > 1440 && $in < $start - 720) {
            $in += 1440;
        }
        if ($out < $in) {
            $out += 1440; // left after midnight
        }

        $shiftMinutes = max(1, $end - $start);
        $workedMinutes = max(0, $out - $in);

        $lateMinutes = $in - $start > $graceMinutes ? $in - $start : 0;
        // Lateness can never exceed the shift itself.
        $lateMinutes = min($lateMinutes, $shiftMinutes);

        $undertimeMinutes = $end - $out > $undertimeGraceMinutes ? $end - $out : 0;
        $undertimeMinutes = min($undertimeMinutes, $shiftMinutes);

        $rawOtMinutes = max(0, $out - $end);

        // Night differential: every minute worked between 10 PM and 6 AM.
        $nightMinutes = $this->nightMinutes($in, $out);

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
        $nightDiffPay = round(($nightMinutes / 60) * $hourly * PayrollSetting::float('night_diff_rate'), 2);
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
            'night_minutes' => $nightMinutes,
            'is_holiday' => $isHoliday,
            'is_sunday' => $isSunday,
            'holiday_pay' => $holidayPay,
            'sunday_pay' => $sundayPay,
            'ot_pay' => $otPay,
            'night_diff_pay' => $nightDiffPay,
            'late_deduction' => $lateDeduction,
            'undertime_deduction' => $undertimeDeduction,
        ];

        return $this->persist($employee, $date, $attributes);
    }

    /**
     * Recompute already-encoded days after their shift changed (a schedule
     * edit, or a new regular schedule time). Days with no time sheet entry
     * are left alone. Pass null to recompute every encoded day.
     *
     * @param  iterable<string>|null  $dates  Y-m-d
     */
    public function refreshEncodedDays(Employee $employee, ?iterable $dates = null): void
    {
        DtrLog::query()
            ->where('employee_id', $employee->id)
            ->when($dates !== null, fn ($query) => $query->whereIn(
                DB::raw('date(work_date)'),
                collect($dates)->values()->all()
            ))
            ->get()
            ->each(function (DtrLog $log) use ($employee) {
                $date = Carbon::parse($log->work_date);
                $this->resolveDay($employee, $date, Holiday::findForDate($date));
            });
    }

    /**
     * The shift a day is measured against: that day's scheduled shift, else
     * the person's regular schedule time from the Manning tab, else the
     * company default in payroll settings. A shift may run past midnight
     * (6:00 PM – 6:00 AM); its end is then on the next morning.
     *
     * @return array{0: string, 1: string}
     */
    public function shiftFor(Employee $employee, CarbonInterface $date): array
    {
        $scheduled = Schedule::query()
            ->with('shiftType')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->first()
            ?->shiftType;

        $candidates = [
            [$scheduled && ! $scheduled->is_rest_day ? $scheduled->time_in : null, $scheduled?->time_out],
            [ShiftType::toDbTime($employee->time_in_schedule), ShiftType::toDbTime($employee->time_out_schedule)],
        ];

        foreach ($candidates as [$in, $out]) {
            if ($in && $out && $this->normalizeTime($out) !== $this->normalizeTime($in)) {
                return [$this->normalizeTime($in), $this->normalizeTime($out)];
            }
        }

        return [
            $this->normalizeTime(PayrollSetting::getValue('shift_start', '08:00')),
            $this->normalizeTime(PayrollSetting::getValue('shift_end', '17:00')),
        ];
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

    /** "18:30:00" → 1110: minutes from the start of the day. */
    protected function minuteOfDay(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $this->normalizeTime($time)));

        return $hours * 60 + $minutes;
    }

    /**
     * Minutes of a stay (in minutes from the start of the work date, possibly
     * past 1440) that fall between 10 PM and 6 AM — the night before, the
     * night of, or the night after.
     */
    protected function nightMinutes(int $in, int $out): int
    {
        $total = 0;

        foreach ([-1, 0, 1] as $night) {
            $from = 22 * 60 + 1440 * $night;
            $to = $from + 8 * 60;
            $total += max(0, min($out, $to) - max($in, $from));
        }

        return $total;
    }

    /**
     * @return array{time_in: string|null, time_out: string|null, source: string}|null
     */
    protected function fromTimesheet(Employee $employee, Carbon $date): ?array
    {
        $dtr = DtrLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $date->toDateString())
            ->where('status', 'approved')
            ->first();

        if (! $dtr || $dtr->day_status !== 'worked') {
            return null;
        }

        // An overnight stay: arrived in the evening, departure written as an
        // earlier clock time (22:00 → 06:00). It ends the next morning, so
        // the arrival and departure columns are taken as written.
        $arrival = $dtr->time_in ?? $dtr->break_in;
        $departure = $dtr->time_out ?? $dtr->break_out;

        if ($arrival !== null && $departure !== null
            && $this->normalizeTime($departure) < $this->normalizeTime($arrival)) {
            return [
                'time_in' => $this->normalizeTime($arrival),
                'time_out' => $this->normalizeTime($departure),
                'source' => 'dtr',
            ];
        }

        // On the paper DTR an afternoon shift's 13:00 goes under PM Arrival,
        // and an early arrival sometimes lands under AM Departure. So the day
        // runs from the earliest time written to the latest, whichever
        // column each is in.
        $times = collect([$dtr->time_in, $dtr->break_out, $dtr->break_in, $dtr->time_out])
            ->filter()
            ->map(fn (string $time) => $this->normalizeTime($time))
            ->sort()
            ->values();

        if ($times->isEmpty()) {
            return null;
        }

        if ($times->count() === 1) {
            // A lone time is an arrival if written in an arrival column,
            // otherwise a departure — either way the day is incomplete.
            $isArrival = $dtr->time_in !== null || $dtr->break_in !== null;

            return [
                'time_in' => $isArrival ? $times[0] : null,
                'time_out' => $isArrival ? null : $times[0],
                'source' => 'dtr',
            ];
        }

        return [
            'time_in' => $times->first(),
            'time_out' => $times->last(),
            'source' => 'dtr',
        ];
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
    protected function normalizeTime(string $time): string
    {
        $time = trim($time);

        if (strlen($time) === 5) {
            return "{$time}:00";
        }

        return $time;
    }
}
