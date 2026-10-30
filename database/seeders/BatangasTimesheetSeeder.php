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
 * Lasala, Gian Paulo's DAILY TIME RECORD tab from the client's "Batangas
 * time sheet WHPEP" workbook, Aug–Nov 2026 — including the times jotted
 * outside the form beside a blank day (e.g. Oct 1: 6:54 / 16:06), which are
 * kept as outside notes rather than paid.
 */
class BatangasTimesheetSeeder extends Seeder
{
    /**
     * Each day: [AM arrival, PM departure], "day_off", or ['note' => [arrival, departure]]
     * for a day left blank inside the form with times noted outside it. A
     * worked day may also carry 'note'.
     *
     * @var array<string, array<int, string|array<int|string, mixed>>>
     */
    protected const LASALA_DTR = [
        '2026-08' => [
            1 => ['6:48', '19:17'],
            2 => 'day_off',
            3 => ['6:55', '20:00'], 4 => ['6:53', '19:19'], 5 => ['6:56', '18:22'], 6 => ['6:51', '19:02'],
            7 => ['6:54', '18:01'], 8 => ['6:52', '18:20'],
            9 => 'day_off',
            10 => ['6:56', '18:22'], 11 => ['7:05', '18:21'], 12 => ['6:56', '18:00'], 13 => ['6:56', '18:00'],
            14 => ['6:54', '18:00'], 15 => ['6:49', '19:01'],
            16 => 'day_off',
            17 => ['6:55', '20:00'], 18 => ['7:00', '17:00'],
            19 => ['note' => ['6:48', '19:01']],
            20 => ['6:47', '18:04'], 21 => ['6:48', '19:01'], 22 => ['6:47', '19:14'],
            24 => ['6:56', '19:00'], 25 => ['6:57', '20:03'],
            26 => ['note' => ['6:55', '20:29']],
            27 => ['6:54', '20:00'], 28 => ['6:51', '18:00', 'note' => [null, '18:00']], 29 => ['6:53', '18:00'],
            30 => 'day_off',
            31 => ['6:41', '20:34'],
        ],
        '2026-09' => [
            1 => ['6:53', '19:16'], 2 => ['6:45', '18:28'], 3 => ['6:54', '20:00'], 4 => ['6:52', '19:02'],
            5 => ['6:52', '19:10'],
            6 => 'day_off',
            7 => ['6:58', '19:13'], 8 => ['6:55', '19:00'], 9 => ['6:56', '19:32'], 10 => ['6:49', '19:00'],
            11 => ['6:46', '19:05'], 12 => ['6:07', '19:01'],
            13 => 'day_off',
            14 => ['6:52', null],
            16 => ['6:52', '19:04'], 17 => ['6:59', '20:00'], 18 => ['6:51', '19:24'], 19 => ['6:57', '19:21'],
            20 => 'day_off',
            21 => ['6:58', '19:02'], 22 => ['6:53', '20:00'], 23 => ['6:57', '20:00'], 24 => ['6:59', '20:01'],
            25 => ['7:17', '18:39'], 26 => ['6:58', '20:01'],
            27 => 'day_off',
            28 => ['6:56', '20:04'], 29 => ['6:58', '20:00'], 30 => ['6:59', '21:02'],
        ],
        '2026-10' => [
            1 => ['note' => ['6:54', '16:06']],
            2 => ['note' => ['6:56', '16:03']],
            3 => ['6:50', '16:15'],
            4 => 'day_off',
            5 => ['6:54', '16:11'], 6 => ['6:58', '16:13'], 7 => ['6:59', '16:00'], 8 => ['6:57', '16:19'],
            9 => ['6:54', '16:22'], 10 => ['6:56', '16:06'],
            11 => 'day_off',
            12 => ['7:09', '15:59'], 13 => ['6:45', '16:03'], 14 => ['6:57', '16:04'], 15 => ['6:52', '16:01'],
            16 => ['6:50', '16:01'], 17 => ['6:48', '16:00'],
            18 => 'day_off',
            19 => ['7:03', '16:04'], 20 => ['6:56', '16:00'], 21 => ['6:56', '16:00'],
            25 => 'day_off',
        ],
        // November is still ahead: only the day offs are marked.
        '2026-11' => [1 => 'day_off', 8 => 'day_off', 15 => 'day_off', 22 => 'day_off'],
    ];

    public function run(): void
    {
        $employee = Employee::query()->where('employee_code', '25167')->first();

        if (! $employee) {
            return;
        }

        $encoderId = User::query()->orderBy('id')->value('id');
        $resolver = app(AttendanceResolver::class);

        foreach (self::LASALA_DTR as $month => $days) {
            foreach ($days as $dayNumber => $entry) {
                $date = Carbon::parse(sprintf('%s-%02d', $month, $dayNumber));
                $note = is_array($entry) ? ($entry['note'] ?? [null, null]) : [null, null];

                $attributes = [
                    'time_in' => is_array($entry) ? $this->time($entry[0] ?? null) : null,
                    'time_out' => is_array($entry) ? $this->time($entry[1] ?? null) : null,
                    'note_time_in' => $this->time($note[0]),
                    'note_time_out' => $this->time($note[1]),
                    'day_status' => $entry === 'day_off' ? 'day_off' : 'worked',
                    'reason' => null,
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

    /** "6:45" → "06:45:00". */
    protected function time(?string $value): ?string
    {
        return $value === null ? null : str_pad($value, 5, '0', STR_PAD_LEFT).':00';
    }
}
