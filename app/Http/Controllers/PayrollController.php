<?php

namespace App\Http\Controllers;

use App\Exceptions\PayrollAlreadyProcessed;
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
    public function index(Request $request): Response
    {
        $years = PayrollPeriod::query()
            ->select('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->values()
            ->all();

        $defaultYear = $years[0] ?? (int) now()->year;
        $year = (int) $request->integer('year', $defaultYear);

        if ($years !== [] && ! in_array($year, $years, true)) {
            $year = $defaultYear;
        }

        $periods = PayrollPeriod::query()
            ->with('latestRun')
            ->where('year', $year)
            ->orderByDesc('month')
            ->orderByDesc('half')
            ->get()
            ->map(fn (PayrollPeriod $period) => [
                'id' => $period->id,
                'name' => $period->name,
                'year' => (int) $period->year,
                'month' => (int) $period->month,
                'status' => $period->status,
                'start_date' => $period->start_date?->toDateString(),
                'end_date' => $period->end_date?->toDateString(),
                'cutoff_date' => $period->cutoff_date?->toDateString(),
                'payday' => $period->payday?->toDateString(),
                'is_thirteenth_month' => (int) ($period->half ?? 1) === PayrollCalculator::THIRTEENTH_MONTH_HALF,
                'thirteenth_installment' => $this->thirteenthInstallment($period),
                'half' => (int) ($period->half ?? 1),
                'type' => $this->periodType($period),
                'latest_run' => $period->latestRun ? [
                    'id' => $period->latestRun->id,
                    'total_employees' => $period->latestRun->total_employees,
                    'total_payroll' => (float) $period->latestRun->total_payroll,
                    'total_deductions' => (float) $period->latestRun->total_deductions,
                    'net_payroll' => (float) $period->latestRun->net_payroll,
                    'status' => $period->latestRun->status,
                    'processed_at' => $period->latestRun->processed_at?->toDateTimeString(),
                ] : null,
            ]);

        $completed = $periods->where('status', 'completed');
        $totalNet = $completed->sum(fn (array $period) => (float) ($period['latest_run']['net_payroll'] ?? 0));
        $totalGross = $completed->sum(fn (array $period) => (float) ($period['latest_run']['total_payroll'] ?? 0));

        return Inertia::render('payroll/index', [
            'filters' => [
                'year' => $year,
            ],
            'years' => $years,
            'summary' => [
                'cutoffs' => $periods->count(),
                'completed' => $completed->count(),
                'pending' => $periods->count() - $completed->count(),
                'total_payroll' => round($totalGross, 2),
                'net_payroll' => round($totalNet, 2),
            ],
            'periods' => $periods->values(),
        ]);
    }

    public function run(Request $request, PayrollCalculator $calculator): RedirectResponse
    {
        $period = PayrollPeriod::query()->findOrFail($request->integer('payroll_period_id'));

        try {
            $run = $calculator->run($period);
        } catch (PayrollAlreadyProcessed $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage().' Showing the existing payslips instead.',
            ]);

            return redirect()->route('payroll.show', $exception->run);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Payroll processed for {$period->name}.",
        ]);

        return redirect()->route('payroll.show', $run);
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
                'absence_deduction' => (float) $payslip->absence_deduction,
                'absent_days' => (int) $payslip->absent_days,
                'cash_advance_deduction' => (float) $payslip->cash_advance_deduction,
                'sss' => (float) $payslip->sss,
                'philhealth' => (float) $payslip->philhealth,
                'pagibig' => (float) $payslip->pagibig,
                'total_earnings' => (float) $payslip->total_earnings,
                'total_deductions' => (float) $payslip->total_deductions,
                'net_pay' => (float) $payslip->net_pay,
            ]),
        ]);
    }

    protected function periodType(PayrollPeriod $period): string
    {
        $half = (int) ($period->half ?? 1);

        if ($half === PayrollCalculator::THIRTEENTH_MONTH_HALF) {
            return (int) $period->month === 6 ? '13th_month_1st' : '13th_month_2nd';
        }

        return $half === 2 ? '2nd_kinsena' : '1st_kinsena';
    }

    protected function thirteenthInstallment(PayrollPeriod $period): ?int
    {
        if ((int) ($period->half ?? 1) !== PayrollCalculator::THIRTEENTH_MONTH_HALF) {
            return null;
        }

        return (int) $period->month === 6 ? 1 : 2;
    }
}
