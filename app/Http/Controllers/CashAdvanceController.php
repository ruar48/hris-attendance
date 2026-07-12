<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\Employee;
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
                'released_at' => $advance->released_at?->toDateString(),
                'status' => $advance->status,
                'notes' => $advance->notes,
            ]);

        return Inertia::render('cash-advances/index', [
            'advances' => $advances,
            'employees' => Employee::query()
                ->active()
                ->orderBy('first_name')
                ->get(['id', 'employee_code', 'first_name', 'last_name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'amount' => ['required', 'numeric', 'min:1'],
            'released_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        CashAdvance::query()->create([
            'employee_id' => $data['employee_id'],
            'amount' => $data['amount'],
            'balance' => $data['amount'],
            'released_at' => $data['released_at'],
            'status' => 'active',
            'notes' => $data['notes'] ?? null,
        ]);

        return back()->with('success', 'Cash advance recorded. It will be auto-deducted on the next payroll run (up to the max per period in Payroll Settings).');
    }
}
