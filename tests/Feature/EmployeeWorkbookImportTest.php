<?php

use App\Models\Applicant;
use App\Models\Employee;
use App\Models\OptionList;
use App\Services\EmployeeWorkbookImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

function writeRow(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet, int $row, array $values): void
{
    foreach ($values as $col => $value) {
        $sheet->getCell([$col + 1, $row])->setValue($value);
    }
}

function buildWorkbookFile(): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    $master = $spreadsheet->createSheet();
    $master->setTitle('Employee Master File');
    writeRow($master, 5, [
        'Employee ID', 'Last Name', 'First Name', 'Middle Name', 'Gender', 'Civil Status',
        'Date of Birth', 'Contact Number', 'Status',
    ]);
    writeRow($master, 6, [
        '25150', 'Marin', 'Mark Anthony', 'P.', 'Male', 'Married', '1992-05-05', '0995-012-4494', 'Active',
    ]);

    $employment = $spreadsheet->createSheet();
    $employment->setTitle('Employment Details');
    writeRow($employment, 5, [
        'Employee ID', 'Employee Name', 'Position', 'Date Hired', 'Shift Schedule', 'Reporting To', 'Employment Status',
    ]);
    writeRow($employment, 6, [
        '25150', 'Marin, Mark Anthony', 'Shift supervisor', '2024-01-01', 'Flexible', 'Paje, Abigail', 'Regular',
    ]);

    $compensation = $spreadsheet->createSheet();
    $compensation->setTitle('Compensation & Payroll Details');
    writeRow($compensation, 5, [
        'Employee ID', 'Employee Name', 'Position', 'Salary Type', 'Basic Salary', 'Tax Status', 'Rice Allowance',
    ]);
    writeRow($compensation, 6, [
        '25150', 'Marin, Mark Anthony', 'Shift supervisor', 'Monthly', '25000', 'S', '1500',
    ]);
    // A brand new employee not present on the Master File sheet at all.
    writeRow($compensation, 7, [
        '25999', 'New, Hire', 'Checker', 'Semi-Monthly', '18000', 'S1', '',
    ]);

    $benefits = $spreadsheet->createSheet();
    $benefits->setTitle('Government Benefits Details');
    writeRow($benefits, 5, [
        'Employee ID', 'Employee Name', 'Position', 'SSS Number', 'PhilHealth Number', 'Pag-IBIG Number', 'TIN', 'Remarks',
    ]);
    writeRow($benefits, 6, [
        '25150', 'Marin, Mark Anthony', 'Shift supervisor', '04-3293740-8', '09-2011-54492-6', '1212-1741-5824', '774-647-647', '',
    ]);

    $applicants = $spreadsheet->createSheet();
    $applicants->setTitle('Application Onboarding Tracker');
    writeRow($applicants, 5, [
        'Application #', 'Applicant Name', 'Position Applied', 'Date of Application', 'Application Status',
        'PSA/Birth Certificate', 'Government IDs (2) Copy', 'NBI Clearance', 'Police Clearance', 'Barangay Clearance',
        'SSS Number', 'PhilHealth Number', 'Pagibig ID', 'TIN Number', 'College/High School Diploma/TOR',
        'Marriage Certificate', 'Solo Parent ID', 'BIR Form 2305/1905', 'Latest BIR Form 2316',
        'Certificate of Employment', 'Date Submitted', 'Requirement Status',
    ]);
    writeRow($applicants, 6, [
        'APPLICATION-8202600006', 'Jayvee Dela Cruz', 'Checker/TL', '2026-01-05', 'Onboarding',
        true, true, false, false, true, true, true, false, false, false, false, false, false, false, false,
        '', 'Incomplete',
    ]);

    $source = $spreadsheet->createSheet();
    $source->setTitle('Source Data');
    writeRow($source, 1, ['Employee Statuses', 'Company Positions']);
    writeRow($source, 2, ['Active', 'Checker']);
    writeRow($source, 3, ['In Active', 'New Position']);

    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'workbook.xlsx', null, null, true);
}

it('imports employees, applicants, and option lists from the multi-sheet workbook', function () {
    $result = app(EmployeeWorkbookImportService::class)->import(buildWorkbookFile());

    expect($result['employees']['created'])->toBe(2)
        ->and($result['applicants']['created'])->toBe(1)
        ->and($result['sheets_missing'])->toBe([]);

    $employee = Employee::query()->where('employee_code', '25150')->firstOrFail();
    expect($employee->first_name)->toBe('Mark Anthony')
        ->and($employee->last_name)->toBe('Marin')
        ->and($employee->gender)->toBe('male')
        ->and($employee->marital_status)->toBe('married')
        ->and($employee->status)->toBe('active')
        ->and($employee->position)->toBe('Shift supervisor')
        ->and($employee->shift_schedule)->toBe('flexible')
        ->and($employee->employment_status)->toBe('regular')
        ->and((float) $employee->basic_salary)->toBe(25000.0)
        ->and($employee->salary_type)->toBe('monthly')
        ->and($employee->sss_number)->toBe('04-3293740-8')
        ->and($employee->immediate_superior)->toBe('Paje, Abigail');

    $newHire = Employee::query()->where('employee_code', '25999')->firstOrFail();
    expect($newHire->first_name)->toBe('Hire')
        ->and($newHire->last_name)->toBe('New')
        ->and($newHire->position)->toBe('Checker')
        ->and($newHire->salary_type)->toBe('semi_monthly');

    $applicant = Applicant::query()->where('application_number', 'APPLICATION-8202600006')->firstOrFail();
    expect($applicant->applicant_name)->toBe('Jayvee Dela Cruz')
        ->and($applicant->application_status)->toBe('onboarding')
        ->and($applicant->requirement_status)->toBe('incomplete')
        ->and($applicant->req_psa_birth_certificate)->toBeTrue()
        ->and($applicant->req_nbi_clearance)->toBeFalse();

    expect(OptionList::query()->where('category', 'position')->where('value', 'New Position')->exists())->toBeTrue();
});

it('resolves cross-sheet formula cells instead of importing the formula source text', function () {
    // Mirrors the real client workbook: Employment Details pulls the
    // Employee ID/Name via a formula like
    // =IF('Employee Master File'!B163="","",'Employee Master File'!B163)
    // rather than a literal value, including for still-blank rows.
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    $master = $spreadsheet->createSheet();
    $master->setTitle('Employee Master File');
    writeRow($master, 5, ['Employee ID', 'Last Name', 'First Name']);
    writeRow($master, 6, ['25150', 'Marin', 'Mark Anthony']);
    // Row 7 is intentionally blank on the source sheet — the formula below
    // must resolve to an empty string here, not literal formula text.

    $employment = $spreadsheet->createSheet();
    $employment->setTitle('Employment Details');
    writeRow($employment, 5, ['Employee ID', 'Employee Name', 'Position']);
    $employment->getCell('A6')->setValue("=IF('Employee Master File'!A6=\"\",\"\",'Employee Master File'!A6)");
    $employment->getCell('B6')->setValue("=IF('Employee Master File'!A6=\"\",\"\",'Employee Master File'!B6&\", \"&'Employee Master File'!C6)");
    $employment->getCell('C6')->setValue('Shift supervisor');
    $employment->getCell('A7')->setValue("=IF('Employee Master File'!A7=\"\",\"\",'Employee Master File'!A7)");
    $employment->getCell('B7')->setValue("=IF('Employee Master File'!A7=\"\",\"\",'Employee Master File'!B7&\", \"&'Employee Master File'!C7)");

    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'workbook.xlsx', null, null, true);

    $result = app(EmployeeWorkbookImportService::class)->import($file);

    expect($result['employees']['created'])->toBe(1)
        ->and(Employee::query()->count())->toBe(1);

    $employee = Employee::query()->firstOrFail();
    expect($employee->employee_code)->toBe('25150')
        ->and($employee->position)->toBe('Shift supervisor')
        ->and($employee->employee_code)->not->toContain('=IF');
});

it('reads checklist columns whose header is merged into the row above the field labels', function () {
    // Mirrors the real client tracker: "Application #" etc. sit on row 5,
    // but the checklist column headers ("PSA/Birth Certificate", …) are on
    // row 4, merged across rows 4:5 — row 5 is blank for those columns.
    // Also covers the separate Diploma / Transcript of Records columns that
    // both fold into the single req_college_diploma_tor DB field.
    $spreadsheet = new Spreadsheet;
    $spreadsheet->removeSheetByIndex(0);

    $applicants = $spreadsheet->createSheet();
    $applicants->setTitle('Application Onboarding Tracker');
    writeRow($applicants, 4, [
        '', '', '', '', '',
        'PSA/Birth Certificate', 'Government IDs (2) Copy', 'NBI Clearance',
        'College / Highschool Diploma', 'Transcript of Records (TOR)',
    ]);
    writeRow($applicants, 5, [
        'Application #', 'Applicant Name', 'Position Applied', 'Date of Application', 'Application Status',
    ]);
    writeRow($applicants, 6, [
        'APPLICATION-8202600001', 'Mark Anthony Marin', 'Shift supervisor', '2026-01-01', 'Onboarding',
        true, true, false, false, true,
    ]);
    writeRow($applicants, 7, [
        'APPLICATION-8202600002', 'Arjeleen Perez', 'Shift supervisor', '2026-01-01', 'Onboarding',
        false, false, false, true, false,
    ]);

    $path = tempnam(sys_get_temp_dir(), 'wb').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);
    $file = new UploadedFile($path, 'workbook.xlsx', null, null, true);

    app(EmployeeWorkbookImportService::class)->import($file);

    $first = Applicant::query()->where('application_number', 'APPLICATION-8202600001')->firstOrFail();
    expect($first->req_psa_birth_certificate)->toBeTrue()
        ->and($first->req_government_ids)->toBeTrue()
        ->and($first->req_nbi_clearance)->toBeFalse()
        // Diploma column false, TOR column true — OR'd into one true.
        ->and($first->req_college_diploma_tor)->toBeTrue();

    $second = Applicant::query()->where('application_number', 'APPLICATION-8202600002')->firstOrFail();
    expect($second->req_psa_birth_certificate)->toBeFalse()
        // Diploma column true here — still true via the OR.
        ->and($second->req_college_diploma_tor)->toBeTrue();
});

it('is idempotent on re-import', function () {
    $file = buildWorkbookFile();
    app(EmployeeWorkbookImportService::class)->import($file);
    $result = app(EmployeeWorkbookImportService::class)->import(buildWorkbookFile());

    expect($result['employees']['created'])->toBe(0)
        ->and($result['employees']['updated'])->toBe(2)
        ->and(Employee::query()->count())->toBe(2);
});
