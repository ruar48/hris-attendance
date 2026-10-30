<?php

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Models\User;
use App\Services\AttendancePeriodSummary;
use App\Services\AttendanceResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

/*
| Reference rates: hourly 75, daily 600. Default shift 08:00-17:00.
*/

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    foreach (PayrollSetting::defaults() as $key => $value) {
        PayrollSetting::setValue($key, $value);
    }

    OptionList::query()->create(['category' => 'work_location', 'value' => 'Tayabas', 'label' => 'Tayabas']);
    OptionList::query()->create(['category' => 'work_location', 'value' => 'Taguig', 'label' => 'Taguig', 'sort_order' => 1]);

    $make = fn (string $code, string $site, array $extra = []) => Employee::query()->create([
        'employee_code' => $code, 'first_name' => $code, 'last_name' => 'Test',
        'work_location' => $site, 'basic_salary' => 15600, 'daily_rate' => 600, 'hourly_rate' => 75,
        'status' => 'active', ...$extra,
    ]);

    $this->tayabas = $make('T-1', 'Tayabas');
    $this->taguig = $make('G-1', 'Taguig');
});

function encodeFor(Employee $employee, string $date, ?string $in, ?string $out, string $status = 'worked'): ?AttendanceRecord
{
    DtrLog::query()->updateOrCreate(
        ['employee_id' => $employee->id, 'work_date' => $date],
        ['time_in' => $in ? "{$in}:00" : null, 'time_out' => $out ? "{$out}:00" : null, 'day_status' => $status, 'status' => 'approved'],
    );

    return app(AttendanceResolver::class)->resolveDay($employee, Carbon::parse($date));
}

test('a local holiday pays only the people at its own site', function () {
    $this->post(route('holidays.store'), [
        'name' => 'Tayabas Town Fiesta', 'site' => 'Tayabas', 'is_recurring' => false,
        'date' => '2026-05-15', 'type' => 'special', 'pay_multiplier' => 1.30,
    ])->assertSessionHasNoErrors();

    $atTayabas = encodeFor($this->tayabas, '2026-05-15', '08:00', '17:00');
    $atTaguig = encodeFor($this->taguig, '2026-05-15', '08:00', '17:00');

    expect($atTayabas->is_holiday)->toBeTrue()
        ->and((float) $atTayabas->holiday_pay)->toBe(180.0) // 600 × 0.30
        ->and($atTaguig->is_holiday)->toBeFalse()
        ->and((float) $atTaguig->holiday_pay)->toBe(0.0);
});

test('a local holiday is not an absence at its site, but is a working day elsewhere', function () {
    Holiday::query()->create([
        'name' => 'Tayabas Town Fiesta', 'site' => 'Tayabas', 'is_recurring' => false,
        'date' => '2026-05-15', 'type' => 'special', 'pay_multiplier' => 1.30,
    ]);

    $day = Carbon::parse('2026-05-15'); // a Friday, nothing encoded

    expect(AttendancePeriodSummary::build($this->tayabas, $day, $day, collect())['absent'])->toBe(0)
        ->and(AttendancePeriodSummary::build($this->taguig, $day, $day, collect())['absent'])->toBe(1);
});

test('two sites can each have their own holiday on the same date', function () {
    foreach (['Tayabas', 'Taguig'] as $site) {
        $this->post(route('holidays.store'), [
            'name' => "{$site} fiesta", 'site' => $site, 'is_recurring' => false,
            'date' => '2026-05-15', 'type' => 'special', 'pay_multiplier' => 1.30,
        ])->assertSessionHasNoErrors();
    }

    expect(Holiday::query()->count())->toBe(2);
});

test('an overnight shift is measured across midnight with night differential', function () {
    // Manning time 6:00 PM – 6:00 AM.
    $this->taguig->update(['time_in_schedule' => '6:00 PM', 'time_out_schedule' => '6:00 AM']);

    $record = encodeFor($this->taguig, '2026-05-11', '18:00', '07:00');

    expect($record->is_incomplete)->toBeFalse()
        ->and($record->worked_minutes)->toBe(780)          // 18:00 → 07:00 next day
        ->and($record->late_minutes)->toBe(0)
        ->and($record->undertime_minutes)->toBe(0)
        ->and($record->ot_minutes)->toBe(60)                // past the 06:00 end
        ->and($record->night_minutes)->toBe(480)            // 22:00 → 06:00
        ->and((float) $record->night_diff_pay)->toBe(60.0); // 8h × 75 × 10%
});

test('arriving after midnight on a night shift is late for that same night', function () {
    $this->taguig->update(['time_in_schedule' => '6:00 PM', 'time_out_schedule' => '6:00 AM']);

    $record = encodeFor($this->taguig, '2026-05-11', '00:30', '06:00');

    expect($record->late_minutes)->toBe(390) // 18:00 → 00:30
        ->and($record->worked_minutes)->toBe(330);
});

test('a day shift earns no night differential', function () {
    expect(encodeFor($this->tayabas, '2026-05-11', '08:00', '17:00')->night_minutes)->toBe(0);
});

test('leave past the yearly paid-leave limit is unpaid and counted as absent', function () {
    PayrollSetting::setValue('paid_leave_days_per_year', 2);

    // One leave earlier in the year uses up one of the two paid days.
    encodeFor($this->tayabas, '2026-03-02', null, null, 'leave');

    foreach (['2026-05-11', '2026-05-12', '2026-05-13'] as $date) {
        encodeFor($this->tayabas, $date, null, null, 'leave');
    }

    $summary = AttendancePeriodSummary::build(
        $this->tayabas,
        Carbon::parse('2026-05-11'),
        Carbon::parse('2026-05-13'),
        collect()
    );

    expect($summary['leave'])->toBe(1)       // the one paid day left
        ->and($summary['unpaidLeave'])->toBe(2)
        ->and($summary['absent'])->toBe(2);
});
