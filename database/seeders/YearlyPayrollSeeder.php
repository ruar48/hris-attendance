<?php

namespace Database\Seeders;

use App\Models\BiometricDevice;
use App\Models\BiometricLog;
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
     * Seed 2024 kinsenas payroll: 24 cutoffs (1–15 and 16–end per month).
     * 13th month is issued as its own payslip after December 2nd kinsena.
     */
    public function run(): void
    {
        PayrollSetting::setValue('auto_13th_month', '1');
        PayrollSetting::setValue('payroll_frequency', 'kinsenas');

        $device = BiometricDevice::query()->where('serial_number', 'ZK-FP-001')->first();
        $demoStaff = Employee::query()
            ->whereIn('employee_code', ['EMP-0001', 'EMP-0002', 'EMP-0003', 'EMP-0004', 'EMP-0005'])
            ->orderBy('employee_code')
            ->get();

        $adminId = Employee::query()->where('employee_code', 'EMP-0001')->value('user_id');
        $calculator = app(PayrollCalculator::class);
        $resolver = app(AttendanceResolver::class);

        for ($month = 1; $month <= 12; $month++) {
            foreach ([1, 2] as $half) {
                [$start, $end, $name] = $this->periodWindow(2024, $month, $half);

                $period = PayrollPeriod::query()->updateOrCreate(
                    [
                        'year' => 2024,
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

                $this->seedPeriodAttendance($demoStaff, $device, $start, $end, $month, $half, $adminId);
                $this->resolvePeriodAttendance($resolver, $demoStaff, $start, $end);
                $calculator->run($period, syncAttendance: false);

                $note = ($month === 12 && $half === 2) ? ' (+ separate 13th month payslip)' : '';
                $this->command?->info("Seeded {$name}{$note}");
            }
        }

        SystemNotification::query()->create([
            'title' => 'Full year 2024 kinsenas payroll ready',
            'message' => '24 cutoffs seeded (1–15 and 16–end). 13th month is a separate payslip after December 2nd kinsena.',
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
        ?BiometricDevice $device,
        Carbon $start,
        Carbon $end,
        int $month,
        int $half,
        mixed $adminId
    ): void {
        $rows = [];
        $now = now();

        foreach ($employees as $index => $employee) {
            foreach (CarbonPeriod::create($start, $end) as $day) {
                $day = Carbon::parse($day);

                if ($day->isSaturday()) {
                    continue;
                }

                $late = $index === 0 && $day->day === 3;
                $ot = $index === 0 && in_array($day->day, [6, 7, 8], true);
                $undertime = $index === 1 && $day->day === 4;

                $rows[] = [
                    'employee_id' => $employee->id,
                    'biometric_device_id' => $device?->id,
                    'punched_at' => $day->copy()->setTime(8, $late ? 25 : ($index + $day->day) % 5),
                    'punch_type' => 'in',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                $rows[] = [
                    'employee_id' => $employee->id,
                    'biometric_device_id' => $device?->id,
                    'punched_at' => $day->copy()->setTime(
                        $undertime ? 16 : 17,
                        $ot ? 45 : ($index + $day->day) % 12
                    ),
                    'punch_type' => 'out',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            BiometricLog::query()->insert($chunk);
        }

        if ($month === 5 && $half === 1) {
            $pedro = $employees->firstWhere('employee_code', 'EMP-0003');
            if ($pedro) {
                BiometricLog::query()
                    ->where('employee_id', $pedro->id)
                    ->whereDate('punched_at', '2024-05-10')
                    ->delete();

                DtrLog::query()->updateOrCreate(
                    [
                        'employee_id' => $pedro->id,
                        'work_date' => '2024-05-10',
                    ],
                    [
                        'time_in' => '08:05:00',
                        'time_out' => '17:10:00',
                        'reason' => 'Fingerprint scanner offline at warehouse',
                        'status' => 'approved',
                        'approved_by' => $adminId,
                    ]
                );
            }
        }
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
                if ($day->isSaturday()) {
                    continue;
                }

                $resolver->resolveDay($employee, $day, Holiday::findForDate($day));
            }
        }
    }
}
