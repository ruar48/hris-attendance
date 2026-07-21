<?php

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\PayrollCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function makeEmployees(int $count): void
{
    for ($i = 1; $i <= $count; $i++) {
        Employee::query()->create([
            'employee_code' => sprintf('EMP-%04d', $i),
            'first_name' => "First{$i}",
            'last_name' => "Last{$i}",
            'basic_salary' => 15000,
            'daily_rate' => 600,
            'hourly_rate' => 75,
            'status' => 'active',
        ]);
    }
}

function makePeriod(int $month): PayrollPeriod
{
    return PayrollPeriod::query()->create([
        'name' => "Period {$month}",
        'year' => 2026,
        'month' => $month,
        'half' => 1,
        'start_date' => "2026-{$month}-01",
        'end_date' => "2026-{$month}-15",
        'payday' => "2026-{$month}-20",
        'status' => 'pending',
    ]);
}

test('the employee list is paginated at 15 per page by default', function () {
    makeEmployees(20);

    $this->get(route('employees.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('employees/index')
            ->has('employees.data', 15)
            ->where('employees.total', 20)
            ->where('employees.last_page', 2)
            ->where('employees.from', 1)
            ->where('employees.to', 15)
            ->where('filters.per_page', 15)
            ->has('perPageOptions')
        );
});

test('the second page returns the remaining employees', function () {
    makeEmployees(20);

    $this->get(route('employees.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 5)
            ->where('employees.current_page', 2)
            ->where('employees.from', 16)
            ->where('employees.to', 20)
        );
});

test('per_page can be changed to an allowed option', function () {
    makeEmployees(60);

    $this->get(route('employees.index', ['per_page' => 50]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 50)
            ->where('employees.last_page', 2)
            ->where('filters.per_page', 50)
        );
});

test('an invalid per_page falls back to the default', function () {
    makeEmployees(20);

    $this->get(route('employees.index', ['per_page' => 9999]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 15)
            ->where('filters.per_page', 15)
        );
});

test('search filters the list and survives pagination', function () {
    makeEmployees(20);

    $this->get(route('employees.index', ['search' => 'EMP-0007']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.total', 1)
            ->where('filters.search', 'EMP-0007')
        );
});

test('a new employee can be registered with an auto-generated code', function () {
    makeEmployees(3);

    $this->post(route('employees.store'), [
        'first_name' => 'Jose',
        'last_name' => 'Rizal',
        'basic_salary' => 20000,
        'daily_rate' => 800,
        'hourly_rate' => 100,
    ])->assertRedirect();

    $employee = Employee::query()->where('last_name', 'Rizal')->firstOrFail();

    expect($employee->employee_code)->toBe('EMP-0004')
        ->and($employee->status)->toBe('active');
});

test('an employee can be edited', function () {
    makeEmployees(2);
    $employee = Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail();

    $this->put(route('employees.update', $employee), [
        'employee_code' => 'EMP-0002',
        'first_name' => 'Gregoria',
        'last_name' => 'De Jesus',
        'position' => 'Supervisor',
        'basic_salary' => 25000,
        'daily_rate' => 1000,
        'hourly_rate' => 125,
        'status' => 'inactive',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $employee->refresh();

    expect($employee->first_name)->toBe('Gregoria')
        ->and($employee->position)->toBe('Supervisor')
        ->and((float) $employee->basic_salary)->toBe(25000.0)
        ->and($employee->status)->toBe('inactive');
});

test('editing keeps the employees own code and rejects another employees code', function () {
    makeEmployees(2);
    $employee = Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail();

    // Its own code must not collide with itself.
    $this->put(route('employees.update', $employee), [
        'employee_code' => 'EMP-0002',
        'first_name' => 'Same',
        'last_name' => 'Code',
        'basic_salary' => 1,
        'daily_rate' => 1,
        'hourly_rate' => 1,
    ])->assertSessionHasNoErrors();

    // But another employee's code must be rejected.
    $this->put(route('employees.update', $employee), [
        'employee_code' => 'EMP-0001',
        'first_name' => 'Taken',
        'last_name' => 'Code',
        'basic_salary' => 1,
        'daily_rate' => 1,
        'hourly_rate' => 1,
    ])->assertSessionHasErrors('employee_code');
});

test('an employee can be archived and hidden from the default list', function () {
    makeEmployees(3);
    $employee = Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail();

    $this->delete(route('employees.destroy', $employee))->assertRedirect();

    expect($employee->fresh()->trashed())->toBeTrue()
        ->and(Employee::query()->count())->toBe(2);

    $this->get(route('employees.index'))
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 2)
            ->where('archivedCount', 1)
        );
});

test('archived employees are listed and can be restored', function () {
    makeEmployees(2);
    $employee = Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail();
    $employee->delete();

    $this->get(route('employees.index', ['archived' => 1]))
        ->assertInertia(fn ($page) => $page
            ->has('employees.data', 1)
            ->where('employees.data.0.employee_code', 'EMP-0002')
            ->where('employees.data.0.archived', true)
        );

    $this->put(route('employees.restore', $employee->id))->assertRedirect();

    expect(Employee::query()->count())->toBe(2)
        ->and($employee->fresh()->trashed())->toBeFalse();
});

test('an archived employee code is not reused by the code generator', function () {
    makeEmployees(3);
    Employee::query()->where('employee_code', 'EMP-0003')->firstOrFail()->delete();

    $this->post(route('employees.store'), [
        'first_name' => 'Apolinario',
        'last_name' => 'Mabini',
        'basic_salary' => 1,
        'daily_rate' => 1,
        'hourly_rate' => 1,
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect(Employee::query()->where('last_name', 'Mabini')->value('employee_code'))->toBe('EMP-0004');
});

test('actions flash a typed toast for the client', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    $this->post(route('employees.store'), [
        'first_name' => 'Melchora',
        'last_name' => 'Aquino',
        'basic_salary' => 1,
        'daily_rate' => 1,
        'hourly_rate' => 1,
    ]);
    expect(session('inertia.flash_data')['toast'] ?? null)
        ->toBe(['type' => 'success', 'message' => 'Employee registered successfully.']);

    $this->delete(route('employees.destroy', $employee));
    expect(session('inertia.flash_data')['toast']['type'] ?? null)->toBe('warning');

    $this->put(route('employees.restore', $employee->id));
    expect(session('inertia.flash_data')['toast']['type'] ?? null)->toBe('success');
});

test('archiving an employee keeps their name on historical records', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    $advance = CashAdvance::query()->create([
        'employee_id' => $employee->id,
        'amount' => 1000,
        'balance' => 1000,
        'released_at' => '2026-01-10',
        'status' => 'active',
    ]);

    $employee->delete();

    // The historical record must still resolve the (archived) employee.
    expect($advance->fresh()->employee?->full_name)->toBe('First1 Last1');
});

test('archived employees are excluded from payroll', function () {
    makeEmployees(3);
    Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail()->delete();

    expect(Employee::query()->active()->pluck('employee_code')->all())
        ->toBe(['EMP-0001', 'EMP-0003']);
});

test('an archived employee gets no payslip, and is paid again once restored', function () {
    makeEmployees(3);
    $archived = Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail();
    $archived->delete();

    $calculator = app(PayrollCalculator::class);

    // First run while archived — they must be skipped entirely.
    $run = $calculator->run(makePeriod(1), syncAttendance: false);

    expect($run->payslips()->count())->toBe(2)
        ->and($run->total_employees)->toBe(2)
        ->and($run->payslips()->pluck('employee_id'))->not->toContain($archived->id);

    // Restore them, then run the next period — they are back on the payroll.
    $archived->restore();
    $nextRun = $calculator->run(makePeriod(2), syncAttendance: false);

    expect($nextRun->payslips()->count())->toBe(3)
        ->and($nextRun->total_employees)->toBe(3)
        ->and($nextRun->payslips()->pluck('employee_id')->all())->toContain($archived->id);
});

test('someone who worked until the 10th is still paid, prorated, for that period', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail(); // basic_salary 15,000 -> 7,500 per half

    // Worked Jul 1-10 of a Jul 1-15 period, then archived.
    $this->delete(route('employees.destroy', $employee), ['last_working_day' => '2026-07-10']);

    $run = app(PayrollCalculator::class)->run(makePeriod(7), syncAttendance: false);
    $payslip = $run->payslips()->firstOrFail();

    // 10 of 15 days => 7,500 * 10/15 = 5,000
    expect($run->payslips()->count())->toBe(1)
        ->and((float) $payslip->basic_pay)->toBe(5000.0);
});

test('an employee archived before the period starts is not paid at all', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    // Left in June; the July period must not pay them.
    $this->delete(route('employees.destroy', $employee), ['last_working_day' => '2026-06-20']);

    $run = app(PayrollCalculator::class)->run(makePeriod(7), syncAttendance: false);

    expect($run->payslips()->count())->toBe(0)
        ->and($run->total_employees)->toBe(0);
});

test('an employee who worked the whole period is paid in full even if archived after it', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    $this->delete(route('employees.destroy', $employee), ['last_working_day' => '2026-07-15']);

    $run = app(PayrollCalculator::class)->run(makePeriod(7), syncAttendance: false);

    expect((float) $run->payslips()->firstOrFail()->basic_pay)->toBe(7500.0);
});

test('archiving defaults the last working day to today', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    $this->delete(route('employees.destroy', $employee));

    expect(Employee::withTrashed()->find($employee->id)->last_working_day->toDateString())
        ->toBe(now()->toDateString());
});

test('restoring clears the last working day so full pay resumes', function () {
    makeEmployees(1);
    $employee = Employee::query()->firstOrFail();

    $this->delete(route('employees.destroy', $employee), ['last_working_day' => '2026-07-10']);
    $this->put(route('employees.restore', $employee->id));

    $employee->refresh();
    expect($employee->last_working_day)->toBeNull()
        ->and($employee->trashed())->toBeFalse();

    $run = app(PayrollCalculator::class)->run(makePeriod(7), syncAttendance: false);

    expect((float) $run->payslips()->firstOrFail()->basic_pay)->toBe(7500.0);
});

test('an inactive employee is skipped by payroll the same way an archived one is', function () {
    makeEmployees(2);
    Employee::query()->where('employee_code', 'EMP-0002')->firstOrFail()
        ->update(['status' => 'inactive']);

    $run = app(PayrollCalculator::class)->run(makePeriod(1), syncAttendance: false);

    expect($run->payslips()->count())->toBe(1);
});

test('registering an employee requires a name and rejects a duplicate code', function () {
    makeEmployees(1);

    $this->post(route('employees.store'), [])
        ->assertSessionHasErrors(['first_name', 'last_name', 'basic_salary', 'daily_rate', 'hourly_rate']);

    $this->post(route('employees.store'), [
        'employee_code' => 'EMP-0001',
        'first_name' => 'Andres',
        'last_name' => 'Bonifacio',
        'basic_salary' => 20000,
        'daily_rate' => 800,
        'hourly_rate' => 100,
    ])->assertSessionHasErrors('employee_code');
});
