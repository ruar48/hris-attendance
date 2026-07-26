<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PhilippineHolidaySeeder::class);
        $this->call(ClientDemoSeeder::class);
        $this->call(YearlyPayrollSeeder::class);
    }
}
