<?php

namespace Database\Seeders;

use App\Models\BiometricDevice;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollSetting;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@payflow.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        foreach (PayrollSetting::defaults() as $key => $value) {
            PayrollSetting::setValue($key, $value);
        }
        PayrollSetting::setValue('auto_13th_month', '1');
        PayrollSetting::setValue('payroll_frequency', 'kinsenas');

        $this->call(PhilippineHolidaySeeder::class);

        BiometricDevice::query()->updateOrCreate(
            ['serial_number' => 'ZK-FP-001'],
            [
                'name' => 'Main Gate Fingerprint',
                'location' => 'Lobby Entrance',
                'status' => 'online',
                'last_synced_at' => now(),
            ]
        );

        BiometricDevice::query()->updateOrCreate(
            ['serial_number' => 'ZK-FP-002'],
            [
                'name' => 'Warehouse Fingerprint',
                'location' => 'Warehouse Exit',
                'status' => 'online',
                'last_synced_at' => now()->subHour(),
            ]
        );

        $employees = [
            [
                'employee_code' => 'EMP-0001',
                'first_name' => 'Juan',
                'last_name' => 'Dela Cruz',
                'email' => 'juan@payflow.test',
                'position' => 'Delivery Driver',
                'department' => 'Operations',
                'basic_salary' => 15000,
                'daily_rate' => 600,
                'sunday_route_rate' => 800,
                'hourly_rate' => 75,
                'biometric_user_id' => 'BIO-001',
                'user_id' => $admin->id,
            ],
            [
                'employee_code' => 'EMP-0002',
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'email' => 'maria@payflow.test',
                'position' => 'Dispatcher',
                'department' => 'Operations',
                'basic_salary' => 18000,
                'daily_rate' => 720,
                'sunday_route_rate' => 900,
                'hourly_rate' => 90,
                'biometric_user_id' => 'BIO-002',
            ],
            [
                'employee_code' => 'EMP-0003',
                'first_name' => 'Pedro',
                'last_name' => 'Reyes',
                'email' => 'pedro@payflow.test',
                'position' => 'Warehouse Staff',
                'department' => 'Logistics',
                'basic_salary' => 14000,
                'daily_rate' => 560,
                'sunday_route_rate' => 700,
                'hourly_rate' => 70,
                'biometric_user_id' => 'BIO-003',
            ],
            [
                'employee_code' => 'EMP-0004',
                'first_name' => 'Ana',
                'last_name' => 'Garcia',
                'email' => 'ana@payflow.test',
                'position' => 'HR Assistant',
                'department' => 'HR',
                'basic_salary' => 16000,
                'daily_rate' => 640,
                'sunday_route_rate' => 0,
                'hourly_rate' => 80,
                'biometric_user_id' => 'BIO-004',
            ],
            [
                'employee_code' => 'EMP-0005',
                'first_name' => 'Carlo',
                'last_name' => 'Mendoza',
                'email' => 'carlo@payflow.test',
                'position' => 'Delivery Driver',
                'department' => 'Operations',
                'basic_salary' => 15000,
                'daily_rate' => 600,
                'sunday_route_rate' => 800,
                'hourly_rate' => 75,
                'biometric_user_id' => 'BIO-005',
            ],
        ];

        $createdEmployees = collect();
        foreach ($employees as $data) {
            $createdEmployees->push(Employee::query()->updateOrCreate(
                ['employee_code' => $data['employee_code']],
                array_merge($data, [
                    'hire_date' => '2023-01-15',
                    'status' => 'active',
                ])
            ));
        }

        for ($i = 6; $i <= 128; $i++) {
            $code = sprintf('EMP-%04d', $i);
            $createdEmployees->push(Employee::query()->updateOrCreate(
                ['employee_code' => $code],
                [
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'email' => "employee{$i}@payflow.test",
                    'position' => fake()->randomElement(['Delivery Driver', 'Warehouse Staff', 'Dispatcher', 'Clerk']),
                    'department' => fake()->randomElement(['Operations', 'Logistics', 'HR', 'Admin']),
                    'basic_salary' => fake()->randomElement([12000, 14000, 15000, 16000, 18000]),
                    'daily_rate' => 600,
                    'sunday_route_rate' => fake()->randomElement([0, 700, 800, 900]),
                    'hourly_rate' => 75,
                    'biometric_user_id' => sprintf('BIO-%03d', $i),
                    'hire_date' => fake()->dateTimeBetween('-3 years', '-1 month')->format('Y-m-d'),
                    'status' => 'active',
                ]
            ));
        }

        // Cash advances that deduct across early months (max ₱1,000 / payroll)
        CashAdvance::query()->updateOrCreate(
            [
                'employee_id' => $createdEmployees[0]->id,
                'released_at' => '2024-01-10',
            ],
            [
                'amount' => 3000,
                'balance' => 3000,
                'status' => 'active',
                'notes' => 'Emergency cash advance — demo multi-month deduction',
            ]
        );

        CashAdvance::query()->updateOrCreate(
            [
                'employee_id' => $createdEmployees[1]->id,
                'released_at' => '2024-03-05',
            ],
            [
                'amount' => 1500,
                'balance' => 1500,
                'status' => 'active',
                'notes' => 'Medical cash advance',
            ]
        );

        SystemNotification::query()->create([
            'title' => 'PH holidays loaded',
            'message' => 'Yearly regular holidays + movable dates for 2024–2026 are ready.',
            'type' => 'info',
        ]);

        $this->call(YearlyPayrollSeeder::class);
    }
}
