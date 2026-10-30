<?php

use App\Models\AttendanceAuditLog;
use App\Models\Employee;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    $this->ana = User::factory()->create(['name' => 'Ana Encoder']);
    $this->ben = User::factory()->create(['name' => 'Ben Encoder']);

    $this->employee = Employee::query()->create([
        'employee_code' => '25167',
        'first_name' => 'Gian Paolo',
        'last_name' => 'Lasala',
        'work_location' => 'Batangas',
        'status' => 'active',
    ]);
});

function encodeDay(User $user, Employee $employee, string $date, string $in, string $out): void
{
    test()->actingAs($user)->put(route('timesheet.update'), [
        'employee_id' => $employee->id,
        'rows' => [[
            'date' => $date, 'time_in' => $in, 'time_out' => $out, 'day_status' => 'worked', 'remarks' => null,
        ]],
    ]);
}

test('every time sheet change is logged with who made it and from which computer', function () {
    encodeDay($this->ana, $this->employee, '2026-10-05', '06:54', '16:11');

    $log = AttendanceAuditLog::query()->sole();

    expect($log->user_id)->toBe($this->ana->id)
        ->and($log->source)->toBe('timesheet')
        ->and($log->ip_address)->toBe('127.0.0.1');
});

test('a day\'s history shows who changed a time to the wrong value', function () {
    encodeDay($this->ana, $this->employee, '2026-10-05', '06:54', '16:11');
    encodeDay($this->ben, $this->employee, '2026-10-05', '09:54', '16:11'); // the mistake

    $this->actingAs($this->ana)
        ->get(route('attendance-logs.index', ['employee_id' => $this->employee->id, 'work_date' => '2026-10-05']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('attendance-logs/index')
            ->has('logs.data', 2)
            ->where('logs.data.0.user', 'Ben Encoder')
            ->where('logs.data.0.action', 'updated')
            ->where('logs.data.0.changes.time_in', ['06:54', '09:54'])
            ->where('logs.data.1.user', 'Ana Encoder'));
});

test('the log filters by who made the change and summarises each person', function () {
    encodeDay($this->ana, $this->employee, '2026-10-05', '06:54', '16:11');
    encodeDay($this->ana, $this->employee, '2026-10-06', '06:58', '16:13');
    encodeDay($this->ben, $this->employee, '2026-10-07', '06:59', '16:00');

    $this->actingAs($this->ana)
        ->get(route('attendance-logs.index', ['user_id' => $this->ben->id]))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.user', 'Ben Encoder'));

    $this->actingAs($this->ana)
        ->get(route('attendance-logs.index'))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.0.name', 'Ana Encoder')
            ->where('summary.0.created', 2)
            ->where('summary.1.name', 'Ben Encoder'));
});

test('schedule changes are logged too', function () {
    $this->actingAs($this->ben)->post(route('schedules.bulk'), ['cells' => [
        ['employee_id' => $this->employee->id, 'work_date' => '2026-10-05', 'value' => 'sick_leave'],
    ]]);

    $log = AttendanceAuditLog::query()->sole();

    expect($log->source)->toBe('schedule')
        ->and($log->user_id)->toBe($this->ben->id)
        ->and($log->changes)->toBe(['schedule' => ['Manning shift', 'Sick Leave']]);
});

test('manning changes are logged: joining a site and changing the schedule time', function () {
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Taguig', 'label' => 'Taguig']);
    OptionList::query()->create(['category' => 'shift_schedule', 'value' => '0700-1600', 'label' => '7:00 am - 4:00 pm', 'time_in' => '7:00 AM', 'time_out' => '4:00 PM']);
    $this->employee->update(['work_location' => null]);

    $this->actingAs($this->ana)->put(route('timesheet.manning.update', $this->employee), ['work_location' => 'Taguig']);
    $this->actingAs($this->ben)->put(route('timesheet.manning.update', $this->employee), ['shift_schedule' => '0700-1600']);

    $logs = AttendanceAuditLog::query()->where('source', 'manning')->orderBy('id')->get();

    expect($logs)->toHaveCount(2)
        ->and($logs[0]->action)->toBe('created')
        ->and($logs[0]->user_id)->toBe($this->ana->id)
        ->and($logs[0]->work_date)->toBeNull()
        ->and($logs[0]->changes)->toBe(['site' => [null, 'Taguig']])
        ->and($logs[1]->user_id)->toBe($this->ben->id)
        ->and($logs[1]->changes)->toBe(['shift_schedule' => [null, '7:00 am - 4:00 pm']]);

    $this->actingAs($this->ana)
        ->get(route('attendance-logs.index', ['source' => 'manning']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 2)
            ->where('logs.data.0.source', 'Manning')
            ->where('logs.data.0.work_date', null));
});

test('each change keeps the site it was made for, even after the person moves', function () {
    encodeDay($this->ana, $this->employee, '2026-10-05', '06:54', '16:11'); // at Batangas
    $this->employee->update(['work_location' => 'Taguig']);
    encodeDay($this->ana, $this->employee, '2026-10-06', '06:54', '16:11'); // now at Taguig

    $this->actingAs($this->ana)
        ->get(route('attendance-logs.index', ['site' => 'Batangas']))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.site', 'Batangas')
            ->where('logs.data.0.work_date', '2026-10-05'));
});

test('the log can be exported as a CSV', function () {
    encodeDay($this->ana, $this->employee, '2026-10-05', '06:54', '16:11');

    $response = $this->actingAs($this->ana)->get(route('attendance-logs.export'));

    $response->assertOk();
    expect($response->streamedContent())
        ->toContain('Changed by')
        ->toContain('Ana Encoder')
        ->toContain('time_in: — → 06:54');
});
