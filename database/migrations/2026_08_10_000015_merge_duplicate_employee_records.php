<?php

use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use Illuminate\Database\Migrations\Migration;

/**
 * Six people were seeded twice under two different employee_code schemes:
 * RealEmployeeSeeder's short numeric codes (125, 225, ...) and the "201
 * Files" 5-digit codes (25176, 25178, ...) from EmployeeMasterFileSeeder.
 * The 5-digit record is the canonical one going forward (it's what the
 * Profile/Master File/Government Benefits/Compensation tabs are keyed to),
 * so this folds the short-code record's payroll rate fields and department
 * into it, reassigns its DTR/attendance history, and removes the duplicate.
 */
return new class extends Migration
{
    /** @var array<string, string> old employee_code => canonical employee_code */
    private const DUPLICATES = [
        '125' => '25176', // Christian Biclar
        '225' => '25178', // Jonnel Tumaob
        '325' => '25177', // Zeus Jay Sanchez
        '425' => '25179', // Eugene Panergayo
        '625' => '25182', // Jomar Javier
        '725' => '25181', // Christian Lising
    ];

    public function up(): void
    {
        foreach (self::DUPLICATES as $oldCode => $canonicalCode) {
            $old = Employee::withTrashed()->where('employee_code', $oldCode)->first();
            $canonical = Employee::withTrashed()->where('employee_code', $canonicalCode)->first();

            if (! $old || ! $canonical) {
                continue;
            }

            $canonical->update([
                'department' => $canonical->department ?? $old->department,
                'basic_salary' => $old->basic_salary,
                'daily_rate' => $old->daily_rate,
                'sunday_route_rate' => $old->sunday_route_rate,
                'hourly_rate' => $old->hourly_rate,
            ]);

            DtrLog::where('employee_id', $old->id)->update(['employee_id' => $canonical->id]);
            AttendanceRecord::where('employee_id', $old->id)->update(['employee_id' => $canonical->id]);

            $old->forceDelete();
        }
    }

    public function down(): void
    {
        // Irreversible: the duplicate rows and their original IDs are gone.
    }
};
