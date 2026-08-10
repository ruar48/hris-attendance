<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Services\AttendancePeriodSummary;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class AttendanceSummaryController extends Controller
{
    protected const STANDARD_HOURS_PER_DAY = 8;

    public function index(Request $request): Response
    {
        $start = $request->date('start_date') ?? Carbon::today()->subDays(13);
        $end = $request->date('end_date') ?? Carbon::today();

        $rows = Employee::query()
            ->active()
            ->orderBy('employee_code')
            ->get()
            ->map(function (Employee $employee) use ($start, $end) {
                // whereBetween with plain "Y-m-d" strings would silently miss
                // the range's last day: work_date is cast to `date`, which
                // Eloquent stores as "Y-m-d 00:00:00", and that string sorts
                // *after* the bare "Y-m-d" upper bound lexicographically.
                $attendance = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', '>=', $start->toDateString())
                    ->whereDate('work_date', '<=', $end->toDateString())
                    ->get();

                $summary = AttendancePeriodSummary::build($employee, $start, $end, $attendance);

                $workedMinutes = (int) $attendance->sum('worked_minutes');
                $lateMinutes = (int) $attendance->sum('late_minutes');
                $lateFrequency = $attendance->where('late_minutes', '>', 0)->count();
                $earlyMinutes = (int) $attendance->sum('undertime_minutes');
                $earlyFrequency = $attendance->where('undertime_minutes', '>', 0)->count();
                $otMinutes = (int) $attendance->sum('ot_minutes');

                $holidayPay = (float) $attendance->sum('holiday_pay');
                $sundayPay = (float) $attendance->sum('sunday_pay');
                $otPay = (float) $attendance->sum('ot_pay');
                $lateDeduction = (float) $attendance->sum('late_deduction');
                $undertimeDeduction = (float) $attendance->sum('undertime_deduction');

                $absenceDeduction = $summary['scheduled'] > 0
                    ? round((float) $employee->daily_rate * $summary['absent'], 2)
                    : 0.0;

                $realPay = round(
                    $summary['worked'] * (float) $employee->daily_rate
                    + $holidayPay + $sundayPay + $otPay
                    - $lateDeduction - $undertimeDeduction,
                    2
                );

                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->full_name,
                    'department' => $employee->department,
                    'standard_hours' => round($summary['scheduled'] * self::STANDARD_HOURS_PER_DAY, 2),
                    'actual_hours' => round($workedMinutes / 60, 2),
                    'late_frequency' => $lateFrequency,
                    'late_minutes' => $lateMinutes,
                    'early_frequency' => $earlyFrequency,
                    'early_minutes' => $earlyMinutes,
                    'overtime_hours' => round($otMinutes / 60, 2),
                    'attend_standard' => $summary['scheduled'],
                    'attend_actual' => $summary['worked'],
                    'absences' => $summary['absent'],
                    'leave' => $summary['leave'],
                    'business_trip' => $summary['businessTrip'],
                    'overtime_pay' => round($otPay, 2),
                    'time_deduction' => round($lateDeduction + $undertimeDeduction, 2),
                    'absence_deduction' => $absenceDeduction,
                    'real_pay' => $realPay,
                ];
            });

        return Inertia::render('attendance-summary/index', [
            'rows' => $rows,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    public function abnormal(Request $request): Response
    {
        $start = $request->date('start_date') ?? Carbon::today()->subDays(29);
        $end = $request->date('end_date') ?? Carbon::today();

        $records = AttendanceRecord::query()
            ->with('employee')
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->where(function ($query) {
                $query->where('is_incomplete', true)
                    ->orWhere('late_minutes', '>', 0)
                    ->orWhere('undertime_minutes', '>', 0);
            })
            ->get()
            ->filter(fn (AttendanceRecord $record) => $record->employee !== null)
            ->sortBy([
                fn ($record) => $record->employee->employee_code,
                fn ($record) => $record->work_date,
            ])
            ->values()
            ->map(function (AttendanceRecord $record) {
                $lateMinutes = (int) $record->late_minutes;
                $earlyMinutes = (int) $record->undertime_minutes;

                $remarks = [];
                if (! $record->time_in || ! $record->time_out) {
                    $remarks[] = 'Missing punch';
                }
                if ($lateMinutes > 0) {
                    $remarks[] = 'Late';
                }
                if ($earlyMinutes > 0) {
                    $remarks[] = 'Undertime';
                }

                return [
                    'id' => $record->id,
                    'employee_code' => $record->employee->employee_code,
                    'name' => $record->employee->full_name,
                    'department' => $record->employee->department,
                    'work_date' => $record->work_date->toDateString(),
                    'time_in' => $record->time_in,
                    'time_out' => $record->time_out,
                    'late_minutes' => $lateMinutes,
                    'early_minutes' => $earlyMinutes,
                    'total_minutes' => $lateMinutes + $earlyMinutes,
                    'remark' => implode(', ', $remarks),
                ];
            })
            ->values();

        return Inertia::render('attendance-summary/abnormal', [
            'rows' => $records,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
        ]);
    }

    public function report(Request $request): Response
    {
        $start = $request->date('start_date') ?? Carbon::today()->subDays(19);
        $end = $request->date('end_date') ?? Carbon::today();
        $search = $request->string('search')->toString();

        $employees = $this->buildReportRows($start, $end, $search);

        return Inertia::render('attendance-summary/report', [
            'employees' => $employees,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function reportPdf(Request $request): SymfonyResponse
    {
        $start = $request->date('start_date') ?? Carbon::today()->subDays(19);
        $end = $request->date('end_date') ?? Carbon::today();
        $search = $request->string('search')->toString();

        $employees = $this->buildReportRows($start, $end, $search);

        $filename = sprintf(
            'attendance-report-%s-to-%s.pdf',
            $start->toDateString(),
            $end->toDateString()
        );

        return Pdf::loadView('attendance-report.pdf', [
            'employees' => $employees,
            'start' => $start,
            'end' => $end,
        ])
            ->setPaper('a4', 'landscape')
            ->download($filename);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function buildReportRows(Carbon $start, Carbon $end, string $search): Collection
    {
        return Employee::query()
            ->active()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('employee_code', 'like', "%{$search}%")
                        ->orWhere('department', 'like', "%{$search}%");
                });
            })
            ->orderBy('employee_code')
            ->get()
            ->map(function (Employee $employee) use ($start, $end) {
                $attendance = AttendanceRecord::query()
                    ->where('employee_id', $employee->id)
                    ->whereDate('work_date', '>=', $start->toDateString())
                    ->whereDate('work_date', '<=', $end->toDateString())
                    ->get()
                    ->keyBy(fn (AttendanceRecord $record) => $record->work_date->toDateString());

                $summary = AttendancePeriodSummary::build($employee, $start, $end, $attendance->values());

                $lateMinutes = (int) $attendance->sum('late_minutes');
                $lateFrequency = $attendance->where('late_minutes', '>', 0)->count();
                $earlyMinutes = (int) $attendance->sum('undertime_minutes');
                $earlyFrequency = $attendance->where('undertime_minutes', '>', 0)->count();
                $otMinutes = (int) $attendance->sum('ot_minutes');

                $days = [];
                foreach (CarbonPeriod::create($start, $end) as $day) {
                    $record = $attendance->get($day->toDateString());

                    $days[] = [
                        'date' => $day->toDateString(),
                        'label' => $day->format('j/D'),
                        'ot_in' => $record?->time_in,
                        'ot_out' => $record?->time_out,
                    ];
                }

                return [
                    'id' => $employee->id,
                    'employee_code' => $employee->employee_code,
                    'name' => $employee->full_name,
                    'department' => $employee->department,
                    'absences' => $summary['absent'],
                    'leave' => $summary['leave'],
                    'business_trip' => $summary['businessTrip'],
                    'attend_standard' => $summary['scheduled'],
                    'ot_normal_hours' => round($otMinutes / 60, 2),
                    'ot_special_hours' => 0,
                    'late_frequency' => $lateFrequency,
                    'late_minutes' => $lateMinutes,
                    'early_frequency' => $earlyFrequency,
                    'early_minutes' => $earlyMinutes,
                    'days' => $days,
                ];
            })
            ->values();
    }
}
