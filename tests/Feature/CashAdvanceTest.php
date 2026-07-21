<?php

use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    $this->employee = Employee::query()->create([
        'employee_code' => 'EMP-0001',
        'first_name' => 'Jomar',
        'last_name' => 'Reyes',
        'basic_salary' => 15000,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);
});

function cashAdvancePeriod(int $month): PayrollPeriod
{
    return PayrollPeriod::query()->create([
        'name' => "Period {$month}",
        'year' => 2026,
        'month' => $month,
        'half' => 1,
        'start_date' => "2026-{$month}-01",
        'end_date' => "2026-{$month}-15",
        'payday' => "2026-{$month}-20",
        'status' => 'pending',
    ]);
}

/** Present for every scheduled day, so the employee actually earns a wage. */
function markPresent(Employee $employee, PayrollPeriod $period): void
{
    foreach (CarbonPeriod::create($period->start_date, $period->end_date) as $day) {
        if ($day->isSunday()) {
            continue;
        }

        AttendanceRecord::query()->create([
            'employee_id' => $employee->id,
            'work_date' => $day->toDateString(),
            'source' => 'biometric',
            'is_incomplete' => false,
            'worked_minutes' => 540,
        ]);
    }
}

function runPayroll(int $month): void
{
    $period = cashAdvancePeriod($month);
    markPresent(test()->employee, $period);

    app(PayrollCalculator::class)->run($period, syncAttendance: false);
}

test('a 2000 advance set to 500 per payroll clears over four runs', function () {
    $this->post(route('cash-advances.store'), [
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $advance = CashAdvance::query()->firstOrFail();
    expect((float) $advance->deduction_per_payroll)->toBe(500.0);

    // Four payroll runs of 500 each.
    foreach ([2, 3, 4, 5] as $index => $month) {
        runPayroll($month);
        $advance->refresh();

        expect((float) $advance->balance)->toBe(2000.0 - (500.0 * ($index + 1)));
    }

    expect((float) $advance->balance)->toBe(0.0)
        ->and($advance->status)->toBe('paid');
});

test('the payslip shows the 500 instalment as the cash advance deduction', function () {
    CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $period = cashAdvancePeriod(2);
    markPresent($this->employee, $period);
    $run = app(PayrollCalculator::class)->run($period, syncAttendance: false);

    expect((float) $run->payslips()->firstOrFail()->cash_advance_deduction)->toBe(500.0);
});

test('an advance without its own amount falls back to the settings default', function () {
    // Default is 1000 per month => 500 per payroll run.
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 3000,
        'balance' => 3000,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    expect($advance->deduction_per_payroll)->toBeNull()
        ->and($advance->instalmentPerPayroll())->toBe(500.0);

    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(2500.0);
});

test('changing the default setting changes advances that have no override', function () {
    PayrollSetting::setValue('cash_advance_max_deduction', '400'); // => 200 per run

    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 1000,
        'balance' => 1000,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(800.0);
});

test('an override is not affected when the default setting changes', function () {
    PayrollSetting::setValue('cash_advance_max_deduction', '400'); // => 200 per run

    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(1500.0);
});

test('the last run only takes what is left, never more than the balance', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 200, // less than the 500 instalment
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $period = cashAdvancePeriod(2);
    markPresent($this->employee, $period);
    $run = app(PayrollCalculator::class)->run($period, syncAttendance: false);

    expect((float) $run->payslips()->firstOrFail()->cash_advance_deduction)->toBe(200.0)
        ->and((float) $advance->fresh()->balance)->toBe(0.0)
        ->and($advance->fresh()->status)->toBe('paid');
});

test('the instalment can be corrected and payroll uses the new one', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->put(route('cash-advances.update', $advance), [
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'deduction_per_payroll' => 250,
        'released_at' => '2026-01-05',
    ])->assertSessionHasNoErrors();

    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(1750.0);
});

test('clearing the instalment falls back to the default again', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 250,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->put(route('cash-advances.update', $advance), [
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'deduction_per_payroll' => null,
        'released_at' => '2026-01-05',
    ])->assertSessionHasNoErrors();

    expect($advance->fresh()->deduction_per_payroll)->toBeNull();

    runPayroll(2); // default is 500 per run

    expect((float) $advance->fresh()->balance)->toBe(1500.0);
});

test('a typo can be fixed while nothing has been deducted yet', function () {
    // HR typed 500 but the employee actually borrowed 5,000.
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 500,
        'balance' => 500,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->put(route('cash-advances.update', $advance), [
        'amount' => 5000,
        'deduction_per_payroll' => 500,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $advance->refresh();

    // Both the amount and the untouched balance follow the correction.
    expect((float) $advance->amount)->toBe(5000.0)
        ->and((float) $advance->balance)->toBe(5000.0)
        ->and($advance->status)->toBe('active');

    // And payroll now collects against the corrected figure.
    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(4500.0);
});

test('the amount locks as soon as payroll has deducted once', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 500,
        'balance' => 500,
        'deduction_per_payroll' => 100,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    runPayroll(2); // collects 100 -> balance 400

    $this->put(route('cash-advances.update', $advance), [
        'amount' => 5000,
        'deduction_per_payroll' => 100,
    ])->assertSessionHasNoErrors();

    $advance->refresh();

    // The correction is ignored now that money has moved.
    expect((float) $advance->amount)->toBe(500.0)
        ->and((float) $advance->balance)->toBe(400.0);
});

test('the index flags whether an advance amount is still editable', function () {
    $untouched = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'released_at' => '2026-02-05',
        'status' => 'active',
    ]);

    $partlyPaid = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 1500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->get(route('cash-advances.index'))
        ->assertInertia(fn ($page) => $page
            ->where('advances.0.id', $untouched->id)
            ->where('advances.0.amount_locked', false)
            ->where('advances.1.id', $partlyPaid->id)
            ->where('advances.1.amount_locked', true)
        );
});

test('a corrected amount cannot be smaller than the instalment', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 5000,
        'balance' => 5000,
        'deduction_per_payroll' => 1000,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->put(route('cash-advances.update', $advance), [
        'amount' => 500, // less than the 1,000 instalment
        'deduction_per_payroll' => 1000,
    ])->assertSessionHasErrors('deduction_per_payroll');

    expect((float) $advance->fresh()->amount)->toBe(5000.0);
});

test('editing cannot change the borrower or the amount, even if posted', function () {
    $other = Employee::query()->create([
        'employee_code' => 'EMP-0002',
        'first_name' => 'Someone',
        'last_name' => 'Else',
        'basic_salary' => 15000,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);

    // Partly repaid, so the amount is locked too.
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 1500,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
        'notes' => 'Emergency fund',
    ]);

    // Post every locked field with new values — they must all be ignored.
    $this->put(route('cash-advances.update', $advance), [
        'employee_id' => $other->id,
        'amount' => 99999,
        'balance' => 1,
        'deduction_per_payroll' => 250,
        'released_at' => '2020-01-01',
        'notes' => 'hacked',
        'status' => 'paid',
    ])->assertSessionHasNoErrors();

    $advance->refresh();

    expect($advance->employee_id)->toBe($this->employee->id)
        ->and((float) $advance->amount)->toBe(2000.0)
        ->and((float) $advance->balance)->toBe(1500.0)
        ->and($advance->released_at->toDateString())->toBe('2026-01-05')
        ->and($advance->notes)->toBe('Emergency fund')
        ->and($advance->status)->toBe('active')
        // Only this one changed.
        ->and((float) $advance->deduction_per_payroll)->toBe(250.0);
});

test('the instalment cannot be set higher than the amount borrowed', function () {
    // Partly repaid, so the amount is locked and the cap comes from the record.
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 1500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    $this->put(route('cash-advances.update', $advance), [
        'deduction_per_payroll' => 5000,
    ])->assertSessionHasErrors('deduction_per_payroll');

    expect($advance->fresh()->deduction_per_payroll)->toBeNull();

    // The cap is the advance amount, so exactly the amount is allowed.
    $this->put(route('cash-advances.update', $advance), [
        'deduction_per_payroll' => 2000,
    ])->assertSessionHasNoErrors();

    expect((float) $advance->fresh()->deduction_per_payroll)->toBe(2000.0);
});

test('the per payroll amount cannot exceed the advance itself', function () {
    $this->post(route('cash-advances.store'), [
        'employee_id' => $this->employee->id,
        'amount' => 1000,
        'deduction_per_payroll' => 5000,
        'released_at' => '2026-01-05',
    ])->assertSessionHasErrors('deduction_per_payroll');
});

test('turning auto deduct off collects nothing', function () {
    PayrollSetting::setValue('cash_advance_auto_deduct', '0');

    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    runPayroll(2);

    expect((float) $advance->fresh()->balance)->toBe(2000.0);
});
