<?php

namespace App\Services;

use App\Exceptions\PayrollAlreadyProcessed;
use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\SystemNotification;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollCalculator
{
    /** Special half value for the standalone 13th-month period (not a kinsena cutoff). */
    public const THIRTEENTH_MONTH_HALF = 3;

    public function __construct(
        protected AttendanceResolver $attendanceResolver
    ) {}

    public function run(PayrollPeriod $period, bool $syncAttendance = true): PayrollRun
    {
        $this->guardAgainstReprocessing($period);

        return DB::transaction(function () use ($period, $syncAttendance) {
            if ($this->isThirteenthMonthPeriod($period)) {
                return $this->runThirteenthMonthPeriod($period);
            }

            if ($syncAttendance) {
                $this->attendanceResolver->syncPeriod(
                    $period->start_date,
                    $period->end_date
                );
            }

            $run = PayrollRun::query()->create([
                'payroll_period_id' => $period->id,
                'status' => 'in_progress',
            ]);

            $employees = Employee::query()->forPayrollPeriod($period->start_date)->get();
            $totalPayroll = 0.0;
            $totalDeductions = 0.0;

            foreach ($employees as $employee) {
                $payslip = $this->buildRegularPayslip($run, $employee, $period);
                $totalPayroll += (float) $payslip->total_earnings;
                $totalDeductions += (float) $payslip->total_deductions;
            }

            $run->update([
                'total_employees' => $employees->count(),
                'total_payroll' => round($totalPayroll, 2),
                'total_deductions' => round($totalDeductions, 2),
                'net_payroll' => round($totalPayroll - $totalDeductions, 2),
                'status' => 'completed',
                'processed_at' => now(),
            ]);

            $period->update(['status' => 'completed']);

            SystemNotification::query()->create([
                'title' => 'Payroll processed',
                'message' => "{$period->name} payroll completed for {$employees->count()} employees.",
                'type' => 'success',
            ]);

            // December (or Dec 2nd kinsena) creates a separate 13th-month payslip run.
            if (
                PayrollSetting::bool('auto_13th_month')
                && $this->isThirteenthMonthCutoff($period)
            ) {
                $this->ensureThirteenthMonthRun($period, $employees);
            }

            return $run->fresh(['payslips.employee', 'period']);
        });
    }

    /**
     * A cutoff may only be processed once. Re-running would issue a second set
     * of payslips and collect another cash advance instalment.
     *
     * @throws PayrollAlreadyProcessed
     */
    protected function guardAgainstReprocessing(PayrollPeriod $period): void
    {
        $existing = $period->runs()
            ->where('status', 'completed')
            ->latest('id')
            ->first();

        if ($existing) {
            throw new PayrollAlreadyProcessed($period, $existing);
        }
    }

    /**
     * December 2nd kinsena (16–end) triggers the separate 13th-month payslip.
     */
    protected function isThirteenthMonthCutoff(PayrollPeriod $period): bool
    {
        if ($this->isThirteenthMonthPeriod($period)) {
            return false;
        }

        // Instalments are released after the 2nd cutoff of the month.
        if ((int) ($period->half ?? 1) !== 2) {
            return false;
        }

        $month = (int) $period->month;

        if ($month === 12) {
            return true;
        }

        return PayrollSetting::bool('thirteenth_month_split')
            && $month === PayrollSetting::int('thirteenth_month_first_month');
    }

    public function isThirteenthMonthPeriod(PayrollPeriod $period): bool
    {
        return (int) ($period->half ?? 1) === self::THIRTEENTH_MONTH_HALF;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    protected function ensureThirteenthMonthRun(PayrollPeriod $sourcePeriod, Collection $employees): PayrollRun
    {
        $thirteenthPeriod = $this->resolveThirteenthMonthPeriod($sourcePeriod);

        return $this->runThirteenthMonthPeriod($thirteenthPeriod, $employees);
    }

    protected function resolveThirteenthMonthPeriod(PayrollPeriod $sourcePeriod): PayrollPeriod
    {
        $year = (int) $sourcePeriod->year;
        $month = (int) $sourcePeriod->month;
        $end = $sourcePeriod->end_date?->toDateString() ?? "{$year}-12-31";
        $label = $month === 12 ? '13th Month Pay' : '13th Month Pay (1st release)';

        return PayrollPeriod::query()->updateOrCreate(
            [
                'year' => $year,
                'month' => $month,
                'half' => self::THIRTEENTH_MONTH_HALF,
            ],
            [
                'name' => "{$year} {$label}",
                'start_date' => $end,
                'end_date' => $end,
                'cutoff_date' => $end,
                'process_start' => $end,
                'process_end' => $end,
                'payslip_release' => $end,
                'payday' => $end,
                'status' => 'open',
            ]
        );
    }

    /**
     * @param  Collection<int, Employee>|null  $employees
     */
    protected function runThirteenthMonthPeriod(
        PayrollPeriod $period,
        ?Collection $employees = null
    ): PayrollRun {
        $employees ??= Employee::query()->forPayrollPeriod($period->start_date)->get();

        $run = PayrollRun::query()->create([
            'payroll_period_id' => $period->id,
            'status' => 'in_progress',
        ]);

        $totalPayroll = 0.0;

        foreach ($employees as $employee) {
            $payslip = $this->buildThirteenthMonthPayslip($run, $employee, (int) $period->year);
            $totalPayroll += (float) $payslip->total_earnings;
        }

        $run->update([
            'total_employees' => $employees->count(),
            'total_payroll' => round($totalPayroll, 2),
            'total_deductions' => 0,
            'net_payroll' => round($totalPayroll, 2),
            'status' => 'completed',
            'processed_at' => now(),
        ]);

        $period->update(['status' => 'completed']);

        SystemNotification::query()->create([
            'title' => '13th month processed',
            'message' => "{$period->name} issued as a separate payslip for {$employees->count()} employees.",
            'type' => 'success',
        ]);

        return $run->fresh(['payslips.employee', 'period']);
    }

    protected function buildRegularPayslip(
        PayrollRun $run,
        Employee $employee,
        PayrollPeriod $period
    ): Payslip {
        $attendance = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('work_date', [$period->start_date, $period->end_date])
            ->get();

        $basicPay = $this->periodBasicPay($employee, $period);
        $holidayPay = (float) $attendance->sum('holiday_pay');
        $sundayRoute = (float) $attendance->sum('sunday_pay');
        $overtimePay = (float) $attendance->sum('ot_pay');
        $lateDeduction = (float) $attendance->sum('late_deduction');
        $undertimeDeduction = (float) $attendance->sum('undertime_deduction');

        $absence = $this->absenceDeduction($employee, $period, $basicPay, $attendance);

        // Time-based deductions can never take more than the basic left after
        // absences — you cannot lose more time than you were paid for.
        $timeBudget = max(0.0, round($basicPay - $absence['amount'], 2));
        $timeDeductions = round($lateDeduction + $undertimeDeduction, 2);

        if ($timeDeductions > $timeBudget) {
            // Trim the later line first so the printed lines still add up.
            $undertimeDeduction = max(0.0, round($timeBudget - $lateDeduction, 2));
            $lateDeduction = min($lateDeduction, $timeBudget);
            $timeDeductions = round($lateDeduction + $undertimeDeduction, 2);
        }

        // Contributions are based on the full basic — a single absent day does
        // not change an employee's contribution bracket.
        $gov = $this->governmentBenefits($basicPay);

        $totalEarnings = round(
            $basicPay + $holidayPay + $sundayRoute + $overtimePay,
            2
        );

        // ...but nothing can be withheld from pay that was never earned, so
        // trim the contributions to whatever the payslip can actually bear.
        $gov = $this->capContributions(
            $gov,
            max(0.0, round($totalEarnings - $timeDeductions - $absence['amount'], 2))
        );

        // Mandatory deductions come first; the cash advance may only take what
        // is left, so a payslip can never go negative. Anything it cannot
        // collect this run stays on the balance for the next one.
        $mandatory = round(
            $timeDeductions
            + $absence['amount']
            + $gov['sss']
            + $gov['philhealth']
            + $gov['pagibig'],
            2
        );

        $collectible = max(0.0, round($totalEarnings - $mandatory, 2));
        $cashAdvanceDeduction = $this->deductCashAdvance($employee, $collectible);

        $totalDeductions = round($mandatory + $cashAdvanceDeduction, 2);

        return Payslip::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_pay' => $basicPay,
            'absent_days' => $absence['days'],
            'holiday_pay' => $holidayPay,
            'sunday_route' => $sundayRoute,
            'overtime_pay' => $overtimePay,
            'thirteenth_month' => 0,
            'late_deduction' => $lateDeduction,
            'undertime_deduction' => $undertimeDeduction,
            'absence_deduction' => $absence['amount'],
            'cash_advance_deduction' => $cashAdvanceDeduction,
            'sss' => $gov['sss'],
            'philhealth' => $gov['philhealth'],
            'pagibig' => $gov['pagibig'],
            'total_earnings' => $totalEarnings,
            'total_deductions' => $totalDeductions,
            'net_pay' => round($totalEarnings - $totalDeductions, 2),
        ]);
    }

    protected function buildThirteenthMonthPayslip(
        PayrollRun $run,
        Employee $employee,
        int $year
    ): Payslip {
        $thirteenthMonth = $this->computeThirteenthMonth($employee, $year);

        return Payslip::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_pay' => 0,
            'holiday_pay' => 0,
            'sunday_route' => 0,
            'overtime_pay' => 0,
            'thirteenth_month' => $thirteenthMonth,
            'late_deduction' => 0,
            'undertime_deduction' => 0,
            'cash_advance_deduction' => 0,
            'sss' => 0,
            'philhealth' => 0,
            'pagibig' => 0,
            'total_earnings' => $thirteenthMonth,
            'total_deductions' => 0,
            'net_pay' => $thirteenthMonth,
        ]);
    }

    /**
     * Kinsenas: half of monthly basic per cutoff (1–15 and 16–end).
     */
    /**
     * Half-month basic pay, prorated when the employee left mid-period.
     *
     * Someone who worked 10 days of a 15-day period earns 10/15 of the basic.
     */
    protected function periodBasicPay(Employee $employee, PayrollPeriod $period): float
    {
        $basic = ((float) $employee->basic_salary) / 2;

        if (! $employee->leftDuring($period->end_date)) {
            return round($basic, 2);
        }

        $totalDays = $period->start_date->diffInDays($period->end_date) + 1;
        $workedDays = $period->start_date->diffInDays($employee->last_working_day) + 1;
        $workedDays = max(0, min($workedDays, $totalDays));

        return round($basic * $workedDays / $totalDays, 2);
    }

    /**
     * Deduct a day's pay for every scheduled working day the employee did not
     * work. Basic pay is a flat half-month figure, so a day is worth
     * basicPay / scheduled working days in the cutoff.
     *
     * Scheduled days are Mon–Sat excluding Sundays and holidays. A day counts
     * as worked only when it has a usable attendance record — a day flagged
     * incomplete (a missing punch) is not proof of work until HR corrects it.
     *
     * @param  Collection<int, AttendanceRecord>  $attendance
     * @return array{days: int, amount: float}
     */
    protected function absenceDeduction(
        Employee $employee,
        PayrollPeriod $period,
        float $basicPay,
        Collection $attendance
    ): array {
        if ($basicPay <= 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        $holidays = Holiday::mapForPeriod($period->start_date, $period->end_date);

        $workedDates = $attendance
            ->where('is_incomplete', false)
            ->map(fn (AttendanceRecord $record) => $record->work_date->toDateString())
            ->all();

        $lastDay = $employee->last_working_day;
        $scheduled = 0;
        $absent = 0;

        foreach (CarbonPeriod::create($period->start_date, $period->end_date) as $day) {
            /** @var CarbonInterface $day */
            if ($day->isSunday() || $holidays->has($day->toDateString())) {
                continue;
            }

            // Days after someone left are not theirs to be absent for; their
            // basic pay is already prorated for those.
            if ($lastDay !== null && $day->gt($lastDay)) {
                continue;
            }

            $scheduled++;

            if (! in_array($day->toDateString(), $workedDates, true)) {
                $absent++;
            }
        }

        if ($scheduled === 0 || $absent === 0) {
            return ['days' => 0, 'amount' => 0.0];
        }

        // Never deduct more than the basic pay itself.
        $amount = min($basicPay, round($basicPay / $scheduled * $absent, 2));

        return ['days' => $absent, 'amount' => $amount];
    }

    /**
     * PH 13th month = (total basic salary earned in the calendar year) / 12.
     * Sums regular payslips only (excludes the 13th-month slip itself).
     */
    protected function computeThirteenthMonth(Employee $employee, int $year): float
    {
        $divisor = max(1, PayrollSetting::int('thirteenth_month_divisor'));

        $totalBasic = (float) Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($query) => $query->where('status', 'completed'))
            ->whereHas('payrollRun.period', function ($query) use ($year) {
                $query->where('year', $year)
                    ->where('half', '!=', self::THIRTEENTH_MONTH_HALF);
            })
            ->sum('basic_pay');

        $entitlement = round($totalBasic / $divisor, 2);

        // The 13th month may be released in instalments (commonly half at
        // mid-year, the rest in December). Each payout settles whatever is
        // still owed, so the year always totals the full entitlement.
        $alreadyPaid = (float) Payslip::query()
            ->where('employee_id', $employee->id)
            ->whereHas('payrollRun', fn ($query) => $query->where('status', 'completed'))
            ->whereHas('payrollRun.period', function ($query) use ($year) {
                $query->where('year', $year)
                    ->where('half', self::THIRTEENTH_MONTH_HALF);
            })
            ->sum('thirteenth_month');

        return max(0.0, round($entitlement - $alreadyPaid, 2));
    }

    /**
     * @param  float  $collectible  Pay left after mandatory deductions. The
     *                              advance may not take more than this.
     */
    protected function deductCashAdvance(Employee $employee, float $collectible): float
    {
        if (! PayrollSetting::bool('cash_advance_auto_deduct') || $collectible <= 0) {
            return 0.0;
        }

        $advance = CashAdvance::query()
            ->where('employee_id', $employee->id)
            ->active()
            ->orderBy('released_at')
            ->first();

        if (! $advance) {
            return 0.0;
        }

        // Per-advance instalment if one was set, otherwise the settings default,
        // capped by the balance and by what this payslip can actually afford.
        $deduction = min(
            (float) $advance->balance,
            $advance->instalmentPerPayroll(),
            $collectible
        );
        $newBalance = round((float) $advance->balance - $deduction, 2);

        $advance->update([
            'balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : 'active',
        ]);

        return $deduction;
    }

    /**
     * Reduce contributions so their total fits the pay available, trimming the
     * flat Pag-IBIG first and SSS last. Without this a period with no earnings
     * would still withhold and drive the payslip negative.
     *
     * @param  array{sss: float, philhealth: float, pagibig: float}  $gov
     * @return array{sss: float, philhealth: float, pagibig: float}
     */
    protected function capContributions(array $gov, float $available): array
    {
        $total = round($gov['sss'] + $gov['philhealth'] + $gov['pagibig'], 2);

        if ($total <= $available) {
            return $gov;
        }

        foreach (['pagibig', 'philhealth', 'sss'] as $key) {
            $excess = round($total - $available, 2);

            if ($excess <= 0) {
                break;
            }

            $trim = min($gov[$key], $excess);
            $gov[$key] = round($gov[$key] - $trim, 2);
            $total = round($total - $trim, 2);
        }

        return $gov;
    }

    /**
     * @return array{sss: float, philhealth: float, pagibig: float}
     */
    protected function governmentBenefits(float $basicPay): array
    {
        $sssRate = PayrollSetting::float('sss_rate');
        $philhealthRate = PayrollSetting::float('philhealth_rate');
        $pagibig = round(PayrollSetting::float('pagibig_fixed') / 2, 2);

        return [
            'sss' => round($basicPay * $sssRate, 2),
            'philhealth' => round($basicPay * $philhealthRate, 2),
            'pagibig' => $pagibig,
        ];
    }
}
