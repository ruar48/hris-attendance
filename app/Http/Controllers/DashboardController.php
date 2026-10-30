<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SystemNotification;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $period = PayrollPeriod::query()
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->first();

        $latestRun = $period?->latestRun;

        $recentRuns = PayrollRun::query()
            ->with('period')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PayrollRun $run) => [
                'id' => $run->id,
                'period' => $run->period?->name,
                'total_employees' => $run->total_employees,
                'total_payroll' => (float) $run->total_payroll,
                'status' => $run->status,
            ]);

        $samplePayslip = Payslip::query()
            ->with('employee')
            ->latest()
            ->first();

        $notifications = SystemNotification::query()
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (SystemNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'message' => $n->message,
                'type' => $n->type,
                'created_at' => $n->created_at?->diffForHumans(),
            ]);

        return Inertia::render('dashboard', [
            'stats' => [
                'total_employees' => Employee::query()->active()->count(),
                'total_payroll' => (float) ($latestRun?->total_payroll ?? 0),
                'total_deductions' => (float) ($latestRun?->total_deductions ?? 0),
                'net_payroll' => (float) ($latestRun?->net_payroll ?? 0),
            ],
            'period' => $period ? [
                'id' => $period->id,
                'name' => $period->name,
                'cutoff_date' => $period->cutoff_date?->toDateString(),
                'process_start' => $period->process_start?->toDateString(),
                'process_end' => $period->process_end?->toDateString(),
                'payslip_release' => $period->payslip_release?->toDateString(),
                'payday' => $period->payday?->toDateString(),
                'status' => $period->status,
            ] : null,
            'recentRuns' => $recentRuns,
            'notifications' => $notifications,
            'samplePayslip' => $samplePayslip ? [
                'employee_name' => $samplePayslip->employee?->full_name,
                'employee_code' => $samplePayslip->employee?->employee_code,
                'position' => $samplePayslip->employee?->position,
                'basic_pay' => (float) $samplePayslip->basic_pay,
                'holiday_pay' => (float) $samplePayslip->holiday_pay,
                'sunday_route' => (float) $samplePayslip->sunday_route,
                'overtime_pay' => (float) $samplePayslip->overtime_pay,
                'late_deduction' => (float) $samplePayslip->late_deduction,
                'undertime_deduction' => (float) $samplePayslip->undertime_deduction,
                'cash_advance_deduction' => (float) $samplePayslip->cash_advance_deduction,
                'sss' => (float) $samplePayslip->sss,
                'philhealth' => (float) $samplePayslip->philhealth,
                'pagibig' => (float) $samplePayslip->pagibig,
                'total_earnings' => (float) $samplePayslip->total_earnings,
                'total_deductions' => (float) $samplePayslip->total_deductions,
                'net_pay' => (float) $samplePayslip->net_pay,
            ] : null,
        ]);
    }
}
