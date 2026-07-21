<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    /** @var list<int> */
    private const PER_PAGE_OPTIONS = [15, 25, 50, 100];

    public function index(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $archived = $request->boolean('archived');

        $employees = Employee::query()
            ->when($archived, fn ($query) => $query->onlyTrashed())
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('biometric_user_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'position' => $employee->position,
                'department' => $employee->department,
                'basic_salary' => (float) $employee->basic_salary,
                'daily_rate' => (float) $employee->daily_rate,
                'sunday_route_rate' => (float) $employee->sunday_route_rate,
                'hourly_rate' => (float) $employee->hourly_rate,
                'biometric_user_id' => $employee->biometric_user_id,
                'hire_date' => $employee->hire_date?->toDateString(),
                'last_working_day' => $employee->last_working_day?->toDateString(),
                'status' => $employee->status,
                'archived' => $employee->trashed(),
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
                'archived' => $archived,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'nextEmployeeCode' => $this->nextEmployeeCode(),
            'archivedCount' => Employee::onlyTrashed()->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateEmployee($request);

        Employee::query()->create([
            'employee_code' => ($data['employee_code'] ?? null) ?: $this->nextEmployeeCode(),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'position' => $data['position'] ?? null,
            'department' => $data['department'] ?? null,
            'basic_salary' => $data['basic_salary'],
            'daily_rate' => $data['daily_rate'],
            'sunday_route_rate' => $data['sunday_route_rate'] ?? 0,
            'hourly_rate' => $data['hourly_rate'],
            'biometric_user_id' => $data['biometric_user_id'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'status' => $data['status'] ?? 'active',
        ]);

        return $this->toast('success', 'Employee registered successfully.');
    }

    public function update(Request $request, Employee $employee): RedirectResponse
    {
        $data = $this->validateEmployee($request, $employee);

        $employee->update([
            'employee_code' => ($data['employee_code'] ?? null) ?: $employee->employee_code,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'] ?? null,
            'position' => $data['position'] ?? null,
            'department' => $data['department'] ?? null,
            'basic_salary' => $data['basic_salary'],
            'daily_rate' => $data['daily_rate'],
            'sunday_route_rate' => $data['sunday_route_rate'] ?? 0,
            'hourly_rate' => $data['hourly_rate'],
            'biometric_user_id' => $data['biometric_user_id'] ?? null,
            'hire_date' => $data['hire_date'] ?? null,
            'status' => $data['status'] ?? $employee->status,
        ]);

        return $this->toast('success', "{$employee->full_name} was updated.");
    }

    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $data = $request->validate([
            'last_working_day' => ['nullable', 'date'],
        ]);

        // Payroll pays them through this date, then drops them from later runs.
        $employee->update([
            'last_working_day' => $data['last_working_day'] ?? now()->toDateString(),
        ]);
        $employee->delete();

        $lastDay = $employee->last_working_day->toFormattedDateString();

        return $this->toast(
            'warning',
            "{$employee->full_name} was archived. They will still be paid through {$lastDay}."
        );
    }

    public function restore(Employee $employee): RedirectResponse
    {
        $employee->restore();
        $employee->update(['last_working_day' => null]);

        return $this->toast('success', "{$employee->full_name} was restored and is back on the payroll.");
    }

    /**
     * Flash a colored toast for the next Inertia response.
     */
    private function toast(string $type, string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => $type, 'message' => $message]);

        return back();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEmployee(Request $request, ?Employee $employee = null): array
    {
        return $request->validate([
            'employee_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'employee_code')->ignore($employee?->id),
            ],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'sunday_route_rate' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'biometric_user_id' => [
                'nullable', 'string', 'max:50',
                Rule::unique('employees', 'biometric_user_id')->ignore($employee?->id),
            ],
            'hire_date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function nextEmployeeCode(): string
    {
        $highest = Employee::withTrashed()
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('employee_code')
            ->map(fn (string $code) => (int) substr($code, 4))
            ->max();

        return sprintf('EMP-%04d', ($highest ?? 0) + 1);
    }
}
