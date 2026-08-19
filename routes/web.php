<?php

use App\Http\Controllers\ApplicantController;
use App\Http\Controllers\BiometricController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DtrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GovernmentBenefitsController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\OptionListController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollSettingsController;
use App\Http\Controllers\AttendanceSummaryController;
use App\Http\Controllers\PayslipPdfController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftTypeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
})->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::get('employees/profile', [EmployeeController::class, 'profile'])->name('employees.profile');
    Route::get('employees/employment-details', [EmployeeController::class, 'employmentDetails'])
        ->name('employees.employment-details');
    Route::get('employees/master-file', [EmployeeController::class, 'masterFile'])
        ->name('employees.master-file');
    Route::get('employees/government-benefits', [EmployeeController::class, 'governmentBenefits'])
        ->name('employees.government-benefits');
    Route::get('employees/compensation-payroll', [EmployeeController::class, 'compensationPayroll'])
        ->name('employees.compensation-payroll');
    Route::get('employees/source-data', [OptionListController::class, 'index'])->name('employees.source-data');
    Route::post('option-lists', [OptionListController::class, 'store'])->name('option-lists.store');
    Route::put('option-lists/{optionList}', [OptionListController::class, 'update'])->name('option-lists.update');
    Route::delete('option-lists/{optionList}', [OptionListController::class, 'destroy'])->name('option-lists.destroy');
    Route::post('employees/import', [EmployeeController::class, 'import'])->name('employees.import');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');
    Route::put('employees/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    Route::delete('employees/{employee}', [EmployeeController::class, 'destroy'])->name('employees.destroy');
    Route::put('employees/{employee}/restore', [EmployeeController::class, 'restore'])
        ->withTrashed()
        ->name('employees.restore');

    Route::get('applicants', [ApplicantController::class, 'index'])->name('applicants.index');
    Route::post('applicants', [ApplicantController::class, 'store'])->name('applicants.store');
    Route::put('applicants/{applicant}', [ApplicantController::class, 'update'])->name('applicants.update');
    Route::delete('applicants/{applicant}', [ApplicantController::class, 'destroy'])->name('applicants.destroy');

    Route::get('biometrics', [BiometricController::class, 'index'])->name('biometrics.index');
    Route::post('biometrics/import', [BiometricController::class, 'import'])->name('biometrics.import');

    Route::get('dtr', [DtrController::class, 'index'])->name('dtr.index');
    Route::post('dtr', [DtrController::class, 'store'])->name('dtr.store');

    Route::get('cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances.index');
    Route::post('cash-advances', [CashAdvanceController::class, 'store'])->name('cash-advances.store');
    Route::put('cash-advances/{cashAdvance}', [CashAdvanceController::class, 'update'])
        ->name('cash-advances.update');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

    Route::post('shift-types', [ShiftTypeController::class, 'store'])->name('shift-types.store');
    Route::put('shift-types/{shiftType}', [ShiftTypeController::class, 'update'])->name('shift-types.update');
    Route::delete('shift-types/{shiftType}', [ShiftTypeController::class, 'destroy'])->name('shift-types.destroy');

    Route::get('schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::post('schedules/bulk', [ScheduleController::class, 'bulkUpdate'])->name('schedules.bulk');

    Route::get('attendance-summary', [AttendanceSummaryController::class, 'index'])
        ->name('attendance-summary.index');
    Route::get('attendance-summary/abnormal', [AttendanceSummaryController::class, 'abnormal'])
        ->name('attendance-summary.abnormal');
    Route::get('attendance-summary/report', [AttendanceSummaryController::class, 'report'])
        ->name('attendance-summary.report');
    Route::get('attendance-summary/report/pdf', [AttendanceSummaryController::class, 'reportPdf'])
        ->name('attendance-summary.report.pdf');

    Route::get('payroll', [PayrollController::class, 'index'])->name('payroll.index');
    Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
    Route::get('payroll/{payroll}', [PayrollController::class, 'show'])->name('payroll.show');
    Route::get('payroll/{payroll}/payslips/pdf', [PayslipPdfController::class, 'downloadAll'])
        ->name('payroll.payslips.pdf.all');
    Route::get('payroll/{payroll}/payslips/{payslip}/pdf', [PayslipPdfController::class, 'downloadOne'])
        ->name('payroll.payslips.pdf.one');

    Route::get('payroll-settings', [PayrollSettingsController::class, 'edit'])->name('payroll-settings.edit');
    Route::put('payroll-settings', [PayrollSettingsController::class, 'update'])->name('payroll-settings.update');

    Route::get('government-benefits', [GovernmentBenefitsController::class, 'index'])
        ->name('government-benefits.index');
});

require __DIR__.'/settings.php';
