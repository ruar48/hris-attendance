<?php

use App\Http\Controllers\BiometricController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DtrController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\GovernmentBenefitsController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollSettingsController;
use App\Http\Controllers\PayslipPdfController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::get('employees', [EmployeeController::class, 'index'])->name('employees.index');
    Route::post('employees', [EmployeeController::class, 'store'])->name('employees.store');

    Route::get('biometrics', [BiometricController::class, 'index'])->name('biometrics.index');
    Route::post('biometrics/sync', [BiometricController::class, 'sync'])->name('biometrics.sync');

    Route::get('dtr', [DtrController::class, 'index'])->name('dtr.index');
    Route::post('dtr', [DtrController::class, 'store'])->name('dtr.store');

    Route::get('cash-advances', [CashAdvanceController::class, 'index'])->name('cash-advances.index');
    Route::post('cash-advances', [CashAdvanceController::class, 'store'])->name('cash-advances.store');

    Route::get('holidays', [HolidayController::class, 'index'])->name('holidays.index');
    Route::post('holidays', [HolidayController::class, 'store'])->name('holidays.store');
    Route::delete('holidays/{holiday}', [HolidayController::class, 'destroy'])->name('holidays.destroy');

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
