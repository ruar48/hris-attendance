<?php

namespace App\Http\Controllers;

use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PayslipAdjustmentController extends Controller
{
    /**
     * Save a payslip's adjustments (added to net pay) and warehouse subsidy
     * (listed under net pay), then refresh the run's net payroll.
     */
    public function update(Request $request, PayrollRun $payroll, Payslip $payslip): RedirectResponse
    {
        abort_unless($payslip->payroll_run_id === $payroll->id, 404);

        $fields = [
            ...array_keys(Payslip::ADJUSTMENT_FIELDS),
            ...array_keys(Payslip::WAREHOUSE_SUBSIDY_FIELDS),
        ];

        $validated = $request->validate(
            collect($fields)
                ->mapWithKeys(fn (string $field) => [$field => ['nullable', 'numeric', 'min:0', 'max:99999999']])
                ->all(),
            [],
            [...Payslip::ADJUSTMENT_FIELDS, ...Payslip::WAREHOUSE_SUBSIDY_FIELDS],
        );

        DB::transaction(function () use ($payroll, $payslip, $validated) {
            $payslip->applyAdjustments($validated);
            $payslip->save();

            $payroll->update([
                'net_payroll' => round((float) $payroll->payslips()->sum('net_pay'), 2),
            ]);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Adjustments saved for '.($payslip->employee?->full_name ?? 'employee').'.',
        ]);

        return back();
    }
}
