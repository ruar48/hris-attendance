<?php

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollSetting;
use App\Services\AttendanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
| Verification of the attendance engine against hand-computed values.
| Reference employee: hourly 75, daily 600. Shift 08:00-17:00 (9h), 5 min
| grace, OT minimum 60 min at 1.25x, holiday 2.00x.
*/

beforeEach(function () {
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
        'sunday_route_rate' => 0,
        'status' => 'active',
    ]);
});

/**
 * Encode one time on the employee's time sheet, the way HR types it in.
 */
function punch(Employee $employee, string $at, string $type): void
{
    $at = Carbon::parse($at);
    $field = $type === 'in' ? 'time_in' : 'time_out';

    $log = DtrLog::query()
        ->where('employee_id', $employee->id)
        ->whereDate('work_date', $at->toDateString())
        ->first();

    if ($log) {
        $log->update([$field => $at->format('H:i:s')]);

        return;
    }

    DtrLog::query()->create([
        'employee_id' => $employee->id,
        'work_date' => $at->toDateString(),
        $field => $at->format('H:i:s'),
        'status' => 'approved',
    ]);
}

function resolveAttendance(Employee $employee, string $date, ?Holiday $holiday = null)
{
    return app(AttendanceResolver::class)->resolveDay($employee, Carbon::parse($date), $holiday);
}

function fullDay(Employee $employee, string $date): void
{
    punch($employee, "{$date} 08:00:00", 'in');
    punch($employee, "{$date} 17:00:00", 'out');
}

test('a normal day on time produces no deductions and no overtime', function () {
    fullDay($this->employee, '2026-01-05');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect((float) $record->late_deduction)->toBe(0.0)
        ->and((float) $record->undertime_deduction)->toBe(0.0)
        ->and((float) $record->ot_pay)->toBe(0.0)
        ->and($record->is_incomplete)->toBeFalse()
        ->and($record->worked_minutes)->toBe(540);
});

test('lateness beyond grace is charged at the hourly rate', function () {
    punch($this->employee, '2026-01-05 08:30:00', 'in');
    punch($this->employee, '2026-01-05 17:00:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->late_minutes)->toBe(30)
        ->and((float) $record->late_deduction)->toBe(37.50); // 30 min at 75/hr
});

test('overtime past the minimum pays at the multiplier', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in');
    punch($this->employee, '2026-01-05 19:00:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->ot_minutes)->toBe(120)
        ->and((float) $record->ot_pay)->toBe(187.50); // 2h at 75 * 1.25
});

test('a lone time out is flagged incomplete instead of charging a phantom day late', function () {
    punch($this->employee, '2026-01-05 17:00:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeTrue()
        ->and($record->late_minutes)->toBe(0)
        ->and((float) $record->late_deduction)->toBe(0.0);
});

test('a missing time out is flagged incomplete rather than silently paid', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeTrue()
        ->and((float) $record->undertime_deduction)->toBe(0.0)
        ->and($record->worked_minutes)->toBe(0);
});

test('an incomplete day is repaired once the missing time is encoded', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in');
    expect(resolveAttendance($this->employee, '2026-01-05')->is_incomplete)->toBeTrue();

    punch($this->employee, '2026-01-05 17:00:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeFalse()
        ->and($record->worked_minutes)->toBe(540);
});

test('an afternoon shift written under PM Arrival is read from the earliest to the latest time', function () {
    // The paper DTR's 13:00 goes in the PM Arrival column, AM columns blank.
    DtrLog::query()->create([
        'employee_id' => $this->employee->id,
        'work_date' => '2026-01-05',
        'break_in' => '13:00:00',
        'time_out' => '22:00:00',
        'status' => 'approved',
    ]);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeFalse()
        ->and($record->time_in)->toBe('13:00:00')
        ->and($record->time_out)->toBe('22:00:00')
        ->and($record->worked_minutes)->toBe(540);
});

test('an arrival written under AM Departure still counts as the arrival', function () {
    DtrLog::query()->create([
        'employee_id' => $this->employee->id,
        'work_date' => '2026-01-05',
        'break_out' => '08:00:00',
        'time_out' => '17:00:00',
        'status' => 'approved',
    ]);

    expect(resolveAttendance($this->employee, '2026-01-05')->worked_minutes)->toBe(540);
});

test('a day off, absence or leave produces no payable record', function () {
    fullDay($this->employee, '2026-01-05');
    expect(resolveAttendance($this->employee, '2026-01-05'))->not->toBeNull();

    DtrLog::query()->where('employee_id', $this->employee->id)->update([
        'day_status' => 'absent',
        'time_in' => null,
        'time_out' => null,
    ]);

    expect(resolveAttendance($this->employee, '2026-01-05'))->toBeNull()
        ->and(AttendanceRecord::query()->count())->toBe(0);
});

test('the OT rate setting no longer reprices late deductions', function () {
    PayrollSetting::setValue('ot_use_employee_hourly', '0');
    PayrollSetting::setValue('ot_fixed_hourly_rate', '300');

    punch($this->employee, '2026-01-05 09:00:00', 'in');
    punch($this->employee, '2026-01-05 19:00:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    // Late still charged at the employee's own 75/hr...
    expect((float) $record->late_deduction)->toBe(75.00)
        // ...while OT uses the configured 300/hr: 2h * 300 * 1.25.
        ->and((float) $record->ot_pay)->toBe(750.00);
});

test('undertime minutes stored match the minutes actually charged', function () {
    PayrollSetting::setValue('undertime_deduction_unit', 'hour');

    punch($this->employee, '2026-01-05 08:00:00', 'in');
    punch($this->employee, '2026-01-05 16:54:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05');

    // Charged a full hour, so a full hour is what the record shows.
    expect($record->undertime_minutes)->toBe(60)
        ->and((float) $record->undertime_deduction)->toBe(75.00)
        ->and(round($record->undertime_minutes / 60 * 75, 2))
        ->toBe((float) $record->undertime_deduction);
});

test('holiday premium is prorated by the hours actually worked', function () {
    $holiday = Holiday::query()->create([
        'name' => 'Test Holiday',
        'date' => '2026-01-05',
        'type' => 'regular',
        'pay_multiplier' => 2.00,
    ]);

    // Worked 1h30 of a 9h shift.
    punch($this->employee, '2026-01-05 08:00:00', 'in');
    punch($this->employee, '2026-01-05 09:30:00', 'out');

    $record = resolveAttendance($this->employee, '2026-01-05', $holiday);

    expect((float) $record->holiday_pay)->toBe(100.00); // 600 * (90/540)
});

test('a full day on a holiday still pays the whole premium', function () {
    $holiday = Holiday::query()->create([
        'name' => 'Test Holiday',
        'date' => '2026-01-05',
        'type' => 'regular',
        'pay_multiplier' => 2.00,
    ]);

    fullDay($this->employee, '2026-01-05');

    expect((float) resolveAttendance($this->employee, '2026-01-05', $holiday)->holiday_pay)
        ->toBe(600.00);
});

test('a holiday on a Sunday pays the better premium, not both', function () {
    $this->employee->update(['sunday_route_rate' => 800]);

    $holiday = Holiday::query()->create([
        'name' => 'Sunday Holiday',
        'date' => '2026-01-11', // a Sunday
        'type' => 'regular',
        'pay_multiplier' => 2.00,
    ]);

    fullDay($this->employee, '2026-01-11');

    $record = resolveAttendance($this->employee, '2026-01-11', $holiday);

    // max(600 holiday, 800 route) = 800, counted once.
    expect((float) $record->holiday_pay)->toBe(800.00)
        ->and((float) $record->sunday_pay)->toBe(0.0);
});

test('an employee with no route rate is not paid the default Sunday amount', function () {
    fullDay($this->employee, '2026-01-11'); // Sunday, route rate 0

    expect((float) resolveAttendance($this->employee, '2026-01-11')->sunday_pay)->toBe(0.0);
});

test('a route driver is paid their own Sunday rate', function () {
    $this->employee->update(['sunday_route_rate' => 800]);

    fullDay($this->employee, '2026-01-11');

    expect((float) resolveAttendance($this->employee, '2026-01-11')->sunday_pay)->toBe(800.00);
});

test('Saturday work is recorded by the period sync', function () {
    punch($this->employee, '2026-01-10 08:00:00', 'in'); // Saturday
    punch($this->employee, '2026-01-10 19:00:00', 'out');

    app(AttendanceResolver::class)->syncPeriod(
        Carbon::parse('2026-01-05'),
        Carbon::parse('2026-01-11')
    );

    $record = AttendanceRecord::query()->whereDate('work_date', '2026-01-10')->first();

    expect($record)->not->toBeNull()
        ->and((float) $record->ot_pay)->toBe(187.50); // Saturday OT is paid
});

test('a holiday with a zero multiplier falls back to the configured default', function () {
    $holiday = Holiday::query()->create([
        'name' => 'Broken Holiday',
        'date' => '2026-01-05',
        'type' => 'regular',
        'pay_multiplier' => 0,
    ]);

    fullDay($this->employee, '2026-01-05');

    // Default 2.00x => 600 premium, rather than silently paying nothing.
    expect((float) resolveAttendance($this->employee, '2026-01-05', $holiday)->holiday_pay)
        ->toBe(600.00);
});
