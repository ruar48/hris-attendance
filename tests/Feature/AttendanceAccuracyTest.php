<?php

use App\Models\AttendanceRecord;
use App\Models\BiometricDevice;
use App\Models\BiometricLog;
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

    $this->device = BiometricDevice::query()->create([
        'name' => 'Gate',
        'serial_number' => 'ZK-TEST',
        'status' => 'online',
    ]);
});

function punch(Employee $employee, string $at, string $type, BiometricDevice $device): void
{
    BiometricLog::query()->create([
        'employee_id' => $employee->id,
        'biometric_device_id' => $device->id,
        'punched_at' => $at,
        'punch_type' => $type,
    ]);
}

function resolveAttendance(Employee $employee, string $date, ?Holiday $holiday = null)
{
    return app(AttendanceResolver::class)->resolveDay($employee, Carbon::parse($date), $holiday);
}

function fullDay(Employee $employee, string $date, BiometricDevice $device): void
{
    punch($employee, "{$date} 08:00:00", 'in', $device);
    punch($employee, "{$date} 17:00:00", 'out', $device);
}

test('a normal day on time produces no deductions and no overtime', function () {
    fullDay($this->employee, '2026-01-05', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect((float) $record->late_deduction)->toBe(0.0)
        ->and((float) $record->undertime_deduction)->toBe(0.0)
        ->and((float) $record->ot_pay)->toBe(0.0)
        ->and($record->is_incomplete)->toBeFalse()
        ->and($record->worked_minutes)->toBe(540);
});

test('lateness beyond grace is charged at the hourly rate', function () {
    punch($this->employee, '2026-01-05 08:30:00', 'in', $this->device);
    punch($this->employee, '2026-01-05 17:00:00', 'out', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->late_minutes)->toBe(30)
        ->and((float) $record->late_deduction)->toBe(37.50); // 30 min at 75/hr
});

test('overtime past the minimum pays at the multiplier', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in', $this->device);
    punch($this->employee, '2026-01-05 19:00:00', 'out', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->ot_minutes)->toBe(120)
        ->and((float) $record->ot_pay)->toBe(187.50); // 2h at 75 * 1.25
});

test('a lone out-punch is flagged incomplete instead of charging a phantom day late', function () {
    punch($this->employee, '2026-01-05 17:00:00', 'out', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeTrue()
        ->and($record->late_minutes)->toBe(0)
        ->and((float) $record->late_deduction)->toBe(0.0);
});

test('a missing out-punch is flagged incomplete rather than silently paid', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeTrue()
        ->and((float) $record->undertime_deduction)->toBe(0.0)
        ->and($record->worked_minutes)->toBe(0);
});

test('an incomplete day is repaired by the DTR fallback', function () {
    punch($this->employee, '2026-01-05 08:00:00', 'in', $this->device);
    expect(resolveAttendance($this->employee, '2026-01-05')->is_incomplete)->toBeTrue();

    DtrLog::query()->create([
        'employee_id' => $this->employee->id,
        'work_date' => '2026-01-05',
        'time_in' => '08:00:00',
        'time_out' => '17:00:00',
        'reason' => 'Device problem',
        'status' => 'approved',
    ]);

    $record = resolveAttendance($this->employee, '2026-01-05');

    expect($record->is_incomplete)->toBeFalse()
        ->and($record->worked_minutes)->toBe(540);
});

test('the OT rate setting no longer reprices late deductions', function () {
    PayrollSetting::setValue('ot_use_employee_hourly', '0');
    PayrollSetting::setValue('ot_fixed_hourly_rate', '300');

    punch($this->employee, '2026-01-05 09:00:00', 'in', $this->device);
    punch($this->employee, '2026-01-05 19:00:00', 'out', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-05');

    // Late still charged at the employee's own 75/hr...
    expect((float) $record->late_deduction)->toBe(75.00)
        // ...while OT uses the configured 300/hr: 2h * 300 * 1.25.
        ->and((float) $record->ot_pay)->toBe(750.00);
});

test('undertime minutes stored match the minutes actually charged', function () {
    PayrollSetting::setValue('undertime_deduction_unit', 'hour');

    punch($this->employee, '2026-01-05 08:00:00', 'in', $this->device);
    punch($this->employee, '2026-01-05 16:54:00', 'out', $this->device);

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
    punch($this->employee, '2026-01-05 08:00:00', 'in', $this->device);
    punch($this->employee, '2026-01-05 09:30:00', 'out', $this->device);

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

    fullDay($this->employee, '2026-01-05', $this->device);

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

    fullDay($this->employee, '2026-01-11', $this->device);

    $record = resolveAttendance($this->employee, '2026-01-11', $holiday);

    // max(600 holiday, 800 route) = 800, counted once.
    expect((float) $record->holiday_pay)->toBe(800.00)
        ->and((float) $record->sunday_pay)->toBe(0.0);
});

test('an employee with no route rate is not paid the default Sunday amount', function () {
    fullDay($this->employee, '2026-01-11', $this->device); // Sunday, route rate 0

    expect((float) resolveAttendance($this->employee, '2026-01-11')->sunday_pay)->toBe(0.0);
});

test('a route driver is paid their own Sunday rate', function () {
    $this->employee->update(['sunday_route_rate' => 800]);

    fullDay($this->employee, '2026-01-11', $this->device);

    expect((float) resolveAttendance($this->employee, '2026-01-11')->sunday_pay)->toBe(800.00);
});

test('Saturday work is recorded by the period sync', function () {
    punch($this->employee, '2026-01-10 08:00:00', 'in', $this->device); // Saturday
    punch($this->employee, '2026-01-10 19:00:00', 'out', $this->device);

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

    fullDay($this->employee, '2026-01-05', $this->device);

    // Default 2.00x => 600 premium, rather than silently paying nothing.
    expect((float) resolveAttendance($this->employee, '2026-01-05', $holiday)->holiday_pay)
        ->toBe(600.00);
});
