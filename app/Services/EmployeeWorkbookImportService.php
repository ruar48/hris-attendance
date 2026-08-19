<?php

namespace App\Services;

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\OptionList;
use Carbon\Carbon;
use Database\Seeders\OptionListSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Throwable;

/**
 * Imports the client's "201 Files" workbook — the multi-tab Google Sheet
 * (Application/Onboarding Tracker, Employment Details, Employee Master File,
 * Government Benefits Details, Compensation & Payroll Details, Source Data)
 * — straight into the employees/applicants/option_lists tables.
 *
 * Each tab is matched by (fuzzy) sheet title, and each column within a tab is
 * matched by keyword against its header row rather than by fixed position,
 * since the export's column order/wording can drift between snapshots.
 */
class EmployeeWorkbookImportService
{
    protected const MAX_HEADER_SCAN_ROWS = 12;

    protected const MAX_COLUMNS = 60;

    /**
     * @return array{
     *     sheets_found: string[],
     *     sheets_missing: string[],
     *     employees: array{created: int, updated: int},
     *     applicants: array{created: int, updated: int},
     *     option_lists: array{created: int, updated: int},
     *     warnings: string[],
     * }
     */
    public function import(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());

        $stats = [
            'sheets_found' => [],
            'sheets_missing' => [],
            // An employee touched by several sheets (Master File, then
            // Employment Details, then Compensation, …) must still count
            // once — these track codes rather than per-sheet-row hits, and
            // are collapsed into plain counts before this method returns.
            '_employee_codes' => ['created' => [], 'updated' => []],
            'employees' => ['created' => 0, 'updated' => 0],
            'applicants' => ['created' => 0, 'updated' => 0],
            'option_lists' => ['created' => 0, 'updated' => 0],
            'warnings' => [],
        ];

        $sheets = $this->matchSheets($spreadsheet);

        $labels = [
            'applicants' => 'Application/Onboarding & Requirements Tracker',
            'employment' => 'Employment Details',
            'master' => 'Employee Master File',
            'benefits' => 'Government Benefits Details',
            'compensation' => 'Compensation & Payroll Details',
            'source' => 'Source Data',
        ];

        foreach ($labels as $key => $label) {
            if (isset($sheets[$key])) {
                $stats['sheets_found'][] = $label;
            } else {
                $stats['sheets_missing'][] = $label;
            }
        }

        // Source Data first (it defines the canonical position/department/
        // shift labels the other tabs are normalized against), then Master
        // File before anything else that upserts an Employee, so first/last
        // name is on record before Employment/Compensation/Benefits rows
        // that only ever create a bare-bones fallback record.
        $order = ['source', 'master', 'employment', 'compensation', 'benefits', 'applicants'];

        DB::transaction(function () use ($sheets, $order, &$stats) {
            foreach ($order as $key) {
                if (! isset($sheets[$key])) {
                    continue;
                }

                match ($key) {
                    'source' => $this->importSourceData($sheets[$key], $stats),
                    'master' => $this->importMasterFile($sheets[$key], $stats),
                    'employment' => $this->importEmploymentDetails($sheets[$key], $stats),
                    'compensation' => $this->importCompensation($sheets[$key], $stats),
                    'benefits' => $this->importGovernmentBenefits($sheets[$key], $stats),
                    'applicants' => $this->importApplicants($sheets[$key], $stats),
                    default => null,
                };
            }
        });

        // A code created earlier in this run and touched again by a later
        // sheet stays a "create", not also an "update".
        $stats['employees']['created'] = count($stats['_employee_codes']['created']);
        $stats['employees']['updated'] = count(array_diff_key(
            $stats['_employee_codes']['updated'],
            $stats['_employee_codes']['created']
        ));
        unset($stats['_employee_codes']);

        return $stats;
    }

    /**
     * @return array<string, Worksheet>
     */
    protected function matchSheets(Spreadsheet $spreadsheet): array
    {
        $patterns = [
            'applicants' => ['application', 'onboarding'],
            'employment' => ['employment', 'details'],
            'master' => ['master', 'file'],
            'benefits' => ['government', 'benefit'],
            'compensation' => ['compensation', 'payroll'],
            'source' => ['source', 'data'],
        ];

        $matched = [];

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $title = $this->normalize($sheet->getTitle());

            foreach ($patterns as $key => $keywords) {
                if (isset($matched[$key])) {
                    continue;
                }

                if ($this->containsAll($title, $keywords)) {
                    $matched[$key] = $sheet;
                }
            }
        }

        return $matched;
    }

    // ------------------------------------------------------------------
    // Application / Onboarding & Requirements Tracker
    // ------------------------------------------------------------------

    protected function importApplicants(Worksheet $sheet, array &$stats): void
    {
        $headerRow = $this->findHeaderRow($sheet, ['application', '#']) ?? $this->findHeaderRow($sheet, ['application', 'number']);

        if ($headerRow === null) {
            $stats['warnings'][] = 'Application/Onboarding sheet: could not find the header row (looked for "Application #") — sheet skipped.';

            return;
        }

        $columns = $this->mapColumns($sheet, $headerRow, [
            'application_number' => [['application', '#'], ['application', 'number']],
            'applicant_name' => [['applicant', 'name']],
            'position_applied' => [['position', 'applied']],
            'date_of_application' => [['date', 'application']],
            'application_status' => [['application', 'status']],
            'req_psa_birth_certificate' => [['psa'], ['birth', 'certificate']],
            'req_government_ids' => [['government', 'id'], ['govt', 'id']],
            'req_nbi_clearance' => [['nbi']],
            'req_police_clearance' => [['police']],
            'req_barangay_clearance' => [['barangay'], ['brgy']],
            'req_sss_number' => [['sss']],
            'req_philhealth_number' => [['philhealth'], ['phil', 'health']],
            'req_pagibig_id' => [['pagibig'], ['pag-ibig'], ['hdmf']],
            'req_tin_number' => [['tin']],
            'req_college_diploma_tor' => [['diploma']],
            // The sheet has separate Diploma and Transcript of Records
            // columns; the DB only has one combined field, so both are read
            // and OR'd together below.
            'req_college_diploma_tor_alt' => [['transcript'], ['tor']],
            'req_marriage_certificate' => [['marriage']],
            'req_solo_parent_id' => [['solo', 'parent']],
            'req_bir_form_2305_1905' => [['2305'], ['1905']],
            'req_latest_bir_form_2316' => [['2316']],
            'req_certificate_of_employment' => [['certificate', 'employment'], ['coe']],
            'date_submitted' => [['date', 'submitted']],
            'requirement_status' => [['requirement', 'status']],
            'onboarding_date' => [['onboarding', 'date']],
            'onboarding_training_1' => [['training', '1'], ['orientation', '1']],
            'onboarding_training_2' => [['training', '2'], ['orientation', '2']],
            'onboarding_training_3' => [['training', '3'], ['orientation', '3']],
            'onboarding_training_4' => [['training', '4'], ['orientation', '4']],
        ]);

        if (! isset($columns['application_number'])) {
            $stats['warnings'][] = 'Application/Onboarding sheet: no "Application #" column found — sheet skipped.';

            return;
        }

        $highestRow = $sheet->getHighestDataRow();

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            $applicationNumber = $this->cellText($sheet, $columns['application_number'], $row);

            if ($applicationNumber === null) {
                continue;
            }

            $data = [
                'applicant_name' => $this->cellText($sheet, $columns['applicant_name'] ?? null, $row) ?? $applicationNumber,
                'position_applied' => $this->cellText($sheet, $columns['position_applied'] ?? null, $row),
                'date_of_application' => $this->cellDate($sheet, $columns['date_of_application'] ?? null, $row),
                'application_status' => $this->cellEnum($sheet, $columns['application_status'] ?? null, $row),
                'date_submitted' => $this->cellDate($sheet, $columns['date_submitted'] ?? null, $row),
                'requirement_status' => $this->cellEnum($sheet, $columns['requirement_status'] ?? null, $row),
                'onboarding_date' => $this->cellDate($sheet, $columns['onboarding_date'] ?? null, $row),
                'onboarding_training_1' => $this->cellDate($sheet, $columns['onboarding_training_1'] ?? null, $row),
                'onboarding_training_2' => $this->cellDate($sheet, $columns['onboarding_training_2'] ?? null, $row),
                'onboarding_training_3' => $this->cellDate($sheet, $columns['onboarding_training_3'] ?? null, $row),
                'onboarding_training_4' => $this->cellDate($sheet, $columns['onboarding_training_4'] ?? null, $row),
            ];

            foreach (Applicant::REQUIREMENT_FIELDS as $field) {
                if (isset($columns[$field])) {
                    $data[$field] = $this->cellBool($sheet, $columns[$field], $row);
                }
            }

            if (isset($columns['req_college_diploma_tor_alt'])) {
                $alt = $this->cellBool($sheet, $columns['req_college_diploma_tor_alt'], $row);
                $data['req_college_diploma_tor'] = ($data['req_college_diploma_tor'] ?? false) || ($alt ?? false);
            }

            $data = array_filter($data, fn ($value) => $value !== null);

            $existing = Applicant::query()->where('application_number', $applicationNumber)->first();

            Applicant::query()->updateOrCreate(
                ['application_number' => $applicationNumber],
                $data
            );

            $stats['applicants'][$existing ? 'updated' : 'created']++;
        }
    }

    // ------------------------------------------------------------------
    // Employment Details
    // ------------------------------------------------------------------

    protected function importEmploymentDetails(Worksheet $sheet, array &$stats): void
    {
        $headerRow = $this->findHeaderRow($sheet, ['employee', 'id']);

        if ($headerRow === null) {
            $stats['warnings'][] = 'Employment Details sheet: could not find the "Employee ID" header row — sheet skipped.';

            return;
        }

        $columns = $this->mapColumns($sheet, $headerRow, [
            'employee_code' => [['employee', 'id']],
            'employee_name' => [['employee', 'name']],
            'position' => [['position']],
            'hire_date' => [['date', 'hired'], ['hire', 'date']],
            'regularization_date' => [['regularization']],
            'separation_date' => [['separation']],
            'work_location' => [['work', 'location']],
            'shift_schedule' => [['shift', 'schedule'], ['shift']],
            'time_in_schedule' => [['time', 'in']],
            'time_out_schedule' => [['time', 'out']],
            'immediate_superior' => [['reporting', 'to']],
            'employment_status' => [['employment', 'status']],
        ]);

        if (! isset($columns['employee_code'])) {
            $stats['warnings'][] = 'Employment Details sheet: no "Employee ID" column found — sheet skipped.';

            return;
        }

        $this->eachEmployeeRow($sheet, $headerRow, $columns['employee_code'], function (int $row) use ($sheet, $columns, &$stats) {
            $code = $this->cellText($sheet, $columns['employee_code'], $row);
            $fallbackName = $this->cellText($sheet, $columns['employee_name'] ?? null, $row);

            $attributes = array_filter([
                'position' => $this->optionValue('position', $this->cellText($sheet, $columns['position'] ?? null, $row)),
                'hire_date' => $this->cellDate($sheet, $columns['hire_date'] ?? null, $row),
                'regularization_date' => $this->cellDate($sheet, $columns['regularization_date'] ?? null, $row),
                'separation_date' => $this->cellDate($sheet, $columns['separation_date'] ?? null, $row),
                'work_location' => $this->cellText($sheet, $columns['work_location'] ?? null, $row),
                'shift_schedule' => $this->optionValue('shift_schedule', $this->cellText($sheet, $columns['shift_schedule'] ?? null, $row)),
                'time_in_schedule' => $this->cellTimeText($sheet, $columns['time_in_schedule'] ?? null, $row),
                'time_out_schedule' => $this->cellTimeText($sheet, $columns['time_out_schedule'] ?? null, $row),
                'immediate_superior' => $this->cellText($sheet, $columns['immediate_superior'] ?? null, $row),
                'employment_status' => $this->optionValue('employment_status', $this->cellText($sheet, $columns['employment_status'] ?? null, $row)),
            ], fn ($value) => $value !== null);

            $this->upsertEmployee($code, $attributes, $fallbackName, $stats);
        });
    }

    // ------------------------------------------------------------------
    // Employee Master File
    // ------------------------------------------------------------------

    protected function importMasterFile(Worksheet $sheet, array &$stats): void
    {
        $headerRow = $this->findHeaderRow($sheet, ['employee', 'id']);

        if ($headerRow === null) {
            $stats['warnings'][] = 'Employee Master File sheet: could not find the "Employee ID" header row — sheet skipped.';

            return;
        }

        $columns = $this->mapColumns($sheet, $headerRow, [
            'employee_code' => [['employee', 'id']],
            'last_name' => [['last', 'name']],
            'first_name' => [['first', 'name']],
            'middle_name' => [['middle', 'name']],
            'suffix' => [['suffix']],
            'photo_url' => [['photo']],
            'gender' => [['gender']],
            'marital_status' => [['civil', 'status'], ['marital']],
            'date_of_birth' => [['date', 'of', 'birth']],
            'place_of_birth' => [['place', 'of', 'birth']],
            'nationality' => [['nationality']],
            'religion' => [['religion']],
            'contact_number' => [['contact', 'number']],
            'personal_email' => [['personal', 'email']],
            'company_email' => [['company', 'email']],
            'current_address' => [['current', 'address']],
            'permanent_address' => [['permanent', 'address']],
            'emergency_contact_name' => [['emergency', 'contact', 'name']],
            'emergency_contact_number' => [['emergency', 'contact', 'number']],
            'emergency_contact_relationship' => [['relationship']],
            'status' => [['status']],
        ]);

        if (! isset($columns['employee_code'])) {
            $stats['warnings'][] = 'Employee Master File sheet: no "Employee ID" column found — sheet skipped.';

            return;
        }

        $this->eachEmployeeRow($sheet, $headerRow, $columns['employee_code'], function (int $row) use ($sheet, $columns, &$stats) {
            $code = $this->cellText($sheet, $columns['employee_code'], $row);
            $lastName = $this->cellText($sheet, $columns['last_name'] ?? null, $row);
            $firstName = $this->cellText($sheet, $columns['first_name'] ?? null, $row);
            $fallbackName = $lastName && $firstName ? "{$lastName}, {$firstName}" : null;

            $attributes = array_filter([
                'last_name' => $lastName,
                'first_name' => $firstName,
                'middle_name' => $this->cellText($sheet, $columns['middle_name'] ?? null, $row),
                'suffix' => $this->cellText($sheet, $columns['suffix'] ?? null, $row),
                'photo_url' => $this->cellText($sheet, $columns['photo_url'] ?? null, $row),
                'gender' => $this->slug($this->cellText($sheet, $columns['gender'] ?? null, $row)),
                'marital_status' => $this->slug($this->cellText($sheet, $columns['marital_status'] ?? null, $row)),
                'date_of_birth' => $this->cellDate($sheet, $columns['date_of_birth'] ?? null, $row),
                'place_of_birth' => $this->cellText($sheet, $columns['place_of_birth'] ?? null, $row),
                'nationality' => $this->cellText($sheet, $columns['nationality'] ?? null, $row),
                'religion' => $this->cellText($sheet, $columns['religion'] ?? null, $row),
                'contact_number' => $this->cellText($sheet, $columns['contact_number'] ?? null, $row),
                'personal_email' => $this->cellText($sheet, $columns['personal_email'] ?? null, $row),
                'company_email' => $this->cellText($sheet, $columns['company_email'] ?? null, $row),
                'current_address' => $this->cellText($sheet, $columns['current_address'] ?? null, $row),
                'permanent_address' => $this->cellText($sheet, $columns['permanent_address'] ?? null, $row),
                'emergency_contact_name' => $this->cellText($sheet, $columns['emergency_contact_name'] ?? null, $row),
                'emergency_contact_number' => $this->cellText($sheet, $columns['emergency_contact_number'] ?? null, $row),
                'emergency_contact_relationship' => $this->cellText($sheet, $columns['emergency_contact_relationship'] ?? null, $row),
                'status' => $this->optionValue('employee_status', $this->cellText($sheet, $columns['status'] ?? null, $row)),
            ], fn ($value) => $value !== null);

            $this->upsertEmployee($code, $attributes, $fallbackName, $stats);
        });
    }

    // ------------------------------------------------------------------
    // Government Benefits Details
    // ------------------------------------------------------------------

    protected function importGovernmentBenefits(Worksheet $sheet, array &$stats): void
    {
        $headerRow = $this->findHeaderRow($sheet, ['employee', 'id']);

        if ($headerRow === null) {
            $stats['warnings'][] = 'Government Benefits Details sheet: could not find the "Employee ID" header row — sheet skipped.';

            return;
        }

        $columns = $this->mapColumns($sheet, $headerRow, [
            'employee_code' => [['employee', 'id']],
            'employee_name' => [['employee', 'name']],
            'sss_number' => [['sss']],
            'philhealth_number' => [['philhealth'], ['phil', 'health']],
            'pagibig_number' => [['pag-ibig'], ['pagibig'], ['hdmf']],
            'tin_number' => [['tin']],
        ]);

        if (! isset($columns['employee_code'])) {
            $stats['warnings'][] = 'Government Benefits Details sheet: no "Employee ID" column found — sheet skipped.';

            return;
        }

        // The trailing remarks column ("INCOMPLETE REQ.", "AWOL", "ENDO", …)
        // has no header of its own — it's whatever sits immediately after
        // the last matched benefits column.
        $remarksColumn = max([0, ...array_values($columns)]) + 1;

        $this->eachEmployeeRow($sheet, $headerRow, $columns['employee_code'], function (int $row) use ($sheet, $columns, $remarksColumn, &$stats) {
            $code = $this->cellText($sheet, $columns['employee_code'], $row);
            $fallbackName = $this->cellText($sheet, $columns['employee_name'] ?? null, $row);

            $attributes = array_filter([
                'sss_number' => $this->cellText($sheet, $columns['sss_number'] ?? null, $row),
                'philhealth_number' => $this->cellText($sheet, $columns['philhealth_number'] ?? null, $row),
                'pagibig_number' => $this->cellText($sheet, $columns['pagibig_number'] ?? null, $row),
                'tin_number' => $this->cellText($sheet, $columns['tin_number'] ?? null, $row),
                'government_benefits_remarks' => $remarksColumn > 0 ? $this->cellText($sheet, $remarksColumn, $row) : null,
            ], fn ($value) => $value !== null);

            $this->upsertEmployee($code, $attributes, $fallbackName, $stats);
        });
    }

    // ------------------------------------------------------------------
    // Compensation & Payroll Details
    // ------------------------------------------------------------------

    protected function importCompensation(Worksheet $sheet, array &$stats): void
    {
        $headerRow = $this->findHeaderRow($sheet, ['employee', 'id']);

        if ($headerRow === null) {
            $stats['warnings'][] = 'Compensation & Payroll Details sheet: could not find the "Employee ID" header row — sheet skipped.';

            return;
        }

        $columns = $this->mapColumns($sheet, $headerRow, [
            'employee_code' => [['employee', 'id']],
            'employee_name' => [['employee', 'name']],
            'position' => [['position']],
            'salary_type' => [['salary', 'type']],
            'basic_salary' => [['basic', 'salary']],
            'tax_status' => [['tax', 'status']],
            'rice_allowance' => [['rice', 'allowance']],
            'transpo_allowance' => [['transpo', 'allowance']],
            'de_minimis_allowance' => [['de', 'minimis']],
            'meal_allowance' => [['meal', 'allowance']],
            'bank_name' => [['bank', 'name']],
            'bank_account_number' => [['account', 'number']],
            'bank_account_status' => [['account', 'status']],
        ]);

        if (! isset($columns['employee_code'])) {
            $stats['warnings'][] = 'Compensation & Payroll Details sheet: no "Employee ID" column found — sheet skipped.';

            return;
        }

        $this->eachEmployeeRow($sheet, $headerRow, $columns['employee_code'], function (int $row) use ($sheet, $columns, &$stats) {
            $code = $this->cellText($sheet, $columns['employee_code'], $row);
            $fallbackName = $this->cellText($sheet, $columns['employee_name'] ?? null, $row);

            $attributes = array_filter([
                'position' => $this->optionValue('position', $this->cellText($sheet, $columns['position'] ?? null, $row)),
                'salary_type' => $this->slug($this->cellText($sheet, $columns['salary_type'] ?? null, $row)),
                'basic_salary' => $this->cellNumber($sheet, $columns['basic_salary'] ?? null, $row),
                'tax_status' => $this->cellText($sheet, $columns['tax_status'] ?? null, $row),
                'rice_allowance' => $this->cellNumber($sheet, $columns['rice_allowance'] ?? null, $row),
                'transpo_allowance' => $this->cellNumber($sheet, $columns['transpo_allowance'] ?? null, $row),
                'de_minimis_allowance' => $this->cellNumber($sheet, $columns['de_minimis_allowance'] ?? null, $row),
                'meal_allowance' => $this->cellNumber($sheet, $columns['meal_allowance'] ?? null, $row),
                'bank_name' => $this->cellText($sheet, $columns['bank_name'] ?? null, $row),
                'bank_account_number' => $this->cellText($sheet, $columns['bank_account_number'] ?? null, $row),
                'bank_account_status' => $this->cellText($sheet, $columns['bank_account_status'] ?? null, $row),
            ], fn ($value) => $value !== null);

            $this->upsertEmployee($code, $attributes, $fallbackName, $stats);
        });
    }

    // ------------------------------------------------------------------
    // Source Data
    // ------------------------------------------------------------------

    protected function importSourceData(Worksheet $sheet, array &$stats): void
    {
        $groups = [
            'employee_status' => ['employee', 'statuses'],
            'employment_status' => ['employment', 'statuses'],
            'position' => ['company', 'positions'],
            'department' => ['departments'],
            'job_level' => ['job', 'levels'],
            'shift_schedule' => ['shift', 'work', 'schedules'],
        ];

        $highestRow = min(500, $sheet->getHighestDataRow());
        $highestCol = min(self::MAX_COLUMNS, $this->columnCount($sheet));

        foreach ($groups as $category => $keywords) {
            $headerCol = null;
            $headerRow = null;

            for ($row = 1; $row <= self::MAX_HEADER_SCAN_ROWS && $headerRow === null; $row++) {
                for ($col = 1; $col <= $highestCol; $col++) {
                    $text = $this->normalize($this->rawCellText($sheet, $col, $row));

                    if ($this->containsAll($text, $keywords)) {
                        $headerCol = $col;
                        $headerRow = $row;

                        break;
                    }
                }
            }

            if ($headerCol === null) {
                continue;
            }

            $timeInCol = $category === 'shift_schedule' ? $this->findColumnNear($sheet, $headerRow, $headerCol, ['time', 'in']) : null;
            $timeOutCol = $category === 'shift_schedule' ? $this->findColumnNear($sheet, $headerRow, $headerCol, ['time', 'out']) : null;

            $sortOrder = 0;

            for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
                $label = $this->cellText($sheet, $headerCol, $row);

                if ($label === null) {
                    continue;
                }

                $value = $this->optionValue($category, $label) ?? $label;

                $existing = OptionList::query()->where('category', $category)->where('value', $value)->first();

                OptionList::query()->updateOrCreate(
                    ['category' => $category, 'value' => $value],
                    array_filter([
                        'label' => $label,
                        'sort_order' => $sortOrder,
                        'time_in' => $timeInCol ? $this->cellText($sheet, $timeInCol, $row) : null,
                        'time_out' => $timeOutCol ? $this->cellText($sheet, $timeOutCol, $row) : null,
                    ], fn ($v) => $v !== null)
                );

                $stats['option_lists'][$existing ? 'updated' : 'created']++;
                $sortOrder++;
            }
        }
    }

    // ------------------------------------------------------------------
    // Shared helpers
    // ------------------------------------------------------------------

    /**
     * Upsert an Employee by employee_code, filling required NOT NULL name
     * columns from a same-row fallback ("Last, First" or a bare name) only
     * when creating a brand-new record — later sheets never overwrite a name
     * already on file.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function upsertEmployee(?string $code, array $attributes, ?string $fallbackName, array &$stats): void
    {
        if ($code === null || trim($code) === '') {
            return;
        }

        $code = trim($code);
        $employee = Employee::withTrashed()->where('employee_code', $code)->first();

        if ($employee) {
            if (! empty($attributes)) {
                $employee->update($attributes);
            }
            $stats['_employee_codes']['updated'][$code] = true;

            return;
        }

        [$lastName, $firstName] = $this->splitName($fallbackName);

        Employee::query()->create([
            'employee_code' => $code,
            'first_name' => $attributes['first_name'] ?? $firstName ?? $code,
            'last_name' => $attributes['last_name'] ?? $lastName ?? $code,
            'status' => $attributes['status'] ?? 'active',
            ...$attributes,
        ]);

        $stats['_employee_codes']['created'][$code] = true;
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    protected function splitName(?string $name): array
    {
        if ($name === null || trim($name) === '') {
            return [null, null];
        }

        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));

            return [$last !== '' ? $last : null, $first !== '' ? $first : null];
        }

        $parts = preg_split('/\s+/', trim($name)) ?: [];

        if (count($parts) < 2) {
            return [$name, $name];
        }

        $first = array_shift($parts);

        return [implode(' ', $parts), $first];
    }

    /**
     * Walk every row below a header that has a non-empty value in the given
     * "identity" column, stopping once two rows in a row are empty (a
     * trailing blank stretch, not just a single skipped row).
     */
    protected function eachEmployeeRow(Worksheet $sheet, int $headerRow, int $identityColumn, callable $callback): void
    {
        $highestRow = $sheet->getHighestDataRow();
        $consecutiveBlanks = 0;

        for ($row = $headerRow + 1; $row <= $highestRow; $row++) {
            if ($this->cellText($sheet, $identityColumn, $row) === null) {
                $consecutiveBlanks++;

                if ($consecutiveBlanks >= 2) {
                    break;
                }

                continue;
            }

            $consecutiveBlanks = 0;
            $callback($row);
        }
    }

    /**
     * Find the header row (within the first MAX_HEADER_SCAN_ROWS rows) whose
     * text contains all of the given keywords in some cell.
     */
    protected function findHeaderRow(Worksheet $sheet, array $keywords): ?int
    {
        $highestCol = min(self::MAX_COLUMNS, $this->columnCount($sheet));

        for ($row = 1; $row <= self::MAX_HEADER_SCAN_ROWS; $row++) {
            for ($col = 1; $col <= $highestCol; $col++) {
                $text = $this->normalize($this->rawCellText($sheet, $col, $row));

                if ($this->containsAll($text, $keywords)) {
                    return $row;
                }
            }
        }

        return null;
    }

    /**
     * How many rows above the detected header row to also fold into each
     * column's header text. A banner row that spans several columns (e.g. a
     * "REQUIREMENTS CHECKLISTS" title) sits above the row with the actual
     * per-column labels, and — as in the client's tracker — a sub-header
     * merged across two rows (row 4 label, row 4:5 merge) leaves the
     * "primary" header row blank for those columns entirely. Combining text
     * from the row(s) just above catches both cases.
     */
    protected const HEADER_LOOKBACK_ROWS = 2;

    /**
     * Map field => column index by scanning a header row (and the couple of
     * rows above it, to catch merged/banner sub-headers) for the first
     * unused column whose combined text matches one of the field's keyword
     * clauses (each clause is an AND of substrings; clauses are tried in
     * order).
     *
     * @param  array<string, array<int, array<int, string>>>  $fields
     * @return array<string, int>
     */
    protected function mapColumns(Worksheet $sheet, int $headerRow, array $fields): array
    {
        $highestCol = min(self::MAX_COLUMNS, $this->columnCount($sheet));
        $topRow = max(1, $headerRow - self::HEADER_LOOKBACK_ROWS);
        $headerText = [];

        for ($col = 1; $col <= $highestCol; $col++) {
            $combined = [];

            for ($row = $topRow; $row <= $headerRow; $row++) {
                $text = $this->rawCellText($sheet, $col, $row);

                if ($text !== '') {
                    $combined[] = $text;
                }
            }

            $headerText[$col] = $this->normalize(implode(' ', $combined));
        }

        $used = [];
        $result = [];

        foreach ($fields as $field => $clauses) {
            foreach ($clauses as $keywords) {
                $found = null;

                foreach ($headerText as $col => $text) {
                    if (isset($used[$col]) || $text === '') {
                        continue;
                    }

                    if ($this->containsAll($text, $keywords)) {
                        $found = $col;

                        break;
                    }
                }

                if ($found !== null) {
                    $result[$field] = $found;
                    $used[$found] = true;

                    break;
                }
            }
        }

        return $result;
    }

    protected function findColumnNear(Worksheet $sheet, int $headerRow, int $anchorCol, array $keywords): ?int
    {
        $highestCol = min(self::MAX_COLUMNS, $this->columnCount($sheet));

        for ($col = $anchorCol; $col <= $highestCol; $col++) {
            $text = $this->normalize($this->rawCellText($sheet, $col, $headerRow));

            if ($this->containsAll($text, $keywords)) {
                return $col;
            }
        }

        return null;
    }

    protected function columnCount(Worksheet $sheet): int
    {
        return \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
    }

    protected function normalize(?string $text): string
    {
        $text = strtolower((string) $text);
        $text = str_replace(["\r\n", "\n", "\r"], ' ', $text);

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }

    protected function containsAll(string $haystack, array $keywords): bool
    {
        foreach ($keywords as $keyword) {
            if (! str_contains($haystack, strtolower($keyword))) {
                return false;
            }
        }

        return true;
    }

    protected function rawCellText(Worksheet $sheet, int $col, int $row): string
    {
        return (string) $this->resolvedCellValue($sheet->getCell([$col, $row]));
    }

    /**
     * A cell's evaluated value — e.g. `=IF('Employee Master File'!B163="","",…)`
     * resolves to the pulled-through text rather than the formula source.
     * Falls back to the raw stored value when the formula engine can't
     * resolve it (unsupported function, broken cross-sheet reference, …).
     */
    protected function resolvedCellValue(\PhpOffice\PhpSpreadsheet\Cell\Cell $cell): mixed
    {
        if (! $cell->isFormula()) {
            return $cell->getValue();
        }

        try {
            return $cell->getCalculatedValue();
        } catch (Throwable) {
            return $cell->getValue();
        }
    }

    protected function cellText(Worksheet $sheet, ?int $col, int $row): ?string
    {
        if ($col === null) {
            return null;
        }

        $value = trim($this->rawCellText($sheet, $col, $row));

        return $value === '' ? null : $value;
    }

    /**
     * Slugified enum text, e.g. "In Active" -> "in_active", "Semi-Monthly" -> "semi_monthly".
     */
    protected function cellEnum(Worksheet $sheet, ?int $col, int $row): ?string
    {
        return $this->slug($this->cellText($sheet, $col, $row));
    }

    protected function slug(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $slug = strtolower(trim($value));
        $slug = preg_replace('/[\s\-]+/', '_', $slug) ?? $slug;

        return $slug === '' ? null : $slug;
    }

    /**
     * Map a raw label to the option_lists value already seeded for this
     * category (case-insensitive match against the label), so imported rows
     * line up with the dropdowns already in use instead of minting near
     * duplicates. Falls back to the raw trimmed text when nothing matches.
     */
    protected function optionValue(string $category, ?string $label): ?string
    {
        if ($label === null) {
            return null;
        }

        $label = trim($label);

        if ($label === '') {
            return null;
        }

        $seeded = OptionListSeeder::rows()[$category] ?? [];

        foreach ($seeded as $item) {
            if (strcasecmp($item['label'], $label) === 0 || strcasecmp((string) $item['value'], $label) === 0) {
                return $item['value'];
            }
        }

        return $category === 'position' || $category === 'department' ? $label : $this->slug($label);
    }

    protected function cellBool(Worksheet $sheet, ?int $col, int $row): ?bool
    {
        if ($col === null) {
            return null;
        }

        $cell = $sheet->getCell([$col, $row]);
        $value = $this->resolvedCellValue($cell);

        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $text = strtolower(trim((string) $value));

        return in_array($text, ['true', '1', 'yes', 'y', 'checked'], true);
    }

    protected function cellNumber(Worksheet $sheet, ?int $col, int $row): ?float
    {
        $text = $this->cellText($sheet, $col, $row);

        if ($text === null) {
            return null;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', $text) ?? '';

        return $clean === '' || ! is_numeric($clean) ? null : (float) $clean;
    }

    /**
     * A date cell as "H:i" text (for time-in/time-out schedule columns,
     * which are free-text like "6:00 AM" rather than real dates).
     */
    protected function cellTimeText(Worksheet $sheet, ?int $col, int $row): ?string
    {
        if ($col === null) {
            return null;
        }

        $cell = $sheet->getCell([$col, $row]);
        $raw = $this->resolvedCellValue($cell);

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw) && ExcelDate::isDateTime($cell)) {
            try {
                return ExcelDate::excelToDateTimeObject($raw)->format('g:i A');
            } catch (Throwable) {
                // fall through to raw text below
            }
        }

        return trim((string) $raw) ?: null;
    }

    protected function cellDate(Worksheet $sheet, ?int $col, int $row): ?string
    {
        if ($col === null) {
            return null;
        }

        $cell = $sheet->getCell([$col, $row]);
        $raw = $this->resolvedCellValue($cell);

        if ($raw === null || $raw === '') {
            return null;
        }

        if (is_numeric($raw)) {
            try {
                return ExcelDate::excelToDateTimeObject($raw)->format('Y-m-d');
            } catch (Throwable) {
                // fall through to string parsing below
            }
        }

        $text = trim((string) $raw);

        if ($text === '' || strtolower($text) === '--') {
            return null;
        }

        try {
            return Carbon::parse($text)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
