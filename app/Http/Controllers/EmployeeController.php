<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
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
        ]);
    }
}
