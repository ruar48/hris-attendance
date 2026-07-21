<?php

use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\User;
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

    $this->period = PayrollPeriod::query()->create([
        'name' => 'January 1-15',
        'year' => 2026,
        'month' => 1,
        'half' => 1,
        'start_date' => '2026-01-01',
        'end_date' => '2026-01-15',
        'payday' => '2026-01-20',
        'status' => 'pending',
    ]);
});

test('running the same period twice does not double-deduct a cash advance', function () {
    $advance = CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-05',
        'status' => 'active',
    ]);

    // Present for every scheduled day so there is pay to deduct from.
    foreach (CarbonPeriod::create($this->period->start_date, $this->period->end_date) as $day) {
        if ($day->isSunday()) {
            continue;
        }

        AttendanceRecord::query()->create([
            'employee_id' => $this->employee->id,
            'work_date' => $day->toDateString(),
            'source' => 'biometric',
            'is_incomplete' => false,
            'worked_minutes' => 540,
        ]);
    }

    $this->post(route('payroll.run'), ['payroll_period_id' => $this->period->id]);
    expect((float) $advance->fresh()->balance)->toBe(1500.0);

    // Second run of the SAME period must not take another 500.
    $this->post(route('payroll.run'), ['payroll_period_id' => $this->period->id]);

    expect((float) $advance->fresh()->balance)->toBe(1500.0);
});

test('running the same period twice does not create a duplicate run', function () {
    $this->post(route('payroll.run'), ['payroll_period_id' => $this->period->id]);
    $this->post(route('payroll.run'), ['payroll_period_id' => $this->period->id]);

    expect($this->period->runs()->count())->toBe(1);
});
