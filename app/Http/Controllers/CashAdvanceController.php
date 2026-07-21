<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CashAdvanceController extends Controller
{
    public function index(): Response
    {
        $advances = CashAdvance::query()
            ->with('employee')
            ->latest('released_at')
            ->limit(100)
            ->get()
            ->map(fn (CashAdvance $advance) => [
                'id' => $advance->id,
                'employee_id' => $advance->employee_id,
                'employee_name' => $advance->employee?->full_name,
                'employee_code' => $advance->employee?->employee_code,
                'amount' => (float) $advance->amount,
                'balance' => (float) $advance->balance,
                'deduction_per_payroll' => $advance->deduction_per_payroll !== null
                    ? (float) $advance->deduction_per_payroll
                    : null,
                'effective_deduction' => $advance->instalmentPerPayroll(),
                // The amount stays fixable until payroll collects against it.
                'amount_locked' => round((float) $advance->amount - (float) $advance->balance, 2) > 0,
                'released_at' => $advance->released_at?->toDateString(),
                'status' => $advance->status,
                'notes' => $advance->notes,
            ]);

        return Inertia::render('cash-advances/index', [
            'advances' => $advances,
            'employees' => Employee::query()
                ->active()
                ->orderBy('employee_code')
                ->get(['id', 'employee_code', 'first_name', 'last_name']),
            'defaultDeduction' => round(PayrollSetting::float('cash_advance_max_deduction') / 2, 2),
            'autoDeduct' => PayrollSetting::bool('cash_advance_auto_deduct'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'deduction_per_payroll' => ['nullable', 'numeric', 'min:1', 'lte:amount'],
            'released_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $advance = CashAdvance::query()->create([
            'employee_id' => $data['employee_id'],
            'amount' => $data['amount'],
            'balance' => $data['amount'],
            'deduction_per_payroll' => $data['deduction_per_payroll'] ?? null,
            'released_at' => $data['released_at'],
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        $instalment = $advance->instalmentPerPayroll();
        $perPayroll = number_format($instalment, 2);
        $runs = (int) ceil((float) $advance->amount / max($instalment, 0.01));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Cash advance recorded. ₱{$perPayroll} will be deducted every payroll (about {$runs} runs to clear).",
        ]);

        return back();
    }

    /**
     * The instalment is always editable. The amount is editable only while
     * payroll has collected nothing yet — that window is when a typo is still
     * a typo. Once a peso has been deducted, changing the amount would rewrite
     * history, so it locks. The borrower never changes.
     */
    public function update(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $amount = (float) $cashAdvance->amount;
        $collected = round($amount - (float) $cashAdvance->balance, 2);
        $amountIsLocked = $collected > 0;

        $rules = ['deduction_per_payroll' => ['nullable', 'numeric', 'min:1']];

        if ($amountIsLocked) {
            $rules['deduction_per_payroll'][] = "max:{$amount}";
        } else {
            $rules['amount'] = ['required', 'numeric', 'min:1'];
            $rules['deduction_per_payroll'][] = 'lte:amount';
        }

        $data = $request->validate($rules, [
            'deduction_per_payroll.max' => 'The per-payroll deduction cannot be more than the ₱'
                .number_format($amount, 2).' advance.',
            'deduction_per_payroll.lte' => 'The per-payroll deduction cannot be more than the advance.',
        ]);

        $updates = ['deduction_per_payroll' => $data['deduction_per_payroll'] ?? null];

        if (! $amountIsLocked) {
            // Nothing collected yet, so the balance simply follows the amount.
            $updates['amount'] = $data['amount'];
            $updates['balance'] = $data['amount'];
            $updates['status'] = 'active';
        }

        $cashAdvance->update($updates);

        $perPayroll = number_format($cashAdvance->instalmentPerPayroll(), 2);
        $remaining = number_format((float) $cashAdvance->balance, 2);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Deduction updated. ₱{$perPayroll} per payroll until the ₱{$remaining} balance clears.",
        ]);

        return back();
    }
}
