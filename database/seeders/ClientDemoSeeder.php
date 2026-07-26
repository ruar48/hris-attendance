<?php

namespace Database\Seeders;

use App\Models\BiometricDevice;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollSetting;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;

/**
 * Curated roster and scenarios for client presentations.
 *
 * Each employee showcases a different payroll feature:
 * - Late / undertime / OT / Sunday route / absence / DTR correction
 * - Cash advance repayment across kinsenas
 * - Statutory brackets at different salary levels
 */
class ClientDemoSeeder extends Seeder
{
    public const DEMO_YEAR = 2025;

    public const DEMO_YEAR_CURRENT = 2026;

  /** @return list<string> */
    public static function employeeCodes(): array
    {
        return array_column(static::employees(), 'employee_code');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function employees(): array
    {
        return [
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
                'hire_date' => '2022-03-01',
                'demo_note' => 'Late (day 3), OT (days 6–8), cash advance',
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
                'hire_date' => '2021-06-15',
                'demo_note' => 'Undertime (day 4), cash advance',
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
                'hire_date' => '2022-08-01',
                'demo_note' => 'DTR correction (May 10) when biometric failed',
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
                'hire_date' => '2020-01-10',
                'demo_note' => 'Clean attendance — ideal payslip reference',
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
                'hire_date' => '2022-11-20',
                'demo_note' => 'Sunday route pay every Sunday',
            ],
            [
                'employee_code' => 'EMP-0006',
                'first_name' => 'Rosa',
                'last_name' => 'Villanueva',
                'email' => 'rosa@payflow.test',
                'position' => 'Office Clerk',
                'department' => 'Admin',
                'basic_salary' => 12000,
                'daily_rate' => 480,
                'sunday_route_rate' => 0,
                'hourly_rate' => 60,
                'biometric_user_id' => 'BIO-006',
                'hire_date' => '2023-02-01',
                'demo_note' => 'Lower salary tier — SSS MSC floor applies',
            ],
            [
                'employee_code' => 'EMP-0007',
                'first_name' => 'Miguel',
                'last_name' => 'Torres',
                'email' => 'miguel@payflow.test',
                'position' => 'Fleet Supervisor',
                'department' => 'Operations',
                'basic_salary' => 22000,
                'daily_rate' => 880,
                'sunday_route_rate' => 1000,
                'hourly_rate' => 110,
                'biometric_user_id' => 'BIO-007',
                'hire_date' => '2019-05-01',
                'demo_note' => 'Works on Labor Day — holiday pay demo',
            ],
            [
                'employee_code' => 'EMP-0008',
                'first_name' => 'Liza',
                'last_name' => 'Fernandez',
                'email' => 'liza@payflow.test',
                'position' => 'Admin Assistant',
                'department' => 'Admin',
                'basic_salary' => 10000,
                'daily_rate' => 400,
                'sunday_route_rate' => 0,
                'hourly_rate' => 50,
                'biometric_user_id' => 'BIO-008',
                'hire_date' => '2023-07-15',
                'demo_note' => '₱10,000 basic — PhilHealth floor bracket',
            ],
            [
                'employee_code' => 'EMP-0009',
                'first_name' => 'Benito',
                'last_name' => 'Cruz',
                'email' => 'benito@payflow.test',
                'position' => 'Delivery Driver',
                'department' => 'Operations',
                'basic_salary' => 15000,
                'daily_rate' => 600,
                'sunday_route_rate' => 800,
                'hourly_rate' => 75,
                'biometric_user_id' => 'BIO-009',
                'hire_date' => '2024-04-01',
                'demo_note' => 'Standard driver — full-year attendance',
            ],
            [
                'employee_code' => 'EMP-0010',
                'first_name' => 'Sofia',
                'last_name' => 'Ramos',
                'email' => 'sofia@payflow.test',
                'position' => 'Warehouse Staff',
                'department' => 'Logistics',
                'basic_salary' => 14000,
                'daily_rate' => 560,
                'sunday_route_rate' => 0,
                'hourly_rate' => 70,
                'biometric_user_id' => 'BIO-010',
                'hire_date' => '2022-09-01',
                'demo_note' => 'Absence on Aug 12 each year',
            ],
            [
                'employee_code' => 'EMP-0011',
                'first_name' => 'Rico',
                'last_name' => 'Del Rosario',
                'email' => 'rico@payflow.test',
                'position' => 'Dispatcher',
                'department' => 'Operations',
                'basic_salary' => 18000,
                'daily_rate' => 720,
                'sunday_route_rate' => 900,
                'hourly_rate' => 90,
                'biometric_user_id' => 'BIO-011',
                'hire_date' => '2021-01-20',
                'demo_note' => 'Heavy OT in March (days 10–12)',
            ],
            [
                'employee_code' => 'EMP-0012',
                'first_name' => 'Elena',
                'last_name' => 'Santos',
                'email' => 'elena@payflow.test',
                'position' => 'HR Manager',
                'department' => 'HR',
                'basic_salary' => 25000,
                'daily_rate' => 1000,
                'sunday_route_rate' => 0,
                'hourly_rate' => 125,
                'biometric_user_id' => 'BIO-012',
                'hire_date' => '2018-03-01',
                'demo_note' => 'Higher bracket — Pag-IBIG cap applies',
            ],
        ];
    }

    public function run(): void
    {
        foreach (PayrollSetting::defaults() as $key => $value) {
            PayrollSetting::setValue($key, $value);
        }
        PayrollSetting::setValue('auto_13th_month', '1');
        PayrollSetting::setValue('payroll_frequency', 'kinsenas');

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@payflow.test'],
            [
                'name' => 'Juan Dela Cruz',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'hr@payflow.test'],
            [
                'name' => 'Ana Garcia',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

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
                'last_synced_at' => now()->subMinutes(45),
            ]
        );

        $created = collect();

        foreach (static::employees() as $data) {
            $userId = $data['employee_code'] === 'EMP-0001' ? $admin->id : null;
            unset($data['demo_note']);

            $created->push(Employee::query()->updateOrCreate(
                ['employee_code' => $data['employee_code']],
                array_merge($data, [
                    'user_id' => $userId,
                    'status' => 'active',
                ])
            ));
        }

        $this->seedCashAdvances($created);

        SystemNotification::query()->create([
            'title' => 'Client demo data ready',
            'message' => '12 employees · '.self::DEMO_YEAR.' full year + '.self::DEMO_YEAR_CURRENT.' Jan–Jun · Login: admin@payflow.test / password',
            'type' => 'success',
        ]);

        SystemNotification::query()->create([
            'title' => 'PH holidays loaded',
            'message' => 'Regular + movable holidays for '.self::DEMO_YEAR.'–'.self::DEMO_YEAR_CURRENT.' are configured.',
            'type' => 'info',
        ]);
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    protected function seedCashAdvances(Collection $employees): void
    {
        $juan = $employees->firstWhere('employee_code', 'EMP-0001');
        $maria = $employees->firstWhere('employee_code', 'EMP-0002');

        if ($juan) {
            CashAdvance::query()->updateOrCreate(
                [
                    'employee_id' => $juan->id,
                    'released_at' => self::DEMO_YEAR.'-01-10',
                ],
                [
                    'amount' => 3000,
                    'balance' => 3000,
                    'deduction_per_payroll' => 500,
                    'status' => 'active',
                    'notes' => 'Emergency cash advance — ₱500 deducted per kinsena until paid',
                ]
            );
        }

        if ($maria) {
            CashAdvance::query()->updateOrCreate(
                [
                    'employee_id' => $maria->id,
                    'released_at' => self::DEMO_YEAR.'-03-05',
                ],
                [
                    'amount' => 1500,
                    'balance' => 1500,
                    'deduction_per_payroll' => 500,
                    'status' => 'active',
                    'notes' => 'Medical cash advance — ₱500 per kinsena',
                ]
            );
        }
    }
}
