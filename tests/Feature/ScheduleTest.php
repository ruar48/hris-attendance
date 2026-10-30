<?php

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\AttendancePeriodSummary;
use App\Services\AttendanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    OptionList::query()->create(['category' => 'work_location', 'value' => 'Batangas', 'label' => 'Batangas']);
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Taguig', 'label' => 'Taguig', 'sort_order' => 1]);

    $this->employee = Employee::query()->create([
        'employee_code' => 'B-001',
        'first_name' => 'Jimmar',
        'last_name' => 'Maderazo',
        'position' => 'Forklift Operator',
        'work_location' => 'Batangas',
        'time_in_schedule' => '6:00 AM',
        'time_out_schedule' => '3:00 PM',
        'hourly_rate' => 75,
        'daily_rate' => 600,
        'status' => 'active',
    ]);

    Employee::query()->create([
        'employee_code' => 'T-001',
        'first_name' => 'Enrico',
        'last_name' => 'Reyes',
        'work_location' => 'Taguig',
        'status' => 'active',
    ]);
});

test('the schedule shows one site\'s people in weekly blocks for the month', function () {
    $this->get(route('schedules.index', ['site' => 'Batangas', 'month' => '2026-08']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('schedules/index')
            ->where('site', 'Batangas')
            ->where('month', '2026-08')
            ->has('weeks', 6) // Aug 2026 touches six Monday-to-Sunday weeks
            ->has('weeks.0.rows', 1)
            ->where('weeks.0.rows.0.name', 'Jimmar Maderazo')
            ->where('weeks.0.days.0.dow', 'MON'));
});

test('days with no entry carry the manning shift and the dates the person is employed', function () {
    $shift = ShiftType::forTimes('6:00 AM', '3:00 PM');
    $this->employee->update(['hire_date' => '2026-08-10']);

    $this->get(route('schedules.index', ['site' => 'Batangas', 'month' => '2026-08']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('weeks.0.rows.0.regular_shift_type_id', $shift->id)
            ->where('weeks.0.rows.0.time', '6:00 AM - 3:00 PM')
            ->where('weeks.0.rows.0.active_from', '2026-08-10')
            ->where('weeks.0.rows.0.cells.0', '')); // no entry: works the Manning shift

    expect(Schedule::query()->count())->toBe(0);
});

test('cells save as a shift, a day off, leave, or cleared', function () {
    $shift = ShiftType::forTimes('6:00 AM', '3:00 PM');

    $this->post(route('schedules.bulk'), ['cells' => [
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-03', 'value' => "s:{$shift->id}"],
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-04', 'value' => 'off'],
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-05', 'value' => 'leave'],
    ]])->assertRedirect();

    $byDate = Schedule::query()->with('shiftType')->get()->keyBy(fn ($s) => $s->work_date->toDateString());

    expect($byDate['2026-08-03']->shift_type_id)->toBe($shift->id)
        ->and($byDate['2026-08-04']->shiftType->is_rest_day)->toBeTrue()
        ->and($byDate['2026-08-05']->status)->toBe('leave');

    $this->post(route('schedules.bulk'), ['cells' => [
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-05', 'value' => ''],
    ]]);

    expect(Schedule::query()->count())->toBe(2);
});

test('sick, vacation and emergency leave are excused in payroll but an absence is not', function () {
    $this->post(route('schedules.bulk'), ['cells' => [
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-03', 'value' => 'sick_leave'],
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-04', 'value' => 'vacation_leave'],
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-05', 'value' => 'emergency_leave'],
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-06', 'value' => 'absent'],
    ]])->assertSessionHasNoErrors();

    // Mon 3 – Thu 6 Aug, nothing worked.
    $summary = AttendancePeriodSummary::build(
        $this->employee,
        Carbon::parse('2026-08-03'),
        Carbon::parse('2026-08-06'),
        collect()
    );

    expect($summary['leave'])->toBe(3)
        ->and($summary['absent'])->toBe(1);
});

test('late and overtime are measured against the person\'s own shift', function () {
    // Regular Manning shift 6:00-15:00, no schedule entry: in 6:00, out 17:00 is 2h OT.
    DtrLog::query()->create([
        'employee_id' => $this->employee->id, 'work_date' => '2026-08-03',
        'time_in' => '06:00:00', 'time_out' => '17:00:00', 'status' => 'approved',
    ]);

    $record = app(AttendanceResolver::class)->resolveDay($this->employee, Carbon::parse('2026-08-03'));

    expect($record->late_minutes)->toBe(0)
        ->and($record->ot_minutes)->toBe(120);

    // Scheduled onto the 1:00-10:00 PM shift that day instead: leaving at
    // 17:00 is now five hours of undertime and no OT, and saving the schedule
    // recomputes the already-encoded day.
    $mid = ShiftType::forTimes('1:00 PM', '10:00 PM');
    $this->post(route('schedules.bulk'), ['cells' => [
        ['employee_id' => $this->employee->id, 'work_date' => '2026-08-03', 'value' => "s:{$mid->id}"],
    ]]);

    $record = AttendanceRecord::query()->sole();
    expect($record->undertime_minutes)->toBe(300)
        ->and($record->late_minutes)->toBe(0)
        ->and($record->ot_minutes)->toBe(0);
});
