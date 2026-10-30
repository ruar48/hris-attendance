<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\OptionList;
use Illuminate\Database\Seeder;

/**
 * Who works at the Taguig and Batangas sites, from those sites' "Manning
 * 2026" tabs. People already in the 201 files are matched by employee code
 * (some under a different spelling than the Manning tab uses); the Taguig
 * staff not in the 201 files yet get temporary TAG- codes to replace later.
 */
class SiteManningSeeder extends Seeder
{
    /**
     * code => [site, shift_schedule option value or null, position or null to keep].
     *
     * @var array<string, array{0: string, 1: string|null, 2: string|null}>
     */
    protected const EXISTING = [
        // Batangas Manning 2026
        '25165' => ['Batangas', '0600-1500', null],             // Maderazo, Jimmar
        '25164' => ['Batangas', '1300-2200', null],             // Gillaco, Francisco Tapurok
        '25166' => ['Batangas', '0600-1500', 'Utility Personnel'], // De Guzman, Victor
        '25168' => ['Batangas', '1200-2100', null],             // Mercado, Anthony
        '25167' => ['Batangas', '0700-1600', null],             // Lasala, Gian Paulo
        '25163' => ['Batangas', '0600-1500', null],             // "Medrano", Princess Nicole
        '25162' => ['Batangas', '1300-2200', null],             // Exequiel P. "Collat"
        // On Batangas' August schedule, but Taguig's OPA since Sept 18, 2026.
        '25161' => ['Taguig', '0600-1500', null],               // Pascual, Micheal Vincent
    ];

    /**
     * Taguig Manning 2026 staff not in the 201 files yet.
     *
     * @var list<array<string, string|null>>
     */
    protected const NEW_TAGUIG = [
        ['code' => 'TAG-001', 'first' => 'Darlyn', 'middle' => 'San Jose', 'last' => 'Lamboson', 'position' => 'Inventory Analyst', 'shift' => null, 'start' => '2026-09-21'],
        ['code' => 'TAG-002', 'first' => 'Shammel', 'last' => 'Roga', 'position' => 'Inventory Analyst', 'shift' => '1800-0600', 'start' => '2026-09-21'],
        ['code' => 'TAG-003', 'first' => 'Enrico', 'last' => 'Reyes', 'position' => 'Forklift Operator', 'shift' => '0600-1500'],
        ['code' => 'TAG-004', 'first' => 'Rene', 'last' => 'Bansuelo', 'position' => 'Forklift Operator', 'shift' => '1300-2200'],
        ['code' => 'TAG-005', 'first' => 'James', 'last' => 'Orbigo', 'position' => 'Forklift Operator', 'shift' => '1300-2200', 'start' => '2026-09-24'],
        ['code' => 'TAG-006', 'first' => 'Garry', 'last' => 'Ramirez', 'position' => 'Checker', 'shift' => '0700-1600'],
        ['code' => 'TAG-007', 'first' => 'Jeoffrey', 'last' => 'Laureles', 'position' => 'Checker', 'shift' => '0600-1500'],
        ['code' => 'TAG-008', 'first' => 'Rianna', 'last' => 'Manuel', 'position' => 'Checker', 'shift' => '1300-2200'],
        ['code' => 'TAG-009', 'first' => 'Joshua', 'last' => 'Privaldo', 'position' => 'Checker', 'shift' => '1300-2200'],
        ['code' => 'TAG-010', 'first' => 'Pio', 'last' => 'Caasi', 'position' => 'Yardman', 'shift' => '0600-1500'],
        ['code' => 'TAG-011', 'first' => 'Vilmer', 'last' => 'Rivera', 'position' => 'Yardman', 'shift' => '1200-2100'],
        ['code' => 'TAG-012', 'first' => 'Clark Loiue', 'last' => 'Laureles', 'position' => 'Yardman', 'shift' => '0700-1600'],
        ['code' => 'TAG-013', 'first' => 'Aldin', 'last' => 'Glindro', 'position' => 'Yardman', 'shift' => '0700-1600', 'start' => '2026-09-21'],
        ['code' => 'TAG-014', 'first' => 'Eliezer Michelle', 'last' => 'Galapon', 'position' => 'Yardman', 'shift' => '0700-1600', 'start' => '2026-09-24'],
    ];

    /**
     * Batangas staff not in the 201 files yet (from their DTR tabs).
     *
     * @var list<array<string, string|null>>
     */
    protected const NEW_BATANGAS = [
        ['code' => 'BAT-001', 'first' => 'Joel', 'last' => 'Plata', 'position' => 'Checker', 'shift' => '0600-1500', 'start' => '2026-09-19'],
    ];

    public function run(): void
    {
        $shifts = OptionList::query()->category('shift_schedule')->get()->keyBy('value');
        $shiftFields = fn (?string $value) => [
            'shift_schedule' => $value,
            'time_in_schedule' => $value ? $shifts->get($value)?->time_in : null,
            'time_out_schedule' => $value ? $shifts->get($value)?->time_out : null,
        ];

        foreach (self::EXISTING as $code => [$site, $shift, $position]) {
            Employee::query()->where('employee_code', (string) $code)->update(array_filter([
                'work_location' => $site,
                ...$shiftFields($shift),
                'position' => $position,
            ], fn ($value) => $value !== null));
        }

        $newStaff = [
            ...array_map(fn (array $row) => [...$row, 'site' => 'Taguig'], self::NEW_TAGUIG),
            ...array_map(fn (array $row) => [...$row, 'site' => 'Batangas'], self::NEW_BATANGAS),
        ];

        foreach ($newStaff as $row) {
            Employee::query()->updateOrCreate(
                ['employee_code' => $row['code']],
                [
                    'first_name' => $row['first'],
                    'middle_name' => $row['middle'] ?? null,
                    'last_name' => $row['last'],
                    'position' => $row['position'],
                    'work_location' => $row['site'],
                    'hire_date' => $row['start'] ?? null,
                    'status' => 'active',
                    ...$shiftFields($row['shift']),
                ]
            );
        }
    }
}
