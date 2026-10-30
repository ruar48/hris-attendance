<?php

namespace Database\Seeders;

use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\User;
use App\Services\AttendanceResolver;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * DAILY TIME RECORD tabs from the client's "Batangas time sheet WHPEP"
 * workbook, 2026, mirrored column for column: an afternoon shift's 13:00
 * stays under PM Arrival, an early 6:00 written under AM Departure stays
 * there, and anything written beside the form (times, "STRAIGHT DUTY",
 * "PLS. IR") is kept as an outside note.
 *
 * A day is either a status ("day_off", "absent", "awol") or an array:
 *   [0] / 'in'  AM arrival      'ao' AM departure
 *   'pi'        PM arrival      [1] / 'out' PM departure
 *   'note'      [arrival, departure] written outside the form
 *   'text'      words written outside the form
 *   'remark'    the yellow note left of the form
 *   'status'    for a non-working day that also carries notes
 */
class BatangasTimesheetSeeder extends Seeder
{
    protected const PM_13_22 = ['pi' => '13:00', 'out' => '22:00'];

    protected const PM_12_20 = ['pi' => '12:00', 'out' => '20:00'];

    /**
     * @return array<string, array<string, array<int, string|array<int|string, mixed>>>>
     */
    protected static function sheets(): array
    {
        $pm13 = self::PM_13_22;
        $pm12 = self::PM_12_20;
        $absentIr = fn (string $text) => ['status' => 'absent', 'text' => $text];

        return [
            // Lasala, Gian Paulo — Yardman
            '25167' => [
                '2026-08' => [
                    1 => ['6:48', '19:17'], 2 => 'day_off',
                    3 => ['6:55', '20:00'], 4 => ['6:53', '19:19'], 5 => ['6:56', '18:22'], 6 => ['6:51', '19:02'],
                    7 => ['6:54', '18:01'], 8 => ['6:52', '18:20'], 9 => 'day_off',
                    10 => ['6:56', '18:22'], 11 => ['7:05', '18:21'], 12 => ['6:56', '18:00'], 13 => ['6:56', '18:00'],
                    14 => ['6:54', '18:00'], 15 => ['6:49', '19:01'], 16 => 'day_off',
                    17 => ['6:55', '20:00'], 18 => ['7:00', '17:00'], 19 => ['note' => ['6:48', '19:01']],
                    20 => ['6:47', '18:04'], 21 => ['6:48', '19:01'], 22 => ['6:47', '19:14'],
                    24 => ['6:56', '19:00'], 25 => ['6:57', '20:03'], 26 => ['note' => ['6:55', '20:29']],
                    27 => ['6:54', '20:00'], 28 => ['6:51', '18:00', 'note' => [null, '18:00']], 29 => ['6:53', '18:00'],
                    30 => 'day_off', 31 => ['6:41', '20:34'],
                ],
                '2026-09' => [
                    1 => ['6:53', '19:16'], 2 => ['6:45', '18:28'], 3 => ['6:54', '20:00'], 4 => ['6:52', '19:02'],
                    5 => ['6:52', '19:10'], 6 => 'day_off',
                    7 => ['6:58', '19:13'], 8 => ['6:55', '19:00'], 9 => ['6:56', '19:32'], 10 => ['6:49', '19:00'],
                    11 => ['6:46', '19:05'], 12 => ['6:07', '19:01'], 13 => 'day_off', 14 => ['in' => '6:52'],
                    16 => ['6:52', '19:04'], 17 => ['6:59', '20:00'], 18 => ['6:51', '19:24'], 19 => ['6:57', '19:21'],
                    20 => 'day_off',
                    21 => ['6:58', '19:02'], 22 => ['6:53', '20:00'], 23 => ['6:57', '20:00'], 24 => ['6:59', '20:01'],
                    25 => ['7:17', '18:39'], 26 => ['6:58', '20:01'], 27 => 'day_off',
                    28 => ['6:56', '20:04'], 29 => ['6:58', '20:00'], 30 => ['6:59', '21:02'],
                ],
                '2026-10' => [
                    1 => ['note' => ['6:54', '16:06']], 2 => ['note' => ['6:56', '16:03']],
                    3 => ['6:50', '16:15'], 4 => 'day_off',
                    5 => ['6:54', '16:11'], 6 => ['6:58', '16:13'], 7 => ['6:59', '16:00'], 8 => ['6:57', '16:19'],
                    9 => ['6:54', '16:22'], 10 => ['6:56', '16:06'], 11 => 'day_off',
                    12 => ['7:09', '15:59'], 13 => ['6:45', '16:03'], 14 => ['6:57', '16:04'], 15 => ['6:52', '16:01'],
                    16 => ['6:50', '16:01'], 17 => ['6:48', '16:00'], 18 => 'day_off',
                    19 => ['7:03', '16:04'], 20 => ['6:56', '16:00'], 21 => ['6:56', '16:00'], 25 => 'day_off',
                ],
                // November is still ahead: only the day offs are marked.
                '2026-11' => [1 => 'day_off', 8 => 'day_off', 15 => 'day_off', 22 => 'day_off'],
            ],

            // Medrano Lapuz, Princess Nicole — Checker
            '25163' => [
                '2026-06' => [
                    1 => ['5:57', '15:00'], 2 => ['5:40', '15:05'], 3 => ['6:00', '15:02'], 4 => ['5:56', '15:01'],
                    5 => ['6:00', '15:03'], 6 => ['5:10', '15:20'], 7 => 'day_off',
                    8 => ['5:50', '15:05'], 9 => ['5:40', '15:06'], 10 => ['pi' => '12:44', 'out' => '22:00'],
                    11 => ['5:30', '15:09'], 12 => ['5:20', '15:05'], 13 => ['5:45', '15:04'], 14 => 'day_off',
                    15 => ['6:00', '15:04'], 16 => ['12:30', '22:00'], 17 => ['12:55', '22:00'],
                    18 => ['6:00', '17:28', 'text' => '2HRS OVERTIME'], 19 => ['6:00', '15:15'], 20 => ['6:00', '15:09'],
                    21 => 'day_off', 22 => ['6:00', '15:00'], 23 => ['6:00', '15:10'],
                    24 => $pm13, 25 => ['pi' => '12:55', 'out' => '22:00'], 26 => $absentIr('PLS. IR'),
                    27 => ['6:00', '15:05', 'remark' => 'Holiday'], 28 => 'day_off', 29 => ['12:37', '22:00'],
                    30 => $absentIr('PLS. IR'),
                ],
                '2026-07' => [
                    1 => $pm13, 2 => ['pi' => '12:40', 'out' => '22:00'], 3 => ['6:00', '15:00'], 4 => ['6:00', '15:10'],
                    5 => 'day_off', 6 => 'absent', 7 => ['5:40', '19:30', 'text' => '4 HOURS OT FOR OFD'], 8 => 'absent',
                    9 => ['5:50', '22:00', 'text' => 'STRAIGHT DUTY'], 10 => ['6:00', '15:00'], 11 => ['6:00', '15:44'],
                    12 => 'day_off', 13 => ['6:00', '15:30'], 14 => ['6:00', '15:15'], 15 => ['6:00', '15:15'],
                    16 => ['5:51', '15:00'], 17 => 'absent', 18 => ['6:00', '22:00'], 19 => 'day_off',
                    20 => ['5:30', '15:00'], 21 => ['5:52', '15:00'], 22 => ['5:32', '19:03'], 23 => ['6:02', '18:04'],
                    24 => ['5:48', '15:00'], 25 => ['in' => '5:59', 'note' => [null, '16:18']], 26 => 'day_off',
                    27 => ['note' => ['5:59', '15:05']], 28 => ['5:54', '15:01'], 29 => ['5:58', '15:08'],
                    30 => ['6:06', '15:05'], 31 => 'absent',
                ],
            ],

            // Gillaco, Francisco — Forklift Operator
            '25164' => [
                '2026-06' => [
                    1 => $pm13, 2 => $pm13, 3 => $pm13, 4 => $pm13, 5 => $pm13, 6 => ['5:40', '15:00'], 7 => 'day_off',
                    8 => $pm13, 9 => ['ao' => '6:00', 'out' => '15:00'], 10 => ['ao' => '5:40', 'out' => '15:00'],
                    11 => ['ao' => '5:50', 'out' => '15:00'], 12 => ['ao' => '5:50', 'out' => '15:00', 'remark' => 'Holiday'],
                    13 => ['5:50', '15:00'], 14 => 'day_off', 15 => $pm13, 16 => $pm13,
                    17 => ['pi' => '12:59', 'out' => '22:00'], 18 => $pm13, 19 => ['pi' => '12:40', 'out' => '22:00'],
                    20 => ['5:40', '15:00'], 21 => 'day_off', 22 => $pm13, 23 => ['6:00', '15:00'], 24 => ['5:40', '15:00'],
                    25 => ['6:00', '15:00'], 26 => ['6:00', '15:00'], 27 => ['ao' => '5:55', 'out' => '15:00'], 28 => 'day_off',
                    29 => $absentIr('IR PLS.'), 30 => ['note' => ['1:00', null], 'text' => '12:00(MIDNIGHT)'],
                ],
                '2026-07' => [
                    1 => $pm13, 2 => ['pi' => '12:55', 'out' => '22:00'], 3 => $pm13, 4 => ['5:50', '15:00'], 5 => 'day_off',
                    6 => ['pi' => '12:30', 'out' => '22:00'], 7 => ['pi' => '12:50', 'out' => '22:00'],
                    8 => ['pi' => '12:50', 'out' => '22:00'], 9 => ['pi' => '12:55', 'out' => '22:00'],
                    10 => ['pi' => '12:55', 'out' => '22:00'], 11 => ['pi' => '12:45', 'out' => '22:00'], 12 => 'day_off',
                    13 => $pm13, 14 => ['pi' => '12:50', 'out' => '22:00'], 15 => ['pi' => '12:40', 'out' => '22:00'],
                    16 => ['pi' => '5:47', 'out' => '15:00'], 17 => ['pi' => '5:48', 'out' => '15:00'],
                    18 => ['pi' => '5:55', 'out' => '15:00'], 19 => 'day_off',
                    // Written "1:00" under PM Arrival on a 13:00–22:00 day.
                    20 => ['pi' => '13:00', 'out' => '22:00'],
                    21 => ['5:43', '15:01'], 22 => ['5:43', '15:00'], 23 => ['in' => '5:30'], 24 => ['in' => '5:34'],
                    25 => ['in' => '5:44'], 26 => 'day_off', 27 => ['note' => ['5:51', null]],
                    28 => ['5:44', '15:13'], 29 => ['5:59', '15:02'], 30 => ['5:43', '15:01'], 31 => ['in' => '5:37'],
                ],
            ],

            // Coliat, Exequiel P. — Checker
            '25162' => [
                '2026-06' => [
                    1 => $pm13, 2 => $pm13, 3 => $pm13, 4 => $pm13, 5 => $pm13, 6 => ['pi' => '14:00', 'out' => '22:00'],
                    7 => 'day_off', 8 => $pm13, 9 => $pm13,
                    10 => ['6:00', '15:00'], 11 => ['6:00', '15:09'], 12 => ['6:00', '15:00', 'remark' => 'Holiday'],
                    13 => ['6:00', '15:00'], 14 => 'day_off',
                    15 => ['6:00', '15:30'], 16 => ['6:00', '15:05'], 17 => ['6:00', '15:34'], 18 => ['7:00', '16:00'],
                    19 => ['6:30', '22:00', 'text' => 'Straight duty'], 20 => $absentIr('IR PLS.'), 21 => 'day_off',
                    22 => ['6:00', '15:00'], 23 => ['6:00', '15:30'], 24 => ['6:00', '15:00'], 25 => ['7:00', '15:00'],
                    26 => ['6:30', '22:00', 'text' => 'Straight duty'], 27 => ['7:30', '20:00'], 28 => 'day_off',
                    29 => ['6:00', '15:00'], 30 => ['in' => '8:00', 'text' => 'Straight duty 12:00(MIDNIGHT)'],
                ],
                '2026-07' => [
                    1 => ['6:30', '15:09'], 2 => ['6:00', '15:00'], 3 => $pm13, 4 => ['13:00', '22:00'], 5 => 'day_off',
                    6 => ['12:15', '22:00'], 7 => $pm13, 8 => ['6:00', '22:00', 'text' => 'STRAIGHT DUTY'], 9 => 'absent',
                    10 => ['pi' => '12:58', 'out' => '22:00'], 11 => $pm13, 12 => 'day_off', 13 => $pm13,
                    14 => ['pi' => '12:50', 'out' => '22:00'], 15 => ['pi' => '12:40', 'out' => '22:00'], 16 => $pm13,
                    17 => ['7:20', '22:00'], 18 => 'absent', 19 => 'day_off', 20 => $pm13,
                    21 => ['pi' => '21:12', 'out' => '22:03'], 22 => ['10:04', '22:01'], 23 => ['9:29', '22:00'],
                    24 => ['pi' => '12:42', 'out' => '22:04'], 25 => ['note' => ['12:52', '22:37']], 26 => 'day_off',
                    27 => ['note' => ['12:59', '22:00']], 28 => ['12:55', '22:01'], 29 => ['13:03', '22:00'],
                    30 => ['13:21', '22:04'], 31 => ['in' => '12:46', 'note' => [null, '22:03']],
                ],
            ],

            // Mercado, Anthony — Yardman
            '25168' => [
                '2026-07' => [
                    1 => $pm12, 2 => ['11:50', '20:00'], 3 => $pm12, 4 => $pm12, 5 => 'day_off', 6 => $pm12,
                    7 => ['11:55', '20:00'], 8 => 'absent', 9 => $pm12, 10 => ['7:00', '20:00', 'text' => 'cctv'],
                    11 => $pm12, 12 => 'day_off', 13 => 'absent', 14 => 'absent',
                    15 => ['12:00', '20:00'], 16 => ['12:00', '20:00'], 17 => ['12:00', '20:00'],
                    // Written "6:30 … 8:00 (straight duty)": the 8:00 is kept outside the form until confirmed.
                    18 => ['in' => '6:30', 'note' => [null, '8:00'], 'text' => '(straight duty)'],
                    19 => 'day_off', 20 => $pm12, 21 => ['pi' => '12:05', 'out' => '20:05'], 22 => ['11:53', '20:03'],
                    // Departure written as "30:13:00", which isn't a time.
                    23 => ['pi' => '12:00', 'text' => '30:13:00'],
                    24 => ['11:58', '21:06'], 25 => ['11:57', '20:02'], 26 => 'day_off', 27 => 'absent',
                    28 => ['11:46', '20:08'], 29 => 'absent', 30 => 'absent', 31 => 'absent',
                ],
                '2026-08' => [
                    1 => 'awol', 2 => 'day_off', 3 => 'awol', 4 => 'awol', 5 => 'awol', 6 => 'awol', 7 => 'awol',
                    8 => 'awol', 9 => 'day_off', 10 => 'awol', 11 => 'awol', 12 => 'awol',
                ],
            ],

            // Plata, Joel — Checker/OFD, started Sept 19, 2026
            'BAT-001' => [
                '2026-09' => [
                    19 => ['6:20', 'ao' => '12:00', 'pi' => '13:00', 'out' => '17:15', 'text' => 'San Pablo SO'],
                    20 => 'day_off',
                    21 => ['6:10', 'ao' => '12:00', 'pi' => '12:50', 'out' => '15:08'],
                    22 => ['5:50', 'ao' => '12:00', 'pi' => '12:49', 'out' => '15:23'],
                    23 => ['6:06', 'ao' => '12:01', 'pi' => '12:54', 'out' => '15:08'],
                    24 => ['5:56', 'ao' => '12:02', 'pi' => '12:54', 'out' => '15:02'],
                    25 => ['6:06', 'ao' => '12:00', 'pi' => '12:54', 'out' => '15:02'],
                    26 => ['6:12', 'ao' => '12:00', 'pi' => '12:57', 'out' => '15:12'],
                    27 => 'day_off',
                    28 => ['6:13', '15:15'], 29 => ['6:10', '15:11'], 30 => ['5:49', '15:00'],
                ],
            ],
        ];
    }

    public function run(): void
    {
        $encoderId = User::query()->orderBy('id')->value('id');
        $resolver = app(AttendanceResolver::class);

        foreach (self::sheets() as $code => $months) {
            $employee = Employee::query()->where('employee_code', (string) $code)->first();

            if (! $employee) {
                continue;
            }

            foreach ($months as $month => $days) {
                foreach ($days as $dayNumber => $entry) {
                    $date = Carbon::parse(sprintf('%s-%02d', $month, $dayNumber));

                    $attributes = [
                        ...$this->parse($entry),
                        'status' => 'approved',
                        'approved_by' => $encoderId,
                        'encoded_by' => $encoderId,
                    ];

                    $log = DtrLog::query()
                        ->where('employee_id', $employee->id)
                        ->whereDate('work_date', $date->toDateString())
                        ->first();

                    $log
                        ? $log->update($attributes)
                        : DtrLog::query()->create(['employee_id' => $employee->id, 'work_date' => $date->toDateString(), ...$attributes]);

                    $resolver->resolveDay($employee, $date, Holiday::findForDate($date));
                }
            }
        }
    }

    /**
     * @param  string|array<int|string, mixed>  $entry
     * @return array<string, string|null>
     */
    protected function parse(string|array $entry): array
    {
        if (is_string($entry)) {
            return [
                'day_status' => $entry,
                'time_in' => null, 'break_out' => null, 'break_in' => null, 'time_out' => null,
                'note_time_in' => null, 'note_time_out' => null, 'note_text' => null, 'reason' => null,
            ];
        }

        $note = $entry['note'] ?? [null, null];

        return [
            'day_status' => $entry['status'] ?? 'worked',
            'time_in' => $this->time($entry['in'] ?? $entry[0] ?? null),
            'break_out' => $this->time($entry['ao'] ?? null),
            'break_in' => $this->time($entry['pi'] ?? null),
            'time_out' => $this->time($entry['out'] ?? $entry[1] ?? null),
            'note_time_in' => $this->time($note[0] ?? null),
            'note_time_out' => $this->time($note[1] ?? null),
            'note_text' => $entry['text'] ?? null,
            'reason' => $entry['remark'] ?? null,
        ];
    }

    /** "6:45" → "06:45:00". */
    protected function time(?string $value): ?string
    {
        return $value === null ? null : str_pad($value, 5, '0', STR_PAD_LEFT).':00';
    }
}
