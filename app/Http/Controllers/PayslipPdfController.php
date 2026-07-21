<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PayslipPdfController extends Controller
{
    public function downloadOne(PayrollRun $payroll, Payslip $payslip): SymfonyResponse
    {
        abort_unless($payslip->payroll_run_id === $payroll->id, 404);

        $payroll->load('period');
        $payslip->load('employee');

        $filename = sprintf(
            'payslip-%s-%s.pdf',
            $payslip->employee?->employee_code ?? $payslip->id,
            str($payroll->period?->name ?? 'payroll')->slug()
        );

        return Pdf::loadView('payslips.pdf', [
            'run' => $payroll,
            'payslips' => collect([$payslip]),
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }

    public function downloadAll(PayrollRun $payroll): SymfonyResponse
    {
        $payroll->load(['period', 'payslips.employee']);

        abort_if($payroll->payslips->isEmpty(), 404, 'No payslips found for this payroll run.');

        $filename = sprintf(
            'payslips-all-%s.pdf',
            str($payroll->period?->name ?? 'payroll')->slug()
        );

        return Pdf::loadView('payslips.pdf', [
            'run' => $payroll,
            'payslips' => $payroll->payslips,
        ])
            ->setPaper('a4', 'portrait')
            ->download($filename);
    }
}
