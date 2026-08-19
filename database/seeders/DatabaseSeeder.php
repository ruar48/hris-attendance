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
    }
}
