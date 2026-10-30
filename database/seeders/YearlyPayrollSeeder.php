<?php

namespace Database\Seeders;

use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollPeriod;
use App\Models\PayrollSetting;
use App\Models\SystemNotification;
use App\Services\AttendanceResolver;
use App\Services\PayrollCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class YearlyPayrollSeeder extends Seeder
{
    /**
     * Years to seed: month => [from, to] inclusive.
     *
     * @var array<int, array{0: int, 1: int}>
     */
    protected array $schedules = [
        ClientDemoSeeder::DEMO_YEAR => [1, 12],
        ClientDemoSeeder::DEMO_YEAR_CURRENT => [1, 6],
    ];

    public function run(): void
    {
        PayrollSetting::setValue('auto_13th_month', '1');
        PayrollSetting::setValue('payroll_frequency', 'kinsenas');

        $employees = Employee::query()
            ->whereIn('employee_code', ClientDemoSeeder::employeeCodes())
            ->orderBy('employee_code')
            ->get();

        $adminId = Employee::query()->where('employee_code', 'EMP-0001')->value('user_id');
        $calculator = app(PayrollCalculator::class);
        $resolver = app(AttendanceResolver::class);

        foreach ($this->schedules as $year => [$fromMonth, $toMonth]) {
            for ($month = $fromMonth; $month <= $toMonth; $month++) {
                foreach ([1, 2] as $half) {
                    [$start, $end, $name] = $this->periodWindow($year, $month, $half);

                    $period = PayrollPeriod::query()->updateOrCreate(
                        [
                            'year' => $year,
                            'month' => $month,
                            'half' => $half,
                        ],
                        [
                            'name' => $name,
                            'start_date' => $start->toDateString(),
                            'end_date' => $end->toDateString(),
                            'cutoff_date' => $end->toDateString(),
                            'process_start' => $end->copy()->addDay()->toDateString(),
                            'process_end' => $end->copy()->addDays(3)->toDateString(),
                            'payslip_release' => $end->copy()->addDays(4)->toDateString(),
                            'payday' => $end->copy()->addDays(5)->toDateString(),
                            'status' => 'open',
                        ]
                    );

                    $this->seedPeriodAttendance(
                        $employees,
                        $start,
                        $end,
                        $month,
                        $half,
                        $adminId,
                        $year
                    );
                    $this->resolvePeriodAttendance($resolver, $employees, $start, $end);
                    $calculator->run($period, syncAttendance: false);

                    $note = match (true) {
                        $month === 6 && $half === 2 => ' (+ 13th month 1st half)',
                        $month === 12 && $half === 2 => ' (+ 13th month 2nd half)',
                        default => '',
                    };
                    $this->command?->info("{$year} · Seeded {$name}{$note}");
                }
            }
        }

        SystemNotification::query()->create([
            'title' => 'Payroll demo dataset generated',
            'message' => sprintf(
                '%d employees · %d full year + %d YTD kinsenas · 13th month split June & December · Statutory deductions on regular pay only',
                $employees->count(),
                24,
                12
            ),
            'type' => 'success',
        ]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    protected function periodWindow(int $year, int $month, int $half): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $label = $monthStart->format('F Y');

        if ($half === 1) {
            $start = $monthStart->copy();
            $end = $monthStart->copy()->day(15);

            return [$start, $end, "{$label} · 1st Kinsena (1–15)"];
        }

        $start = $monthStart->copy()->day(16);
        $end = $monthEnd->copy();

        return [$start, $end, "{$label} · 2nd Kinsena (16–end)"];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    protected function seedPeriodAttendance(
        Collection $employees,
        Carbon $start,
        Carbon $end,
        int $month,
        int $half,
        mixed $adminId,
        int $year
    ): void {
        $rows = [];
        $now = now();

        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $day = Carbon::parse($day);

                if ($this->skipsWorkDay($employee, $day)) {
                    continue;
                }

                [$inHour, $inMin, $outHour, $outMin] = $this->punchTimes($employee, $day);

                $rows[] = [
                    'employee_id' => $employee->id,
                    'work_date' => $day->toDateString(),
                    'time_in' => sprintf('%02d:%02d:00', $inHour, $inMin),
                    'time_out' => sprintf('%02d:%02d:00', $outHour, $outMin),
                    'day_status' => 'worked',
                    'reason' => null,
                    'status' => 'approved',
                    'approved_by' => $adminId,
                    'encoded_by' => $adminId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DtrLog::query()->upsert(
                $chunk,
                ['employee_id', 'work_date'],
                ['time_in', 'time_out', 'day_status', 'reason', 'status', 'approved_by', 'encoded_by', 'updated_at']
            );
        }

        if ($month === 5 && $half === 1) {
            $pedro = $employees->firstWhere('employee_code', 'EMP-0003');
            if ($pedro) {
                DtrLog::query()->upsert(
                    [[
                        'employee_id' => $pedro->id,
                        'work_date' => "{$year}-05-10",
                        'time_in' => '08:05:00',
                        'time_out' => '17:10:00',
                        'day_status' => 'worked',
                        'reason' => 'Corrected from paper DTR',
                        'status' => 'approved',
                        'approved_by' => $adminId,
                        'encoded_by' => $adminId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]],
                    ['employee_id', 'work_date'],
                    ['time_in', 'time_out', 'reason', 'updated_at']
                );
            }
        }
    }

    protected function skipsWorkDay(Employee $employee, Carbon $day): bool
    {
        // Mon–Sat are working days; only Sunday is the rest day (except Carlo's route).
        if ($day->isSunday() && $employee->employee_code !== 'EMP-0005') {
            return true;
        }

        // Sofia — unexcused absence demo (Aug 12).
        if ($employee->employee_code === 'EMP-0010' && (int) $day->month === 8 && (int) $day->day === 12) {
            return true;
        }

        return false;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int} in hour, in min, out hour, out min
     */
    protected function punchTimes(Employee $employee, Carbon $day): array
    {
        $code = $employee->employee_code;
        $dom = (int) $day->day;
        $month = (int) $day->month;

        // Juan — late on the 3rd, OT on 6–8.
        if ($code === 'EMP-0001') {
            if ($dom === 3) {
                return [8, 25, 17, 0];
            }
            if (in_array($dom, [6, 7, 8], true)) {
                return [8, 0, 18, 5];
            }
        }

        // Maria — undertime on the 4th.
        if ($code === 'EMP-0002' && $dom === 4) {
            return [8, 0, 16, 0];
        }

        // Rico — heavy OT in March.
        if ($code === 'EMP-0011' && $month === 3 && in_array($dom, [10, 11, 12], true)) {
            return [8, 0, 18, 30];
        }

        return [8, 0, 17, 0];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     */
    protected function resolvePeriodAttendance(
        AttendanceResolver $resolver,
        Collection $employees,
        Carbon $start,
        Carbon $end
    ): void {
        foreach ($employees as $employee) {
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $day = Carbon::parse($day);

                if ($this->skipsWorkDay($employee, $day)) {
                    continue;
                }

                $resolver->resolveDay($employee, $day, Holiday::findForDate($day));
            }
        }
    }
}
