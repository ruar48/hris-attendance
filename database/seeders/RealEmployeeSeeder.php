<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Formerly seeded 6 employees under short numeric codes (125, 225, ...).
 * Those turned out to be the same 6 people already covered by
 * EmployeeMasterFileSeeder under the "201 Files" 5-digit codes (25176,
 * 25178, ...), so seeding both created duplicate employee records — see
 * the 2026_08_10_000015_merge_duplicate_employee_records migration, which
 * folded the short-code records into their 5-digit counterparts and
 * removed the duplicates. Left as an empty seeder (rather than deleted) in
 * case a genuinely new short-code import shows up later.
 */
class RealEmployeeSeeder extends Seeder
{
    /**
     * @return list<array<string, mixed>>
     */
    public static function employees(): array
    {
        return [];
    }

    public function run(): void
    {
        foreach (static::employees() as $data) {
            Employee::query()->updateOrCreate(
                ['employee_code' => $data['employee_code']],
                array_merge($data, [
                    'basic_salary' => 15000,
                    'daily_rate' => 600,
                    'sunday_route_rate' => 0,
                    'hourly_rate' => 75,
                    'status' => 'active',
                ])
            );
        }
    }
}
