<?php

namespace App\Http\Controllers;

use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function index(): Response
    {
        $periods = PayrollPeriod::query()
            ->with('latestRun')
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->orderByDesc('half')
            ->get()
            ->map(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'month' => $period->month,
                'status' => $period->status,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'includes_13th_month' => (int) ($period->half ?? 1) === PayrollCalculator::THIRTEENTH_MONTH_HALF,
                'is_thirteenth_month' => (int) ($period->half ?? 1) === PayrollCalculator::THIRTEENTH_MONTH_HALF,
                'half' => (int) ($period->half ?? 1),
                'is_kinsena' => (int) ($period->half ?? 1) !== PayrollCalculator::THIRTEENTH_MONTH_HALF,
                'latest_run' => $period->latestRun ? [
                    'id' => $period->latestRun->id,
                    'total_employees' => $period->latestRun->total_employees,
                    'total_payroll' => (float) $period->latestRun->total_payroll,
                    'net_payroll' => (float) $period->latestRun->net_payroll,
                    'status' => $period->latestRun->status,
                ] : null,
            ]);

        $runs = PayrollRun::query()
            ->with('period')
            ->latest()
            ->limit(24)
            ->get()
            ->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'period' => $run->period?->name,
                'month' => $run->period?->month,
                'half' => (int) ($run->period?->half ?? 1),
                'total_employees' => $run->total_employees,
                'total_payroll' => (float) $run->total_payroll,
                'total_deductions' => (float) $run->total_deductions,
                'net_payroll' => (float) $run->net_payroll,
                'status' => $run->status,
                'processed_at' => $run->processed_at?->toDateTimeString(),
                'includes_13th_month' => (int) ($run->period?->half ?? 1) === PayrollCalculator::THIRTEENTH_MONTH_HALF,
            ]);

        return Inertia::render('payroll/index', [
            'periods' => $periods,
            'runs' => $runs,
        ]);
    }

    public function run(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $period = PayrollPeriod::query()->findOrFail($request->integer('payroll_period_id'));
        $run = $calculator->run($period);

        return redirect()
            ->route('payroll.show', $run)
            ->with('success', "Payroll processed for {$period->name}.");
    }

    public function show(PayrollRun $payroll): Response
    {
        $payroll->load(['period', 'payslips.employee']);

        return Inertia::render('payroll/show', [
            'run' => [
                'id' => $payroll->id,
                'period' => $payroll->period?->name,
                'total_employees' => $payroll->total_employees,
                'total_payroll' => (float) $payroll->total_payroll,
                'total_deductions' => (float) $payroll->total_deductions,
                'net_payroll' => (float) $payroll->net_payroll,
                'status' => $payroll->status,
                'processed_at' => $payroll->processed_at?->toDateTimeString(),
            ],
            'payslips' => $payroll->payslips->map(fn (Payslip $payslip) => [
                'id' => $payslip->id,
                'employee_name' => $payslip->employee?->full_name,
                'employee_code' => $payslip->employee?->employee_code,
                'position' => $payslip->employee?->position,
                'department' => $payslip->employee?->department,
                'basic_pay' => (float) $payslip->basic_pay,
                'holiday_pay' => (float) $payslip->holiday_pay,
                'sunday_route' => (float) $payslip->sunday_route,
                'overtime_pay' => (float) $payslip->overtime_pay,
                'thirteenth_month' => (float) $payslip->thirteenth_month,
                'late_deduction' => (float) $payslip->late_deduction,
                'undertime_deduction' => (float) $payslip->undertime_deduction,
                'cash_advance_deduction' => (float) $payslip->cash_advance_deduction,
                'sss' => (float) $payslip->sss,
                'philhealth' => (float) $payslip->philhealth,
                'pagibig' => (float) $payslip->pagibig,
                'withholding_tax' => (float) $payslip->withholding_tax,
                'total_earnings' => (float) $payslip->total_earnings,
                'total_deductions' => (float) $payslip->total_deductions,
                'net_pay' => (float) $payslip->net_pay,
            ]),
        ]);
    }
}
