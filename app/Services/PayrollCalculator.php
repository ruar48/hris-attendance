<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\SystemNotification;
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

            $employees = Employee::query()->active()->get();
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
     * December 2nd kinsena (16–end) triggers the separate 13th-month payslip.
     */
    protected function isThirteenthMonthCutoff(PayrollPeriod $period): bool
    {
        if ($this->isThirteenthMonthPeriod($period)) {
            return false;
        }

        return (int) $period->month === 12
            && (int) ($period->half ?? 1) === 2;
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
        $end = $sourcePeriod->end_date?->toDateString() ?? "{$year}-12-31";

        return PayrollPeriod::query()->updateOrCreate(
            [
                'year' => $year,
                'month' => 12,
                'half' => self::THIRTEENTH_MONTH_HALF,
            ],
            [
                'name' => "{$year} 13th Month Pay",
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
        $employees ??= Employee::query()->active()->get();

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

        $basicPay = $this->periodBasicPay($employee);
        $holidayPay = (float) $attendance->sum('holiday_pay');
        $sundayRoute = (float) $attendance->sum('sunday_pay');
        $overtimePay = (float) $attendance->sum('ot_pay');
        $lateDeduction = (float) $attendance->sum('late_deduction');
        $undertimeDeduction = (float) $attendance->sum('undertime_deduction');

        $cashAdvanceDeduction = $this->deductCashAdvance($employee);
        $gov = $this->governmentBenefits($basicPay);

        $totalEarnings = round(
            $basicPay + $holidayPay + $sundayRoute + $overtimePay,
            2
        );

        $totalDeductions = round(
            $lateDeduction
            + $undertimeDeduction
            + $cashAdvanceDeduction
            + $gov['sss']
            + $gov['philhealth']
            + $gov['pagibig'],
            2
        );

        return Payslip::query()->create([
            'payroll_run_id' => $run->id,
            'employee_id' => $employee->id,
            'basic_pay' => $basicPay,
            'holiday_pay' => $holidayPay,
            'sunday_route' => $sundayRoute,
            'overtime_pay' => $overtimePay,
            'thirteenth_month' => 0,
            'late_deduction' => $lateDeduction,
            'undertime_deduction' => $undertimeDeduction,
            'cash_advance_deduction' => $cashAdvanceDeduction,
            'sss' => $gov['sss'],
            'philhealth' => $gov['philhealth'],
            'pagibig' => $gov['pagibig'],
            'withholding_tax' => 0,
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
            'withholding_tax' => 0,
            'total_earnings' => $thirteenthMonth,
            'total_deductions' => 0,
            'net_pay' => $thirteenthMonth,
        ]);
    }

    /**
     * Kinsenas: half of monthly basic per cutoff (1–15 and 16–end).
     */
    protected function periodBasicPay(Employee $employee): float
    {
        return round(((float) $employee->basic_salary) / 2, 2);
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
            ->whereHas('payrollRun.period', function ($query) use ($year) {
                $query->where('year', $year)
                    ->where('half', '!=', self::THIRTEENTH_MONTH_HALF);
            })
            ->sum('basic_pay');

        return round($totalBasic / $divisor, 2);
    }

    protected function deductCashAdvance(Employee $employee): float
    {
        if (! PayrollSetting::bool('cash_advance_auto_deduct')) {
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

        $maxPerPayroll = round(PayrollSetting::float('cash_advance_max_deduction') / 2, 2);
        $deduction = min((float) $advance->balance, $maxPerPayroll);
        $newBalance = round((float) $advance->balance - $deduction, 2);

        $advance->update([
            'balance' => $newBalance,
            'status' => $newBalance <= 0 ? 'paid' : 'active',
        ]);

        return $deduction;
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
