<?php

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Abigail Paje']);
    $this->actingAs($this->user);

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    $this->employee = Employee::query()->create([
        'employee_code' => 'EMP-0001',
        'first_name' => 'Don Jeric',
        'last_name' => 'Del Pilar',
        'basic_salary' => 15000,
        'daily_rate' => 600,
        'hourly_rate' => 75,
        'status' => 'active',
    ]);
});

function saveTimesheet(array $rows)
{
    return test()->put(route('timesheet.update'), [
        'employee_id' => test()->employee->id,
        'rows' => $rows,
    ]);
}

function sheetRow(string $date, ?string $in, ?string $out, string $status = 'worked', ?string $remarks = null): array
{
    return [
        'date' => $date,
        'time_in' => $in,
        'time_out' => $out,
        'day_status' => $status,
        'remarks' => $remarks,
    ];
}

test('the time sheet lists every day of the selected month', function () {
    $this->get(route('timesheet.index', ['employee_id' => $this->employee->id, 'month' => '2026-02']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('timesheet/index')
            ->where('month', '2026-02')
            ->has('days', 28));
});

test('the full-screen workbook shows the schedule tab with a row per scheduled person', function () {
    $shift = ShiftType::query()->create([
        'code' => 1, 'name' => 'Morning', 'time_in' => '07:00:00', 'time_out' => '16:00:00', 'is_rest_day' => false,
    ]);
    Schedule::query()->create([
        'employee_id' => $this->employee->id, 'work_date' => '2025-12-15', 'shift_type_id' => $shift->id, 'status' => 'working',
    ]);

    $this->get(route('timesheet.workbook', ['month' => '2025-12', 'sheet' => 'schedule']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('timesheet/workbook')
            ->where('sheet', 'schedule')
            ->has('schedule', 5) // Dec 2025 touches five Monday-to-Sunday weeks
            ->where('schedule.2.rows.0.name', 'Don Jeric Del Pilar')
            ->where('schedule.2.rows.0.time', '7:00AM – 4:00PM')
            ->where('schedule.2.rows.0.cells.0', 'work'));
});

test('the workbook opens on the selected site\'s manning tab, grouped by role', function () {
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Taguig', 'label' => 'Taguig', 'time_in' => '6:00 AM', 'time_out' => '6:00 AM']);
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Batangas', 'label' => 'Batangas', 'sort_order' => 1]);
    OptionList::query()->create(['category' => 'shift_schedule', 'value' => '0600-1500', 'label' => '6:00 am - 3:00 pm', 'time_in' => '6:00 AM', 'time_out' => '3:00 PM']);

    $this->employee->update(['work_location' => 'Taguig', 'position' => 'Forklift Operator', 'shift_schedule' => '0600-1500']);
    Employee::query()->create([
        'employee_code' => 'EMP-0002', 'first_name' => 'Jimmar', 'last_name' => 'Maderazo',
        'position' => 'Forklift Operator', 'work_location' => 'Batangas', 'status' => 'active',
    ]);

    $this->get(route('timesheet.workbook', ['site' => 'Taguig']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sheet', 'manning')
            ->where('site', 'Taguig')
            ->has('employees', 1) // only Taguig's people get tabs
            ->where('manning.title', 'Taguig Manning '.now()->year)
            ->where('manning.operation_hours', '6:00 AM - 6:00 AM')
            ->where('manning.groups.1.title', 'Forklift Operators')
            ->where('manning.groups.1.rows.0.name', 'Don Jeric Del Pilar')
            ->where('manning.groups.1.rows.0.schedule', '6:00 am - 3:00 pm'));
});

test('the manning tab sets a schedule time and moves people between sites', function () {
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Taguig', 'label' => 'Taguig']);
    OptionList::query()->create(['category' => 'shift_schedule', 'value' => '0700-1600', 'label' => '7:00 am - 4:00 pm', 'time_in' => '7:00 AM', 'time_out' => '4:00 PM']);

    $this->put(route('timesheet.manning.update', $this->employee), ['work_location' => 'Taguig'])->assertRedirect();
    $this->put(route('timesheet.manning.update', $this->employee), ['shift_schedule' => '0700-1600'])->assertRedirect();

    $employee = $this->employee->fresh();
    expect($employee->work_location)->toBe('Taguig')
        ->and($employee->shift_schedule)->toBe('0700-1600')
        ->and($employee->time_in_schedule)->toBe('7:00 AM');

    $this->put(route('timesheet.manning.update', $this->employee), ['work_location' => 'Nowhere'])
        ->assertSessionHasErrors('work_location');
});

test('encoding a day saves it, resolves attendance and logs who did it', function () {
    saveTimesheet([sheetRow('2026-01-05', '08:00', '19:00')])->assertRedirect();

    $record = AttendanceRecord::query()->firstOrFail();
    expect($record->ot_minutes)->toBe(120)
        ->and($record->source)->toBe('dtr');

    $log = AttendanceAuditLog::query()->sole();
    expect($log->action)->toBe('created')
        ->and($log->user_id)->toBe($this->user->id)
        ->and($log->changes['time_in'])->toBe([null, '08:00'])
        ->and($log->changes['time_out'])->toBe([null, '19:00']);

    expect(DtrLog::query()->sole()->encoded_by)->toBe($this->user->id);
});

test('editing a day logs only the fields that changed', function () {
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00')]);
    saveTimesheet([sheetRow('2026-01-05', '08:20', '17:00', remarks: 'Traffic')]);

    $log = AttendanceAuditLog::query()->latest('id')->first();

    expect($log->action)->toBe('updated')
        ->and($log->changes)->toBe([
            'time_in' => ['08:00', '08:20'],
            'remarks' => [null, 'Traffic'],
        ])
        ->and(AttendanceRecord::query()->sole()->late_minutes)->toBe(20);
});

test('the AM departure and PM arrival columns are saved and logged', function () {
    saveTimesheet([[
        ...sheetRow('2026-01-05', '08:00', '17:00'),
        'break_out' => '12:00',
        'break_in' => '13:00',
    ]]);

    $entry = DtrLog::query()->sole();

    expect($entry->break_out)->toBe('12:00:00')
        ->and($entry->break_in)->toBe('13:00:00')
        ->and(AttendanceAuditLog::query()->sole()->changes['break_out'])->toBe([null, '12:00']);
});

test('saving an unchanged day writes no audit entry', function () {
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00')]);
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00')]);

    expect(AttendanceAuditLog::query()->count())->toBe(1);
});

test('marking a day off clears its times and its payable record', function () {
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00')]);
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00', 'day_off')]);

    $entry = DtrLog::query()->sole();

    expect($entry->day_status)->toBe('day_off')
        ->and($entry->time_in)->toBeNull()
        ->and(AttendanceRecord::query()->count())->toBe(0)
        ->and(AttendanceAuditLog::query()->latest('id')->first()->changes['day_status'])->toBe(['worked', 'day off']);
});

test('clearing a day removes the entry and logs the deletion', function () {
    saveTimesheet([sheetRow('2026-01-05', '08:00', '17:00')]);
    saveTimesheet([sheetRow('2026-01-05', null, null)]);

    expect(DtrLog::query()->count())->toBe(0)
        ->and(AttendanceRecord::query()->count())->toBe(0)
        ->and(AttendanceAuditLog::query()->latest('id')->first()->action)->toBe('deleted');
});

test('a time out earlier than time in is rejected', function () {
    saveTimesheet([sheetRow('2026-01-05', '17:00', '08:00')])
        ->assertSessionHasErrors('rows.0.time_out');

    expect(DtrLog::query()->count())->toBe(0)
        ->and(AttendanceAuditLog::query()->count())->toBe(0);
});
