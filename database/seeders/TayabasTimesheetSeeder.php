<?php

namespace Database\Seeders;

use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionList;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Models\User;
use App\Services\AttendanceResolver;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

/**
 * Attendance transcribed from the client's "Tayabas time sheet WHPEP"
 * workbook: the SHEDULE tab (Dec 15–31, 2025) and Don Jeric Del Pilar's
 * DAILY TIME RECORD tab (Jul–Oct 2025). Runs after the employee seeders,
 * which create the people it refers to by employee code.
 */
class TayabasTimesheetSeeder extends Seeder
{
    /** Shift codes as used on the SHEDULE tab. */
    protected const SHIFTS = [
        ['code' => 1, 'name' => 'Morning (OPA)', 'time_in' => '07:00:00', 'time_out' => '16:00:00', 'is_rest_day' => false],
        ['code' => 2, 'name' => 'Early', 'time_in' => '06:00:00', 'time_out' => '15:00:00', 'is_rest_day' => false],
        ['code' => 3, 'name' => 'Mid', 'time_in' => '11:00:00', 'time_out' => '20:00:00', 'is_rest_day' => false],
        ['code' => 4, 'name' => 'Office', 'time_in' => '08:00:00', 'time_out' => '17:00:00', 'is_rest_day' => false],
        ['code' => 9, 'name' => 'Day Off', 'time_in' => null, 'time_out' => null, 'is_rest_day' => true],
    ];

    /**
     * SHEDULE tab, Dec 15–31 2025: each person's shift code and the Sundays
     * they worked (everyone else is off on Sunday).
     *
     * @var array<string, array{shift: int, sundays: list<string>}>
     */
    protected const DECEMBER_SCHEDULE = [
        '25169' => ['shift' => 1, 'sundays' => ['2025-12-21', '2025-12-28']], // Don Jeric Del Pilar, OPA
        '25170' => ['shift' => 2, 'sundays' => ['2025-12-21']],               // John Armel Salandanan, Checker
        '25171' => ['shift' => 3, 'sundays' => ['2025-12-28']],               // John Eric Delas Alas, Checker
        '25173' => ['shift' => 3, 'sundays' => []],                           // Romnick Delos Reyes, Forklift
        '25172' => ['shift' => 2, 'sundays' => ['2025-12-21', '2025-12-28']], // Rodel Delas Alas, Forklift
        '25175' => ['shift' => 3, 'sundays' => ['2025-12-28']],               // Glynn Hernan, Yardman
    ];

    /**
     * Don Jeric Del Pilar's DTR. Each day is [AM arrival, PM departure,
     * remarks], or a status word for a day not worked.
     *
     * @var array<string, array<int, string|list<string>>>
     */
    protected const DEL_PILAR_DTR = [
        '2025-07' => [
            1 => ['6:45', '18:00'], 2 => ['6:52', '18:00'], 3 => ['6:59', '18:00'], 4 => ['6:59', '18:00'],
            5 => 'day_off',
            6 => ['6:53', '18:00'], 7 => ['7:00', '18:00'], 8 => ['7:00', '18:00'], 9 => ['6:51', '18:00'],
            10 => ['6:54', '18:00'], 11 => ['6:59', '18:00'],
            12 => 'day_off',
            13 => ['6:54', '18:00'], 14 => ['6:54', '18:00'], 15 => ['6:50', '18:00'], 16 => ['6:35', '18:01'],
            17 => ['6:36', '18:01'], 18 => ['6:20', '18:02', '06:20AM-18:02'],
            19 => 'day_off',
            20 => ['6:38', '18:05'], 21 => ['6:33', '18:02'], 22 => ['6:35', '18:01'], 23 => ['6:30', '18:00'],
            24 => ['6:31', '18:00'], 25 => ['6:32', '20:00'],
            26 => 'day_off',
            27 => ['6:32', '18:00'], 28 => ['6:38', '18:45'], 29 => ['6:33', '18:02'], 30 => ['6:38', '17:27'],
            31 => ['6:40', '18:01'],
        ],
        '2025-08' => [
            1 => ['6:40', '17:41'],
            2 => 'day_off',
            3 => ['6:31', '18:17'], 4 => ['6:37', '18:00'], 5 => ['6:35', '18:00'], 6 => ['6:34', '18:00'],
            7 => ['6:46', '18:00'], 8 => ['6:51', '18:00'],
            9 => 'day_off',
            10 => ['6:32', '18:00'], 11 => ['6:34', '18:00'], 12 => ['6:40', '18:13'], 13 => ['6:43', '18:00'],
            14 => ['6:42', '18:00'], 15 => ['6:36', '18:00'],
            16 => 'day_off',
            17 => ['6:31', '19:23', 'monthly Inventory'], 18 => ['6:35', '18:01'], 19 => ['6:40', '18:01', 'Holiday'],
            20 => ['6:34', '18:01'], 21 => ['6:37', '18:01', 'Holiday'], 22 => ['6:36', '18:01'],
            23 => 'day_off',
            24 => ['6:35', '18:01'], 25 => ['6:42', '18:01'], 26 => ['6:31', '18:06'], 27 => ['6:34', '18:01'],
            28 => ['6:34', '18:01'], 29 => ['6:45', '18:01'],
            30 => 'day_off',
            31 => ['6:40', '20:06', 'Regular Holiday'],
        ],
        '2025-09' => [
            1 => ['6:41', '18:30'], 2 => ['6:35', '19:25'], 3 => ['6:37', '18:01'], 4 => ['6:40', '18:06', '06:40am-18:06pm'],
            5 => ['6:36', '18:01'],
            6 => 'day_off',
            7 => ['5:58', '18:01'], 8 => ['6:23', '18:01'], 9 => ['6:40', '18:28'], 10 => ['6:39', '18:01'],
            11 => ['6:46', '18:40'], 12 => ['6:36', '18:01'],
            13 => 'day_off',
            14 => ['6:42', '19:01'], 15 => ['6:50', '18:11'], 16 => ['6:40', '18:01'], 17 => ['6:45', '18:01'],
            18 => ['6:46', '18:01'],
            19 => ['absent', "Absent due to Daughter's Birthday"],
            20 => 'day_off',
            21 => ['6:58', '18:01'], 22 => ['6:47', '18:00'], 23 => ['6:45', '18:01'], 24 => ['6:56', '18:05'],
            25 => ['6:27', '18:02'], 26 => ['6:47', '18:02'],
            27 => 'day_off',
            28 => ['6:45', '18:01'], 29 => ['6:50', '18:01', 'Holiday Fiesta'], 30 => ['6:47', '21:00', 'month end sales'],
        ],
        '2025-10' => [
            1 => ['6:21', '18:00'], 2 => ['6:45', '18:01'], 3 => ['6:48', '18:01'],
            4 => 'day_off',
            5 => ['6:45', '18:01'], 6 => ['6:48', '18:02'], 7 => ['6:50', '18:02'], 8 => ['6:55', '18:02'],
            9 => ['6:38', '18:02'], 10 => ['6:48', '18:02'],
            11 => 'day_off',
            12 => ['6:40', '18:02'], 13 => ['6:45', '18:01'], 14 => ['6:35', '18:02'], 15 => ['6:35', '18:02'],
            16 => ['5:44', '18:01'], 17 => ['6:57', '18:01'],
            18 => 'day_off',
            19 => ['6:48', '19:01', 'PID'], 20 => ['6:58', '18:01'], 21 => ['6:35', '18:01'], 22 => ['6:45', '18:01'],
            23 => ['6:47', '18:02'], 24 => ['6:43', '18:01'],
            25 => 'day_off',
            26 => ['6:48', '18:01'], 27 => ['6:39', '18:01'], 28 => ['6:49', '18:01'], 29 => ['6:38', '18:02'],
            30 => ['6:45', '18:02'],
        ],
    ];

    /**
     * The Tayabas Manning tab: who works at the site and their regular
     * schedule time (shift_schedule option values).
     *
     * @var array<string, string>
     */
    protected const MANNING = [
        '25169' => '0700-1600', // Don Jeric Del Pilar, OPA
        '25170' => '0600-1500', // John Armel Salandanan, Checker
        '25171' => '1100-2000', // John Eric Delas Alas, Checker
        '25172' => '0600-1500', // Rodel Delas Alas, Forklift
        '25173' => '1100-2000', // Romnick Delos Reyes, Forklift
        '25174' => '0600-1500', // Susan Gueriba, Yard/Utility
        '25175' => '1100-2000', // Glynn Hernan, Yardman
    ];

    public function run(): void
    {
        $shiftOptions = OptionList::query()->category('shift_schedule')->get()->keyBy('value');

        foreach (self::MANNING as $code => $shift) {
            Employee::query()->where('employee_code', (string) $code)->update([
                'work_location' => 'Tayabas',
                'shift_schedule' => $shift,
                'time_in_schedule' => $shiftOptions->get($shift)?->time_in,
                'time_out_schedule' => $shiftOptions->get($shift)?->time_out,
            ]);
        }

        $shifts = collect(self::SHIFTS)->mapWithKeys(fn (array $shift) => [
            $shift['code'] => ShiftType::query()->updateOrCreate(['code' => $shift['code']], $shift),
        ]);

        $this->seedDecemberSchedule($shifts->all());
        $this->seedDelPilarDtr();
    }

    /**
     * @param  array<int, ShiftType>  $shifts
     */
    protected function seedDecemberSchedule(array $shifts): void
    {
        $employees = Employee::query()
            ->whereIn('employee_code', array_map('strval', array_keys(self::DECEMBER_SCHEDULE)))
            ->get()
            ->keyBy('employee_code');

        foreach (self::DECEMBER_SCHEDULE as $code => $plan) {
            $employee = $employees->get($code);

            if (! $employee) {
                continue;
            }

            foreach (CarbonPeriod::create('2025-12-15', '2025-12-31') as $day) {
                $date = $day->toDateString();
                $off = $date === '2025-12-25' || ($day->isSunday() && ! in_array($date, $plan['sundays'], true));

                Schedule::query()->updateOrCreate(
                    ['employee_id' => $employee->id, 'work_date' => $date],
                    [
                        'shift_type_id' => $shifts[$off ? 9 : $plan['shift']]->id,
                        'status' => 'working',
                        'notes' => match (true) {
                            $date === '2025-12-25' => 'Christmas Day - Regular Holiday',
                            $date === '2025-12-24' => 'Christmas Eve - Holiday',
                            $day->isSunday() && ! $off => 'Sunday Restday/Overtime',
                            default => null,
                        },
                    ]
                );
            }
        }
    }

    protected function seedDelPilarDtr(): void
    {
        $employee = Employee::query()->where('employee_code', '25169')->first();

        if (! $employee) {
            return;
        }

        $encoderId = User::query()->orderBy('id')->value('id');
        $resolver = app(AttendanceResolver::class);

        foreach (self::DEL_PILAR_DTR as $month => $days) {
            foreach ($days as $dayNumber => $entry) {
                $date = Carbon::parse(sprintf('%s-%02d', $month, $dayNumber));

                [$status, $in, $out, $remarks] = match (true) {
                    is_string($entry) => [$entry, null, null, null],
                    $entry[0] === 'absent' => ['absent', null, null, $entry[1]],
                    default => ['worked', $this->time($entry[0]), $this->time($entry[1]), $entry[2] ?? null],
                };

                $attributes = [
                    'time_in' => $in,
                    'time_out' => $out,
                    'day_status' => $status,
                    'reason' => $remarks,
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
    protected function time(string $value): string
    {
        return str_pad($value, 5, '0', STR_PAD_LEFT).':00';
    }
}
