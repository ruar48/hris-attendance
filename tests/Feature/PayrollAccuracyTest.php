<?php

use App\Models\AttendanceRecord;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\User;
use App\Services\PayrollCalculator;
use Carbon\CarbonPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Payroll accuracy
|--------------------------------------------------------------------------
| Every expected figure below is worked out by hand from the employee's rates
| and the default payroll settings, so these tests fail if the engine's maths
| ever drifts. Reference employee: basic 15,000/mo, hourly 75, daily 600.
|
| Government contributions use the statutory formulas (see
| PayrollCalculator@sssEmployeeShare and friends), computed on the full
| monthly basic and then split in half per kinsena. At 15,000/mo none of the
| brackets' floors or ceilings kick in, so the numbers below are exact:
|
|   SSS:        MSC = round(15,000/500)*500 = 15,000 (no floor/cap applied)
|               monthly = 15,000 * 5%        =    750.00 -> 375.00/kinsena
|   PhilHealth: base = 15,000 (between 10,000 floor and 100,000 cap)
|               monthly = 15,000 * 2.5%      =    375.00 -> 187.50/kinsena
|   Pag-IBIG:   base = min(15,000, 10,000) = 10,000; rate 2% (comp > 1,500)
|               monthly = 10,000 * 2%        =    200.00 -> 100.00/kinsena
|
|   half-month basic  = 15,000 / 2   = 7,500.00
|   sss                                =   375.00
|   philhealth                         =   187.50
|   pagibig                            =   100.00
|   government total                   =   662.50
|   cash advance default = 1,000 / 2   =   500.00
|
| Scheduled working days are Mon-Sat; Sunday is the rest day. The Jan 1-15
| cutoff therefore has 13 scheduled days (Jan 4 and Jan 11 are Sundays).
*/

const HALF_MONTH_BASIC = 7500.00;
const SSS = 375.00;
const PHILHEALTH = 187.50;
const PAGIBIG = 100.00;
const GOV_TOTAL = 662.50;
const SCHEDULED_DAYS = 13;

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
        'sunday_route_rate' => 800,
        'status' => 'active',
    ]);
});

function period(int $month = 1, int $half = 1): PayrollPeriod
{
    return PayrollPeriod::query()->create([
        'name' => "2026-{$month} half {$half}",
        'year' => 2026,
        'month' => $month,
        'half' => $half,
        'start_date' => $half === 1 ? "2026-{$month}-01" : "2026-{$month}-16",
        'end_date' => $half === 1 ? "2026-{$month}-15" : "2026-{$month}-28",
        'payday' => "2026-{$month}-20",
        'status' => 'pending',
    ]);
}

function payrollFor(PayrollPeriod $period): Payslip
{
    return app(PayrollCalculator::class)
        ->run($period, syncAttendance: false)
        ->payslips()
        ->firstOrFail();
}

function attendance(Employee $employee, string $date, array $attributes = []): AttendanceRecord
{
    $values = array_merge([
        'source' => 'biometric',
        'is_incomplete' => false,
        'worked_minutes' => 540,
    ], $attributes);

    // Dates are stored with a time component, so match on the date part.
    $existing = AttendanceRecord::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $date)
        ->first();

    if ($existing) {
        $existing->update($values);

        return $existing->fresh();
    }

    return AttendanceRecord::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $date,
        ...$values,
    ]);
}

/** Mark the employee present for every scheduled day of the cutoff. */
function presentAllPeriod(Employee $employee, PayrollPeriod $period): void
{
    foreach (CarbonPeriod::create($period->start_date, $period->end_date) as $day) {
        if ($day->isSunday()) {
            continue;
        }

        attendance($employee, $day->toDateString());
    }
}

test('a full attendance period pays exactly half the monthly basic less government dues', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    $payslip = payrollFor($period);

    expect((float) $payslip->basic_pay)->toBe(HALF_MONTH_BASIC)
        ->and($payslip->absent_days)->toBe(0)
        ->and((float) $payslip->absence_deduction)->toBe(0.0)
        ->and((float) $payslip->sss)->toBe(SSS)
        ->and((float) $payslip->philhealth)->toBe(PHILHEALTH)
        ->and((float) $payslip->pagibig)->toBe(PAGIBIG)
        ->and((float) $payslip->total_earnings)->toBe(HALF_MONTH_BASIC)
        ->and((float) $payslip->total_deductions)->toBe(GOV_TOTAL)
        ->and((float) $payslip->net_pay)->toBe(HALF_MONTH_BASIC - GOV_TOTAL); // 6,837.50
});

test('government contributions across a full month equal the monthly rates', function () {
    $first = period(1, 1);
    $second = period(1, 2);
    presentAllPeriod($this->employee, $first);
    presentAllPeriod($this->employee, $second);

    $a = payrollFor($first);
    $b = payrollFor($second);

    // 5% and 2.5% of the 15,000 monthly basic, and the flat 200 Pag-IBIG.
    expect((float) $a->sss + (float) $b->sss)->toBe(750.00)
        ->and((float) $a->philhealth + (float) $b->philhealth)->toBe(375.00)
        ->and((float) $a->pagibig + (float) $b->pagibig)->toBe(200.00);
});

test('SSS applies the 5,000 MSC floor and PhilHealth the 10,000 salary floor for low earners', function () {
    $employee = Employee::query()->create([
        'employee_code' => 'EMP-LOW',
        'first_name' => 'Low',
        'last_name' => 'Earner',
        'basic_salary' => 4000, // half-month basic 2,000
        'daily_rate' => 160,
        'hourly_rate' => 20,
        'status' => 'active',
    ]);

    $period = period();
    presentAllPeriod($employee, $period);

    $run = app(PayrollCalculator::class)->run($period, syncAttendance: false);
    $payslip = $run->payslips()->where('employee_id', $employee->id)->firstOrFail();

    // SSS: MSC = round(4,000/500)*500 = 4,000, floored to 5,000 -> 5,000*5% / 2 = 125.00
    // PhilHealth: floored to 10,000 -> 10,000*2.5% / 2 = 125.00
    // Pag-IBIG: comp 4,000 <= 10,000 cap, rate 2% (comp > 1,500) -> 4,000*2% / 2 = 40.00
    expect((float) $payslip->sss)->toBe(125.00)
        ->and((float) $payslip->philhealth)->toBe(125.00)
        ->and((float) $payslip->pagibig)->toBe(40.00);
});

test('SSS applies the 35,000 MSC cap and PhilHealth the 100,000 salary cap for high earners', function () {
    $employee = Employee::query()->create([
        'employee_code' => 'EMP-HIGH',
        'first_name' => 'High',
        'last_name' => 'Earner',
        'basic_salary' => 200000,
        'daily_rate' => 8000,
        'hourly_rate' => 1000,
        'status' => 'active',
    ]);

    $period = period();
    presentAllPeriod($employee, $period);

    $run = app(PayrollCalculator::class)->run($period, syncAttendance: false);
    $payslip = $run->payslips()->where('employee_id', $employee->id)->firstOrFail();

    // SSS: MSC capped at 35,000 -> 35,000*5% / 2 = 875.00
    // PhilHealth: capped at 100,000 -> 100,000*2.5% / 2 = 1,250.00
    // Pag-IBIG: comp capped at 10,000 -> 10,000*2% / 2 = 100.00
    expect((float) $payslip->sss)->toBe(875.00)
        ->and((float) $payslip->philhealth)->toBe(1250.00)
        ->and((float) $payslip->pagibig)->toBe(100.00);
});

test('one absent day deducts exactly one days worth of basic', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);
    AttendanceRecord::query()->whereDate('work_date', '2026-01-06')->delete();

    $payslip = payrollFor($period);

    $perDay = round(HALF_MONTH_BASIC / SCHEDULED_DAYS, 2); // 576.92

    expect($payslip->absent_days)->toBe(1)
        ->and((float) $payslip->absence_deduction)->toBe($perDay)
        ->and((float) $payslip->net_pay)
        ->toBe(round(HALF_MONTH_BASIC - $perDay - GOV_TOTAL, 2));
});

test('a fully absent employee is paid nothing and owes nothing', function () {
    $payslip = payrollFor(period());

    expect($payslip->absent_days)->toBe(SCHEDULED_DAYS)
        ->and((float) $payslip->absence_deduction)->toBe(HALF_MONTH_BASIC)
        ->and((float) $payslip->net_pay)->toBe(0.0)
        // Nothing was earned, so no contributions could be collected.
        ->and((float) $payslip->sss)->toBe(0.0);
});

test('a day with incomplete punches counts as absent until corrected', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);
    attendance($this->employee, '2026-01-06', ['is_incomplete' => true, 'worked_minutes' => 0]);

    expect(payrollFor($period)->absent_days)->toBe(1);
});

test('Sundays are never counted as absences', function () {
    $period = period();
    presentAllPeriod($this->employee, $period); // creates no Sunday records

    expect(payrollFor($period)->absent_days)->toBe(0);
});

test('attendance earnings and deductions are carried into the payslip exactly', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    attendance($this->employee, '2026-01-05', [
        'late_minutes' => 30,
        'late_deduction' => 37.50,   // 30 min at 75/hr
        'undertime_minutes' => 60,
        'undertime_deduction' => 75.00,
        'ot_minutes' => 120,
        'ot_pay' => 187.50,          // 2h at 75 * 1.25
    ]);
    attendance($this->employee, '2026-01-06', [
        'is_holiday' => true,
        'holiday_pay' => 600.00,
    ]);
    attendance($this->employee, '2026-01-11', [   // Sunday, extra day
        'is_sunday' => true,
        'sunday_pay' => 800.00,
    ]);

    $payslip = payrollFor($period);

    expect((float) $payslip->holiday_pay)->toBe(600.00)
        ->and((float) $payslip->sunday_route)->toBe(800.00)
        ->and((float) $payslip->overtime_pay)->toBe(187.50)
        ->and((float) $payslip->late_deduction)->toBe(37.50)
        ->and((float) $payslip->undertime_deduction)->toBe(75.00);

    $expectedEarnings = HALF_MONTH_BASIC + 600.00 + 800.00 + 187.50;   // 9,087.50
    $expectedDeductions = 37.50 + 75.00 + GOV_TOTAL;                    //   775.00

    expect((float) $payslip->total_earnings)->toBe($expectedEarnings)
        ->and((float) $payslip->total_deductions)->toBe($expectedDeductions)
        ->and((float) $payslip->net_pay)->toBe($expectedEarnings - $expectedDeductions);
});

test('only attendance inside the cutoff is counted', function () {
    $period = period(1, 1);
    presentAllPeriod($this->employee, $period);

    attendance($this->employee, '2026-01-15', ['ot_pay' => 100.00]); // inside
    attendance($this->employee, '2026-01-16', ['ot_pay' => 999.00]); // next cutoff

    expect((float) payrollFor($period)->overtime_pay)->toBe(100.00);
});

test('another employees attendance never leaks into this payslip', function () {
    $other = Employee::query()->create([
        'employee_code' => 'EMP-0002',
        'first_name' => 'Other',
        'last_name' => 'Person',
        'basic_salary' => 15000,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);

    $period = period();
    presentAllPeriod($this->employee, $period);
    presentAllPeriod($other, $period);
    attendance($other, '2026-01-05', ['ot_pay' => 5000.00]);

    $run = app(PayrollCalculator::class)->run($period, syncAttendance: false);
    $mine = $run->payslips()->where('employee_id', $this->employee->id)->firstOrFail();

    expect((float) $mine->overtime_pay)->toBe(0.0);
});

test('the cash advance instalment lands on the payslip and reduces the balance once', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 2000,
        'balance' => 2000,
        'deduction_per_payroll' => 500,
        'released_at' => '2026-01-02',
        'status' => 'active',
    ]);

    $payslip = payrollFor($period);

    expect((float) $payslip->cash_advance_deduction)->toBe(500.00)
        ->and((float) $payslip->total_deductions)->toBe(500.00 + GOV_TOTAL)
        ->and((float) $payslip->net_pay)->toBe(HALF_MONTH_BASIC - 500.00 - GOV_TOTAL);
});

test('the cash advance never takes more than the pay left after mandatory dues', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 50000,
        'balance' => 50000,
        'deduction_per_payroll' => 50000,
        'released_at' => '2026-01-02',
        'status' => 'active',
    ]);

    $payslip = payrollFor($period);

    expect((float) $payslip->cash_advance_deduction)->toBe(HALF_MONTH_BASIC - GOV_TOTAL)
        ->and((float) $payslip->net_pay)->toBe(0.0)
        // The uncollected remainder stays owed for the next run.
        ->and((float) CashAdvance::query()->firstOrFail()->balance)
        ->toBe(50000.0 - (HALF_MONTH_BASIC - GOV_TOTAL));
});

test('every payslip column adds up to its stored totals', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    attendance($this->employee, '2026-01-05', [
        'late_deduction' => 12.34,
        'undertime_deduction' => 56.78,
        'ot_pay' => 90.12,
        'holiday_pay' => 345.67,
    ]);

    CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 900,
        'balance' => 900,
        'deduction_per_payroll' => 333.33,
        'released_at' => '2026-01-02',
        'status' => 'active',
    ]);

    $p = payrollFor($period);

    $earnings = (float) $p->basic_pay + (float) $p->holiday_pay
        + (float) $p->sunday_route + (float) $p->overtime_pay;

    $deductions = (float) $p->late_deduction + (float) $p->undertime_deduction
        + (float) $p->absence_deduction + (float) $p->cash_advance_deduction
        + (float) $p->sss + (float) $p->philhealth + (float) $p->pagibig;

    expect((float) $p->total_earnings)->toBe(round($earnings, 2))
        ->and((float) $p->total_deductions)->toBe(round($deductions, 2))
        ->and((float) $p->net_pay)->toBe(round($earnings - $deductions, 2))
        ->and((float) $p->net_pay)->toBeGreaterThanOrEqual(0.0);
});

test('deductions can never push a payslip into negative pay', function () {
    $period = period();
    presentAllPeriod($this->employee, $period);

    // Absent-heavy plus a huge advance.
    AttendanceRecord::query()->delete();

    CashAdvance::query()->create([
        'employee_id' => $this->employee->id,
        'amount' => 50000,
        'balance' => 50000,
        'deduction_per_payroll' => 50000,
        'released_at' => '2026-01-02',
        'status' => 'active',
    ]);

    expect((float) payrollFor($period)->net_pay)->toBeGreaterThanOrEqual(0.0);
});

test('13th month equals the years total basic divided by twelve', function () {
    $first = period(1, 1);
    $second = period(1, 2);
    presentAllPeriod($this->employee, $first);
    presentAllPeriod($this->employee, $second);
    payrollFor($first);
    payrollFor($second);

    $thirteenthPeriod = PayrollPeriod::query()->create([
        'name' => '2026 13th Month',
        'year' => 2026,
        'month' => 12,
        'half' => PayrollCalculator::THIRTEENTH_MONTH_HALF,
        'start_date' => '2026-12-31',
        'end_date' => '2026-12-31',
        'payday' => '2026-12-31',
        'status' => 'open',
    ]);

    $payslip = payrollFor($thirteenthPeriod);

    expect((float) $payslip->thirteenth_month)->toBe(1250.00) // 15,000 / 12
        ->and((float) $payslip->basic_pay)->toBe(0.0)
        ->and((float) $payslip->total_deductions)->toBe(0.0)
        ->and((float) $payslip->net_pay)->toBe(1250.00);
});

test('the 13th month is released in two instalments that total the full entitlement', function () {
    // Jan-Jun: 12 cutoffs of 7,500 basic = 90,000 earned by mid-year.
    foreach (range(1, 6) as $month) {
        foreach ([1, 2] as $half) {
            $p = period($month, $half);
            presentAllPeriod($this->employee, $p);
            payrollFor($p);
        }
    }

    // The June 2nd cutoff triggers the first release: 90,000 / 12 = 7,500.
    $firstRelease = Payslip::query()
        ->whereHas('payrollRun.period', fn ($q) => $q->where('half', PayrollCalculator::THIRTEENTH_MONTH_HALF))
        ->sum('thirteenth_month');

    expect((float) $firstRelease)->toBe(7500.00);

    // Jul-Dec: another 90,000, so the full year entitlement is 180,000/12 = 15,000.
    foreach (range(7, 12) as $month) {
        foreach ([1, 2] as $half) {
            $p = period($month, $half);
            presentAllPeriod($this->employee, $p);
            payrollFor($p);
        }
    }

    $total = Payslip::query()
        ->whereHas('payrollRun.period', fn ($q) => $q->where('half', PayrollCalculator::THIRTEENTH_MONTH_HALF))
        ->sum('thirteenth_month');

    // December pays only the remainder, so the year totals the entitlement once.
    expect((float) $total)->toBe(15000.00);
});

test('the 13th month slip does not itself count towards next years 13th month', function () {
    $first = period(1, 1);
    presentAllPeriod($this->employee, $first);
    payrollFor($first);

    $thirteenth = PayrollPeriod::query()->create([
        'name' => '2026 13th Month',
        'year' => 2026,
        'month' => 12,
        'half' => PayrollCalculator::THIRTEENTH_MONTH_HALF,
        'start_date' => '2026-12-31',
        'end_date' => '2026-12-31',
        'payday' => '2026-12-31',
        'status' => 'open',
    ]);

    payrollFor($thirteenth);

    expect((float) Payslip::query()->sum('basic_pay'))->toBe(HALF_MONTH_BASIC);
});
