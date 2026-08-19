<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\OptionList;
use App\Services\EmployeeWorkbookImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

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
                'employment_status' => $employee->employment_status,
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
            'importResult' => session('importResult'),
        ]);
    }

    public function import(Request $request, EmployeeWorkbookImportService $importer): RedirectResponse
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:20480'],
        ]);

        try {
            $result = $importer->import($data['file']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        $summary = "{$result['employees']['created']} employees added, {$result['employees']['updated']} updated"
            ." — {$result['applicants']['created']} applicants added, {$result['applicants']['updated']} updated"
            ." — {$result['option_lists']['created']} option list entries added, {$result['option_lists']['updated']} updated.";

        Inertia::flash('toast', [
            'type' => empty($result['sheets_found']) ? 'info' : 'success',
            'message' => $summary,
        ]);

        return redirect()->route('employees.index')->with('importResult', $result);
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
            ...Arr::only($data, Employee::PROFILE_FIELDS),
            ...Arr::only($data, Employee::MASTER_FILE_FIELDS),
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
            ...Arr::only($data, Employee::PROFILE_FIELDS),
            ...Arr::only($data, Employee::MASTER_FILE_FIELDS),
        ]);

        return $this->toast('success', "{$employee->full_name} was updated.");
    }

    public function profile(Request $request): Response
    {
        $search = $request->string('search')->toString();
        $employee = null;

        if ($search !== '') {
            $employee = Employee::query()
                ->where('employee_code', $search)
                ->orWhere(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                })
                ->first();
        }

        return Inertia::render('employees/profile', [
            'employee' => $employee ? $this->profileArray($employee) : null,
            'search' => $search,
            'reportingToOptions' => Employee::query()
                ->orderBy('last_name')
                ->get()
                ->map(fn (Employee $e) => "{$e->last_name}, {$e->first_name}")
                ->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileArray(Employee $employee): array
    {
        return [
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
            'gender' => $employee->gender,
            'marital_status' => $employee->marital_status,
            'date_of_birth' => $employee->date_of_birth?->toDateString(),
            'place_of_birth' => $employee->place_of_birth,
            'nationality' => $employee->nationality,
            'religion' => $employee->religion,
            'contact_number' => $employee->contact_number,
            'address' => $employee->address,
            'emergency_contact_name' => $employee->emergency_contact_name,
            'emergency_contact_number' => $employee->emergency_contact_number,
            'emergency_contact_address' => $employee->emergency_contact_address,
            'emergency_contact_relationship' => $employee->emergency_contact_relationship,
            'immediate_superior' => $employee->immediate_superior,
            'regularization_date' => $employee->regularization_date?->toDateString(),
            'separation_date' => $employee->separation_date?->toDateString(),
            'division' => $employee->division,
            'job_level' => $employee->job_level,
            'employment_status' => $employee->employment_status,
            'work_location' => $employee->work_location,
            'shift_schedule' => $employee->shift_schedule,
            'time_in_schedule' => $employee->time_in_schedule,
            'time_out_schedule' => $employee->time_out_schedule,
            'salary_type' => $employee->salary_type,
            'tax_status' => $employee->tax_status,
            'rice_allowance' => $employee->rice_allowance === null ? null : (float) $employee->rice_allowance,
            'transpo_allowance' => $employee->transpo_allowance === null ? null : (float) $employee->transpo_allowance,
            'meal_allowance' => $employee->meal_allowance === null ? null : (float) $employee->meal_allowance,
            'de_minimis_allowance' => $employee->de_minimis_allowance === null ? null : (float) $employee->de_minimis_allowance,
            'bank_name' => $employee->bank_name,
            'bank_account_number' => $employee->bank_account_number,
            'bank_account_status' => $employee->bank_account_status,
            'sss_number' => $employee->sss_number,
            'philhealth_number' => $employee->philhealth_number,
            'pagibig_number' => $employee->pagibig_number,
            'tin_number' => $employee->tin_number,
        ];
    }

    public function employmentDetails(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $employees = Employee::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
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
                'basic_salary' => (float) $employee->basic_salary,
                'daily_rate' => (float) $employee->daily_rate,
                'sunday_route_rate' => (float) $employee->sunday_route_rate,
                'hourly_rate' => (float) $employee->hourly_rate,
                'biometric_user_id' => $employee->biometric_user_id,
                'status' => $employee->status,
                'position' => $employee->position,
                'hire_date' => $employee->hire_date?->toDateString(),
                'regularization_date' => $employee->regularization_date?->toDateString(),
                'separation_date' => $employee->separation_date?->toDateString(),
                'years_of_service' => $this->yearsOfService($employee),
                'work_location' => $employee->work_location,
                'shift_schedule' => $employee->shift_schedule,
                'time_in_schedule' => $employee->time_in_schedule,
                'time_out_schedule' => $employee->time_out_schedule,
                'immediate_superior' => $employee->immediate_superior,
                'employment_status' => $employee->employment_status,
            ]);

        return Inertia::render('employees/employment-details', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'reportingToOptions' => Employee::query()
                ->orderBy('last_name')
                ->get()
                ->map(fn (Employee $employee) => "{$employee->last_name}, {$employee->first_name}")
                ->all(),
        ]);
    }

    private function yearsOfService(Employee $employee): ?string
    {
        if (! $employee->hire_date) {
            return null;
        }

        $end = $employee->separation_date ?? now();
        $diff = $employee->hire_date->diff($end);

        return "{$diff->y} Years {$diff->m} Months {$diff->d} Days";
    }

    public function masterFile(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $employees = Employee::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (Employee $employee) => [
                'id' => $employee->id,
                'employee_code' => $employee->employee_code,
                'biometric_user_id' => $employee->biometric_user_id,
                'last_name' => $employee->last_name,
                'first_name' => $employee->first_name,
                'middle_name' => $employee->middle_name,
                'suffix' => $employee->suffix,
                'photo_url' => $employee->photo_url,
                'gender' => $employee->gender,
                'marital_status' => $employee->marital_status,
                'date_of_birth' => $employee->date_of_birth?->toDateString(),
                'place_of_birth' => $employee->place_of_birth,
                'nationality' => $employee->nationality,
                'religion' => $employee->religion,
                'contact_number' => $employee->contact_number,
                'personal_email' => $employee->personal_email,
                'company_email' => $employee->company_email,
                'current_address' => $employee->current_address,
                'permanent_address' => $employee->permanent_address,
                'emergency_contact_name' => $employee->emergency_contact_name,
                'emergency_contact_number' => $employee->emergency_contact_number,
                'emergency_contact_relationship' => $employee->emergency_contact_relationship,
                'status' => $employee->status,
                'employment_status' => $employee->employment_status,
                // Required by validateEmployee() when this row is PUT back with an inline edit.
                'basic_salary' => (float) $employee->basic_salary,
                'daily_rate' => (float) $employee->daily_rate,
                'hourly_rate' => (float) $employee->hourly_rate,
            ]);

        return Inertia::render('employees/master-file', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function governmentBenefits(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $employees = Employee::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
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
                'position' => $employee->position,
                'sss_number' => $employee->sss_number,
                'philhealth_number' => $employee->philhealth_number,
                'pagibig_number' => $employee->pagibig_number,
                'tin_number' => $employee->tin_number,
                'government_benefits_remarks' => $employee->government_benefits_remarks,
                'employment_status' => $employee->employment_status,
                // Required by validateEmployee() when this row is PUT back with an inline edit.
                'basic_salary' => (float) $employee->basic_salary,
                'daily_rate' => (float) $employee->daily_rate,
                'hourly_rate' => (float) $employee->hourly_rate,
            ]);

        return Inertia::render('employees/government-benefits', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    public function compensationPayroll(Request $request): Response
    {
        $perPage = (int) $request->integer('per_page', 15);
        if (! in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = 15;
        }

        $employees = Employee::query()
            ->when($request->string('search')->toString(), function ($query, string $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%");
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
                'position' => $employee->position,
                'salary_type' => $employee->salary_type,
                'basic_salary' => (float) $employee->basic_salary,
                'tax_status' => $employee->tax_status,
                'rice_allowance' => $employee->rice_allowance === null ? null : (float) $employee->rice_allowance,
                'transpo_allowance' => $employee->transpo_allowance === null ? null : (float) $employee->transpo_allowance,
                'de_minimis_allowance' => $employee->de_minimis_allowance === null ? null : (float) $employee->de_minimis_allowance,
                'meal_allowance' => $employee->meal_allowance === null ? null : (float) $employee->meal_allowance,
                'bank_name' => $employee->bank_name,
                'bank_account_number' => $employee->bank_account_number,
                'bank_account_status' => $employee->bank_account_status,
                'employment_status' => $employee->employment_status,
                // Required by validateEmployee() when this row is PUT back with an inline edit.
                'daily_rate' => (float) $employee->daily_rate,
                'hourly_rate' => (float) $employee->hourly_rate,
            ]);

        return Inertia::render('employees/compensation-payroll', [
            'employees' => $employees,
            'filters' => [
                'search' => $request->string('search')->toString(),
                'per_page' => $perPage,
            ],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
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
            'status' => ['nullable', Rule::in(OptionList::values('employee_status'))],

            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'marital_status' => ['nullable', Rule::in(['single', 'married', 'widowed', 'separated'])],
            'date_of_birth' => ['nullable', 'date'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],

            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_address' => ['nullable', 'string', 'max:500'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],

            'immediate_superior' => ['nullable', 'string', 'max:150'],
            'regularization_date' => ['nullable', 'date'],
            'separation_date' => ['nullable', 'date'],
            'division' => ['nullable', 'string', 'max:100'],
            'job_level' => ['nullable', Rule::in(OptionList::values('job_level'))],
            'employment_status' => ['nullable', Rule::in(OptionList::values('employment_status'))],
            'work_location' => ['nullable', 'string', 'max:150'],
            'shift_schedule' => ['nullable', Rule::in(OptionList::values('shift_schedule'))],
            'time_in_schedule' => ['nullable', 'string', 'max:20'],
            'time_out_schedule' => ['nullable', 'string', 'max:20'],

            'salary_type' => ['nullable', Rule::in(['monthly', 'daily', 'hourly', 'semi_monthly', 'weekly'])],
            'tax_status' => ['nullable', 'string', 'max:20'],
            'rice_allowance' => ['nullable', 'numeric', 'min:0'],
            'transpo_allowance' => ['nullable', 'numeric', 'min:0'],
            'meal_allowance' => ['nullable', 'numeric', 'min:0'],
            'de_minimis_allowance' => ['nullable', 'numeric', 'min:0'],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_account_status' => ['nullable', 'string', 'max:50'],

            'sss_number' => ['nullable', 'string', 'max:30'],
            'philhealth_number' => ['nullable', 'string', 'max:30'],
            'pagibig_number' => ['nullable', 'string', 'max:30'],
            'tin_number' => ['nullable', 'string', 'max:30'],
            'government_benefits_remarks' => ['nullable', 'string', 'max:255'],

            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:20'],
            'photo_url' => ['nullable', 'string', 'max:500'],
            'personal_email' => ['nullable', 'email', 'max:150'],
            'company_email' => ['nullable', 'email', 'max:150'],
            'current_address' => ['nullable', 'string', 'max:500'],
            'permanent_address' => ['nullable', 'string', 'max:500'],
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
