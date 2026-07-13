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
    public function index(Request $request): Response
    {
        $employees = Employee::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('biometric_user_id', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'full_name' => $employee->full_name,
                'position' => $employee->position,
                'department' => $employee->department,
                'basic_salary' => (float) $employee->basic_salary,
                'biometric_user_id' => $employee->biometric_user_id,
                'status' => $employee->status,
            ]);

        return Inertia::render('employees/index', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
            ],
            'nextEmployeeCode' => $this->nextEmployeeCode(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_code' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'employee_code')],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:150'],
            'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'daily_rate' => ['required', 'numeric', 'min:0'],
            'sunday_route_rate' => ['nullable', 'numeric', 'min:0'],
            'hourly_rate' => ['required', 'numeric', 'min:0'],
            'biometric_user_id' => ['nullable', 'string', 'max:50', Rule::unique('employees', 'biometric_user_id')],
            'hire_date' => ['nullable', 'date'],
        ]);

        Employee::query()->create([
            'employee_code' => $data['employee_code'] ?: $this->nextEmployeeCode(),
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
            'status' => 'active',
        ]);

        return back()->with('success', 'Employee registered successfully.');
    }

    private function nextEmployeeCode(): string
    {
        $highest = Employee::query()
            ->where('employee_code', 'like', 'EMP-%')
            ->pluck('employee_code')
            ->map(fn (string $code) => (int) substr($code, 4))
            ->max();

        return sprintf('EMP-%04d', ($highest ?? 0) + 1);
    }
}
