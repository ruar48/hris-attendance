<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class PhilippineHolidaySeeder extends Seeder
{
    /**
     * Fixed regular holidays repeat every year (month/day only).
     * Movable holidays are stored with a specific year date.
     */
    public function run(): void
    {
        // Fixed regular holidays — no year needed
        $yearlyRegular = [
            ['month' => 1, 'day' => 1, 'name' => "New Year's Day"],
            ['month' => 4, 'day' => 9, 'name' => 'Araw ng Kagitingan (Day of Valor)'],
            ['month' => 5, 'day' => 1, 'name' => 'Labor Day'],
            ['month' => 6, 'day' => 12, 'name' => 'Independence Day'],
            ['month' => 11, 'day' => 30, 'name' => 'Bonifacio Day'],
            ['month' => 12, 'day' => 25, 'name' => 'Christmas Day'],
            ['month' => 12, 'day' => 30, 'name' => 'Rizal Day'],
        ];

        foreach ($yearlyRegular as $holiday) {
            Holiday::query()->updateOrCreate(
                [
                    'is_recurring' => true,
                    'month' => $holiday['month'],
                    'day' => $holiday['day'],
                ],
                [
                    'name' => $holiday['name'],
                    'date' => null,
                    'type' => 'regular',
                    'pay_multiplier' => 2.00,
                ]
            );
        }

        // Fixed special days that also repeat yearly
        $yearlySpecial = [
            ['month' => 8, 'day' => 21, 'name' => 'Ninoy Aquino Day'],
            ['month' => 11, 'day' => 1, 'name' => "All Saints' Day"],
            ['month' => 11, 'day' => 2, 'name' => "All Souls' Day"],
            ['month' => 12, 'day' => 8, 'name' => 'Feast of the Immaculate Conception'],
            ['month' => 12, 'day' => 24, 'name' => 'Christmas Eve'],
            ['month' => 12, 'day' => 31, 'name' => 'Last Day of the Year'],
        ];

        foreach ($yearlySpecial as $holiday) {
            Holiday::query()->updateOrCreate(
                [
                    'is_recurring' => true,
                    'month' => $holiday['month'],
                    'day' => $holiday['day'],
                ],
                [
                    'name' => $holiday['name'],
                    'date' => null,
                    'type' => 'special',
                    'pay_multiplier' => 1.30,
                ]
            );
        }

        // Movable holidays — need a specific date each year
        $movable = [
            // 2024
            ['date' => '2024-02-10', 'name' => 'Chinese New Year', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2024-03-28', 'name' => 'Maundy Thursday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2024-03-29', 'name' => 'Good Friday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2024-03-30', 'name' => 'Black Saturday', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2024-08-26', 'name' => 'National Heroes Day', 'type' => 'regular', 'pay_multiplier' => 2.00],

            // 2025
            ['date' => '2025-01-29', 'name' => 'Chinese New Year', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2025-04-17', 'name' => 'Maundy Thursday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2025-04-18', 'name' => 'Good Friday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2025-04-19', 'name' => 'Black Saturday', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2025-08-25', 'name' => 'National Heroes Day', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2025-10-31', 'name' => "All Saints' Day Eve", 'type' => 'special', 'pay_multiplier' => 1.30],

            // 2026
            ['date' => '2026-02-17', 'name' => 'Chinese New Year', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2026-03-20', 'name' => "Eid'l Fitr (Feast of Ramadhan)", 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2026-04-03', 'name' => 'Good Friday', 'type' => 'regular', 'pay_multiplier' => 2.00],
            ['date' => '2026-04-04', 'name' => 'Black Saturday', 'type' => 'special', 'pay_multiplier' => 1.30],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day', 'type' => 'regular', 'pay_multiplier' => 2.00],
        ];

        foreach ($movable as $holiday) {
            Holiday::query()->updateOrCreate(
                ['date' => $holiday['date']],
                [
                    'name' => $holiday['name'],
                    'is_recurring' => false,
                    'month' => null,
                    'day' => null,
                    'type' => $holiday['type'],
                    'pay_multiplier' => $holiday['pay_multiplier'],
                ]
            );
        }
    }
}
