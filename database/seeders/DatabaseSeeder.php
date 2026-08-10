<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OptionListSeeder::class);
        $this->call(PhilippineHolidaySeeder::class);
        $this->call(ClientDemoSeeder::class);
        $this->call(YearlyPayrollSeeder::class);
        $this->call(RealEmployeeSeeder::class);
        $this->call(EmployeeMasterFileSeeder::class);
        $this->call(EmployeeGovernmentBenefitsSeeder::class);
        $this->call(EmployeeCompensationSeeder::class);
    }
}
