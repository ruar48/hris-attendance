<?php

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\Payslip;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| End to end: what's encoded on the Time Sheet, with the holidays calendar,
| is what the payslip pays. Reference employee: hourly 75, daily 600, shift
| 08:00-17:00 (the payroll settings default), 5 min grace, OT 1.25x after
| 60 min, regular holiday 2.00x.
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    $this->employee = Employee::query()->create([
        'employee_code' => 'EMP-0001',
        'first_name' => 'Jimmar',
        'last_name' => 'Maderazo',
        'basic_salary' => 15600,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);

    // Independence Day, a regular holiday every June 12.
    Holiday::query()->create([
        'name' => 'Independence Day', 'is_recurring' => true, 'month' => 6, 'day' => 12,
        'type' => 'regular', 'pay_multiplier' => 2.00,
    ]);

    $this->period = PayrollPeriod::query()->create([
        'name' => 'June 1-15', 'year' => 2026, 'month' => 6, 'half' => 1,
        'start_date' => '2026-06-01', 'end_date' => '2026-06-15', 'payday' => '2026-06-20', 'status' => 'pending',
    ]);
});

function encode(array $rows): void
{
    test()->put(route('timesheet.update'), [
        'employee_id' => test()->employee->id,
        'rows' => array_map(fn ($row) => [
            'time_in' => null, 'time_out' => null, 'day_status' => 'worked', 'remarks' => null, ...$row,
        ], $rows),
    ])->assertSessionHasNoErrors();
}

test('what is encoded on the time sheet is what the payslip pays', function () {
    encode([
        ['date' => '2026-06-01', 'time_in' => '08:00', 'time_out' => '17:00'], // on time
        ['date' => '2026-06-02', 'time_in' => '08:30', 'time_out' => '17:00'], // 30 min late
        ['date' => '2026-06-03', 'time_in' => '08:00', 'time_out' => '19:00'], // 2h OT
        ['date' => '2026-06-04', 'day_status' => 'day_off'],                   // Thursday off: not an absence
        ['date' => '2026-06-05', 'day_status' => 'leave'],                     // leave: not an absence
        ['date' => '2026-06-08', 'day_status' => 'absent'],                    // a missed day
        ['date' => '2026-06-12', 'time_in' => '08:00', 'time_out' => '17:00'], // worked the holiday
    ]);

    $this->post(route('payroll.run'), ['payroll_period_id' => $this->period->id]);

    $payslip = Payslip::query()->where('employee_id', $this->employee->id)->sole();

    // Mon–Sat Jun 1–15 = 13 days, less the Jun 12 holiday = 12, less the
    // day off and the leave = 10 scheduled. 3 worked (Jun 1, 2, 3), so 7
    // missed: Jun 8 (absent) and the blank Jun 6, 9, 10, 11, 13 and 15.
    expect((float) $payslip->holiday_pay)->toBe(600.0)        // 600 × (2.00 − 1)
        ->and((float) $payslip->overtime_pay)->toBe(187.5)    // 2h × 75 × 1.25
        ->and((float) $payslip->late_deduction)->toBe(37.5)   // 30 min × 75/h
        ->and($payslip->absent_days)->toBe(7);
});

test('adding a holiday updates the days already encoded on it', function () {
    encode([['date' => '2026-06-19', 'time_in' => '08:00', 'time_out' => '17:00']]);
    expect((float) AttendanceRecord::query()->sole()->holiday_pay)->toBe(0.0);

    $this->post(route('holidays.store'), [
        'name' => 'Local fiesta', 'is_recurring' => false, 'date' => '2026-06-19',
        'type' => 'special', 'pay_multiplier' => 1.30,
    ])->assertSessionHasNoErrors();

    expect((float) AttendanceRecord::query()->sole()->holiday_pay)->toBe(180.0); // 600 × 0.30

    $this->delete(route('holidays.destroy', Holiday::query()->where('name', 'Local fiesta')->sole()));

    expect((float) AttendanceRecord::query()->sole()->holiday_pay)->toBe(0.0);
});
