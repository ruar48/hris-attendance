<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OptionListSeeder::class);
        $this->call(PhilippineHolidaySeeder::class);
        $this->call(AdminUserSeeder::class);

        // The client's 201 files, then attendance from their time sheet workbook.
        $this->call(EmployeeMasterFileSeeder::class);
        $this->call(EmployeeCompensationSeeder::class);
        $this->call(EmployeeGovernmentBenefitsSeeder::class);
        $this->call(TayabasTimesheetSeeder::class);
    }
}
