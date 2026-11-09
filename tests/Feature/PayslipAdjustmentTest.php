<?php

use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    Employee::query()->create([
        'employee_code' => 'EMP-0001',
        'first_name' => 'Jomar',
        'last_name' => 'Reyes',
        'basic_salary' => 15000,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);

    $period = PayrollPeriod::query()->create([
        'name' => 'January 1-15',
        'year' => 2026,
        'month' => 1,
        'half' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-15',
        'payday' => '2026-01-20',
        'status' => 'pending',
    ]);

    $this->run = app(PayrollCalculator::class)->run($period);
    $this->payslip = $this->run->payslips->first();
});

test('a new payslip starts with no adjustments or warehouse subsidy', function () {
    expect((float) $this->payslip->total_adjustments)->toBe(0.0)
        ->and((float) $this->payslip->warehouse_subsidy)->toBe(0.0)
        ->and((float) $this->payslip->net_pay)
        ->toBe(round((float) $this->payslip->total_earnings - (float) $this->payslip->total_deductions, 2));
});

test('adjustments are added to net pay and warehouse subsidy is kept separate', function () {
    $baseNet = (float) $this->payslip->net_pay;

    $this->patch(route('payroll.payslips.adjustments.update', [$this->run, $this->payslip]), [
        'reimbursement' => 1200.50,
        'allowance' => 800,
        'forklift_diesel' => 2500,
        'disbursement_batangas' => 1000,
        'disbursement_dasma' => 0,
        'disbursement_san_pablo' => 300,
        'disbursement_tayabas' => 0,
        'disbursement_taguig' => 450.25,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $payslip = $this->payslip->fresh();

    expect((float) $payslip->total_adjustments)->toBe(2000.50)
        ->and((float) $payslip->net_pay)->toBe(round($baseNet + 2000.50, 2))
        ->and((float) $payslip->warehouse_subsidy)->toBe(4250.25)
        ->and((float) $this->run->fresh()->net_payroll)->toBe((float) $payslip->net_pay);
});

test('editing adjustments again replaces the previous amounts', function () {
    $baseNet = (float) $this->payslip->net_pay;
    $url = route('payroll.payslips.adjustments.update', [$this->run, $this->payslip]);

    $this->patch($url, ['reimbursement' => 500, 'allowance' => 500]);
    $this->patch($url, ['reimbursement' => 100, 'allowance' => 0]);

    expect((float) $this->payslip->fresh()->net_pay)->toBe(round($baseNet + 100, 2));
});

test('negative amounts are rejected', function () {
    $this->patch(route('payroll.payslips.adjustments.update', [$this->run, $this->payslip]), [
        'reimbursement' => -50,
    ])->assertSessionHasErrors('reimbursement');

    expect((float) $this->payslip->fresh()->reimbursement)->toBe(0.0);
});

test('the payslip pdf renders with adjustments and warehouse subsidy', function () {
    $this->patch(route('payroll.payslips.adjustments.update', [$this->run, $this->payslip]), [
        'reimbursement' => 100,
        'forklift_diesel' => 200,
    ]);

    $this->get(route('payroll.payslips.pdf.one', [$this->run, $this->payslip]))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});
