<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAuditLog;
use App\Models\AttendanceRecord;
use App\Models\DtrLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\OptionList;
use App\Models\PayrollSetting;
use App\Models\Schedule;
use App\Models\ShiftType;
use App\Services\AttendanceResolver;
use App\Services\ScheduleSheetBuilder;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The monthly daily time record HR encodes by hand, one sheet per employee,
 * laid out like the paper DTR. Every saved change is written to the
 * attendance audit log.
 */
class TimesheetController extends Controller
{
    /** The four DTR columns, in paper order: AM arrival/departure, PM arrival/departure. */
    protected const TIME_FIELDS = ['time_in', 'break_out', 'break_in', 'time_out'];

    /** Arrival/departure jotted outside the form; kept, never paid. */
    protected const NOTE_FIELDS = ['note_time_in', 'note_time_out'];

    /** How many monthly DTR forms an employee's tab can show at once. */
    protected const SPANS = [1, 3, 6, 12];

    /** Manning tab sections, in the client's order, matched against each position. */
    protected const MANNING_GROUPS = [
        'Opa/OFD/ Inventory Analyst' => '/opa|ofd|inventory/i',
        'Forklift Operators' => '/forklift/i',
        'CHECKERS' => '/checker/i',
        'Yardman/Utility' => '/yard|utility/i',
    ];

    public function index(Request $request): Response
    {
        return Inertia::render('timesheet/index', $this->sheetProps($request));
    }

    /**
     * The Manning tab on its own, inside the app (with the sidebar).
     */
    public function manningPage(Request $request): Response
    {
        $site = $this->site($request);

        return Inertia::render('manning/index', [
            'sites' => $this->siteOptions(),
            'site' => $site ?? 'all',
            'manning' => $this->manning($site),
        ]);
    }

    /**
     * The same time sheets as a full-screen workbook, with the Manning and
     * schedule tabs first — no app sidebar or header.
     */
    public function workbook(Request $request, ScheduleSheetBuilder $builder): Response
    {
        $site = $this->site($request);
        $month = $this->monthStart($request->string('month')->toString());

        // Like the client's workbooks, it opens on the Manning tab.
        $sheet = $request->string('sheet')->toString();
        if (! in_array($sheet, ['manning', 'schedule', 'dtr'], true)) {
            $sheet = $request->filled('employee_id') ? 'dtr' : 'manning';
        }

        // An employee's tab shows several months, like the client's; the
        // DTR forms are only loaded when that tab is open.
        $props = $this->sheetProps($request, defaultSpan: 6, withSheets: $sheet === 'dtr');

        return Inertia::render('timesheet/workbook', [
            ...$props,
            'sheet' => $sheet,
            'schedule' => $sheet === 'schedule' ? $builder->build($month, $site) : null,
            'manning' => $sheet === 'manning' ? $this->manning($site) : null,
        ]);
    }

    /**
     * A change made on the Manning tab: moving someone to or off a site, or
     * setting their regular schedule time.
     */
    public function updateManning(Request $request, Employee $employee, AttendanceResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'work_location' => ['sometimes', 'nullable', Rule::in(OptionList::values('work_location'))],
            'shift_schedule' => ['sometimes', 'nullable', Rule::in(OptionList::values('shift_schedule'))],
        ]);

        if (array_key_exists('shift_schedule', $data)) {
            // The employee record keeps the shift's times alongside it, the
            // same way the Employment Details tab saves a shift.
            $shift = OptionList::query()->category('shift_schedule')->where('value', $data['shift_schedule'])->first();
            $data['time_in_schedule'] = $shift?->time_in;
            $data['time_out_schedule'] = $shift?->time_out;
        }

        $employee->update($data);

        // Encoded days without a scheduled shift are measured against this one.
        if (array_key_exists('shift_schedule', $data)) {
            $resolver->refreshEncodedDays($employee);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Updated {$employee->full_name}."]);

        return back();
    }

    /**
     * The selected site's value, or null for every site. Defaults to the
     * first site so the workbook opens on one, like a site's own file.
     */
    protected function site(Request $request): ?string
    {
        $sites = OptionList::values('work_location');
        $requested = $request->string('site')->toString();

        if ($requested === 'all') {
            return null;
        }

        return in_array($requested, $sites, true) ? $requested : ($sites[0] ?? null);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    protected function siteOptions(): array
    {
        return OptionList::query()
            ->category('work_location')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (OptionList $option) => ['value' => $option->value, 'label' => $option->label])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function sheetProps(Request $request, int $defaultSpan = 1, bool $withSheets = true): array
    {
        $site = $this->site($request);

        $employees = Employee::query()
            ->active()
            ->when($site, fn ($query) => $query->where('work_location', $site))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'employee_code', 'first_name', 'last_name', 'position']);

        $employee = $employees->firstWhere('id', $request->integer('employee_id')) ?? $employees->first();
        $month = $this->monthStart($request->string('month')->toString());

        // How many monthly forms to show, ending at the chosen month — like an
        // employee's tab in the client's workbook, one DTR per month.
        $span = in_array($request->integer('span'), self::SPANS, true) ? $request->integer('span') : $defaultSpan;
        $first = $month->copy()->subMonthsNoOverflow($span - 1);
        $sheets = [];

        if ($employee && $withSheets) {
            for ($m = $first->copy(); $m->lte($month); $m->addMonthNoOverflow()) {
                $sheets[] = [
                    'month' => $m->format('Y-m'),
                    'days' => $this->days($employee, $m->copy(), $m->copy()->endOfMonth()),
                ];
            }
        }

        return [
            'sites' => $this->siteOptions(),
            'site' => $site ?? 'all',
            'employees' => $employees,
            'employee' => $employee,
            'month' => $month->format('Y-m'),
            'span' => $span,
            'sheets' => $sheets,
            'history' => $employee && $withSheets ? $this->history($employee, $first, $month->copy()->endOfMonth()) : [],
            // Printed on the form header: the person's regular Manning
            // schedule time, else the company default.
            'official_hours' => $this->officialHours($employee ? Employee::query()->find($employee->id) : null),
            'in_charge' => $request->user()?->name,
        ];
    }

    /**
     * The Manning tab: the site's people grouped by role, each with their
     * start date and regular schedule time.
     *
     * @return array<string, mixed>
     */
    protected function manning(?string $site): array
    {
        $siteOption = $site
            ? OptionList::query()->category('work_location')->where('value', $site)->first()
            : null;

        $shiftLabels = OptionList::query()->category('shift_schedule')->pluck('label', 'value');

        $employees = Employee::query()
            ->active()
            ->when($site, fn ($query) => $query->where('work_location', $site))
            ->orderByRaw('hire_date is null')
            ->orderBy('hire_date')
            ->orderBy('last_name')
            ->get();

        $groups = collect(self::MANNING_GROUPS)
            ->map(fn (string $pattern, string $title) => ['title' => $title, 'pattern' => $pattern, 'rows' => []])
            ->all();
        $others = ['title' => 'Others', 'pattern' => null, 'rows' => []];

        foreach ($employees as $employee) {
            $row = [
                'employee_id' => $employee->id,
                'name' => "{$employee->first_name} {$employee->last_name}",
                'start_date' => $employee->hire_date?->format('M j, Y'),
                'shift_schedule' => $employee->shift_schedule,
                'schedule' => $shiftLabels->get((string) $employee->shift_schedule)
                    ?? ($employee->time_in_schedule && $employee->time_out_schedule
                        ? "{$employee->time_in_schedule} - {$employee->time_out_schedule}"
                        : null),
                'position' => $employee->position,
            ];

            $title = collect($groups)->first(
                fn (array $group) => preg_match($group['pattern'], (string) $employee->position) === 1
            )['title'] ?? null;

            if ($title) {
                $groups[$title]['rows'][] = $row;
            } else {
                $others['rows'][] = $row;
            }
        }

        $groups = collect([...array_values($groups), $others])
            ->filter(fn (array $group) => $group['rows'] !== [] || $group['pattern'] !== null)
            ->map(fn (array $group) => ['title' => $group['title'], 'rows' => $group['rows']])
            ->values()
            ->all();

        return [
            'title' => ($siteOption?->label ?? 'All Sites').' Manning '.now()->year,
            'operation_hours' => $siteOption?->time_in && $siteOption->time_out
                ? "{$siteOption->time_in} - {$siteOption->time_out}"
                : null,
            'groups' => $groups,
            // People not yet placed at any site, offered for adding to this one.
            'unassigned' => $site
                ? Employee::query()
                    ->active()
                    ->where(fn ($q) => $q->whereNull('work_location')->orWhereNotIn('work_location', OptionList::values('work_location')))
                    ->orderBy('last_name')
                    ->get(['id', 'employee_code', 'first_name', 'last_name'])
                : [],
        ];
    }

    protected function officialHours(?Employee $employee): ?string
    {
        $in = ShiftType::toDbTime($employee?->time_in_schedule);
        $out = ShiftType::toDbTime($employee?->time_out_schedule);

        return $in && $out
            ? $this->shiftLabel($in, $out)
            : $this->shiftLabel(
                (string) PayrollSetting::getValue('shift_start', '08:00'),
                (string) PayrollSetting::getValue('shift_end', '17:00'),
            );
    }

    public function update(Request $request, AttendanceResolver $resolver): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.date' => ['required', 'date_format:Y-m-d'],
            'rows.*.time_in' => ['nullable', 'date_format:H:i'],
            'rows.*.break_out' => ['nullable', 'date_format:H:i'],
            'rows.*.break_in' => ['nullable', 'date_format:H:i'],
            'rows.*.time_out' => ['nullable', 'date_format:H:i'],
            'rows.*.note_time_in' => ['nullable', 'date_format:H:i'],
            'rows.*.note_time_out' => ['nullable', 'date_format:H:i'],
            'rows.*.day_status' => ['required', Rule::in(DtrLog::DAY_STATUSES)],
            'rows.*.remarks' => ['nullable', 'string', 'max:255'],
        ]);

        $errors = [];
        foreach ($data['rows'] as $i => $row) {
            if (! empty($row['time_in']) && ! empty($row['time_out']) && $row['time_out'] <= $row['time_in']) {
                $errors["rows.{$i}.time_out"] = "Time out on {$row['date']} must be later than time in.";
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $employee = Employee::query()->findOrFail((int) $data['employee_id']);
        $userId = $request->user()?->id;
        $changed = 0;

        DB::transaction(function () use ($data, $employee, $userId, $resolver, &$changed) {
            foreach ($data['rows'] as $row) {
                if ($this->saveRow($employee, $row, $userId)) {
                    $resolver->resolveDay($employee, Carbon::parse($row['date']), Holiday::findForDate(Carbon::parse($row['date'])));
                    $changed++;
                }
            }
        });

        Inertia::flash('toast', [
            'type' => $changed > 0 ? 'success' : 'info',
            'message' => $changed > 0
                ? "Saved {$changed} day".($changed === 1 ? '' : 's')." for {$employee->full_name}."
                : 'Nothing changed.',
        ]);

        return back();
    }

    /**
     * Apply one encoded row and log what moved. Returns whether anything changed.
     *
     * @param  array{date: string, time_in?: string|null, break_out?: string|null, break_in?: string|null, time_out?: string|null, note_time_in?: string|null, note_time_out?: string|null, day_status: string, remarks?: string|null}  $row
     */
    protected function saveRow(Employee $employee, array $row, ?int $userId): bool
    {
        $new = [
            'time_in' => $this->toDbTime($row['time_in'] ?? null),
            'break_out' => $this->toDbTime($row['break_out'] ?? null),
            'break_in' => $this->toDbTime($row['break_in'] ?? null),
            'time_out' => $this->toDbTime($row['time_out'] ?? null),
            'note_time_in' => $this->toDbTime($row['note_time_in'] ?? null),
            'note_time_out' => $this->toDbTime($row['note_time_out'] ?? null),
            'day_status' => $row['day_status'],
            'reason' => filled($row['remarks'] ?? null) ? trim($row['remarks']) : null,
        ];

        // Times only mean something on a worked day. Outside notes are kept
        // whatever the day is marked.
        if ($new['day_status'] !== 'worked') {
            foreach (self::TIME_FIELDS as $field) {
                $new[$field] = null;
            }
        }

        $isBlank = $new['day_status'] === 'worked'
            && collect([...self::TIME_FIELDS, ...self::NOTE_FIELDS])->every(fn (string $field) => $new[$field] === null)
            && $new['reason'] === null;

        $existing = DtrLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $row['date'])
            ->first();

        $old = $existing
            ? [
                'time_in' => $existing->time_in,
                'break_out' => $existing->break_out,
                'break_in' => $existing->break_in,
                'time_out' => $existing->time_out,
                'note_time_in' => $existing->note_time_in,
                'note_time_out' => $existing->note_time_out,
                'day_status' => $existing->day_status,
                'reason' => $existing->reason,
            ]
            : null;

        if ($existing === null && $isBlank) {
            return false;
        }

        if ($existing !== null && $isBlank) {
            $existing->delete();
            $this->audit($employee, $row['date'], 'deleted', $this->diff($old, null), $userId);

            return true;
        }

        $changes = $this->diff($old, $new);
        if ($changes === []) {
            return false;
        }

        if ($existing) {
            $existing->update([...$new, 'status' => 'approved', 'encoded_by' => $userId]);
        } else {
            DtrLog::query()->create([
                'employee_id' => $employee->id,
                'work_date' => $row['date'],
                ...$new,
                'status' => 'approved',
                'approved_by' => $userId,
                'encoded_by' => $userId,
            ]);
        }

        $this->audit($employee, $row['date'], $existing ? 'updated' : 'created', $changes, $userId);

        return true;
    }

    /**
     * @param  array<string, string|null>|null  $old
     * @param  array<string, string|null>|null  $new
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    protected function diff(?array $old, ?array $new): array
    {
        $changes = [];

        foreach (['day_status', ...self::TIME_FIELDS, ...self::NOTE_FIELDS, 'reason'] as $field) {
            $before = $old[$field] ?? null;
            $after = $new[$field] ?? null;

            if ($field === 'day_status') {
                // A missing row reads as an ordinary worked day.
                $before ??= $old === null ? null : 'worked';
                $after ??= $new === null ? null : 'worked';
            }

            if ($before !== $after) {
                $label = $field === 'reason' ? 'remarks' : $field;
                $changes[$label] = [
                    $this->forDisplay($field, $before),
                    $this->forDisplay($field, $after),
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  array<string, array{0: string|null, 1: string|null}>  $changes
     */
    protected function audit(Employee $employee, string $date, string $action, array $changes, ?int $userId): void
    {
        AttendanceAuditLog::query()->create([
            'user_id' => $userId,
            'employee_id' => $employee->id,
            'work_date' => $date,
            'action' => $action,
            'source' => 'timesheet',
            'changes' => $changes,
            'ip_address' => request()->ip(),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function days(Employee $employee, Carbon $start, Carbon $end): array
    {
        $holidays = Holiday::mapForPeriod($start, $end);
        $schedules = Schedule::mapForPeriod($employee->id, $start, $end);

        $entries = DtrLog::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (DtrLog $log) => $log->work_date->toDateString());

        $records = AttendanceRecord::query()
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->get()
            ->keyBy(fn (AttendanceRecord $record) => $record->work_date->toDateString());

        $days = [];

        foreach (CarbonPeriod::create($start, $end) as $day) {
            $date = $day->toDateString();
            $entry = $entries->get($date);
            $record = $records->get($date);
            $schedule = $schedules->get($date);
            $shift = $schedule?->shiftType;

            $days[] = [
                'date' => $date,
                'day' => $day->day,
                'dow' => $day->format('D'),
                'is_sunday' => $day->isSunday(),
                'holiday' => $holidays->get($date)?->name,
                'schedule' => match (true) {
                    // Absent, sick leave… set on the schedule.
                    $schedule !== null && isset(Schedule::STATUS_LABELS[$schedule->status]) => Schedule::STATUS_LABELS[$schedule->status],
                    $shift === null => null,
                    $shift->is_rest_day => 'Rest day',
                    default => $this->shiftLabel($shift->time_in, $shift->time_out),
                },
                'time_in' => $entry?->day_status === 'worked' ? $this->toInputTime($entry->time_in) : '',
                'break_out' => $entry?->day_status === 'worked' ? $this->toInputTime($entry->break_out) : '',
                'break_in' => $entry?->day_status === 'worked' ? $this->toInputTime($entry->break_in) : '',
                'time_out' => $entry?->day_status === 'worked' ? $this->toInputTime($entry->time_out) : '',
                'note_time_in' => $this->toInputTime($entry?->note_time_in),
                'note_time_out' => $this->toInputTime($entry?->note_time_out),
                'day_status' => $entry?->day_status ?? 'worked',
                'remarks' => $entry?->reason ?? '',
                'result' => $record ? [
                    'worked_minutes' => $record->worked_minutes,
                    'late_minutes' => $record->late_minutes,
                    'undertime_minutes' => $record->undertime_minutes,
                    'ot_minutes' => $record->ot_minutes,
                    'is_incomplete' => $record->is_incomplete,
                ] : null,
            ];
        }

        return $days;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function history(Employee $employee, Carbon $start, Carbon $end): array
    {
        return AttendanceAuditLog::query()
            ->with('user:id,name')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->latest('id')
            ->limit(200)
            ->get()
            ->map(fn (AttendanceAuditLog $log) => [
                'id' => $log->id,
                'at' => $log->created_at?->format('M j, Y g:i A'),
                'user' => $log->user?->name ?? 'System',
                'work_date' => $log->work_date->format('M j'),
                'action' => $log->action,
                'changes' => $log->changes,
            ])
            ->all();
    }

    protected function monthStart(string $month): Carbon
    {
        if (preg_match('/^\d{4}-\d{2}$/', $month)) {
            return Carbon::createFromFormat('Y-m-d', "{$month}-01")->startOfDay();
        }

        return Carbon::today()->startOfMonth();
    }

    protected function shiftLabel(?string $in, ?string $out): ?string
    {
        if (! $in || ! $out) {
            return null;
        }

        return Carbon::parse($in)->format('g:iA').' – '.Carbon::parse($out)->format('g:iA');
    }

    protected function toDbTime(?string $time): ?string
    {
        return filled($time) ? "{$time}:00" : null;
    }

    /**
     * "06:45:00" → "6:45", the way times are written on the paper DTR.
     */
    protected function toInputTime(?string $time): string
    {
        return $time ? preg_replace('/^0(?=\d)/', '', substr($time, 0, 5)) : '';
    }

    protected function forDisplay(string $field, ?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($field) {
            'time_in', 'break_out', 'break_in', 'time_out', 'note_time_in', 'note_time_out' => substr($value, 0, 5),
            'day_status' => str_replace('_', ' ', $value),
            default => $value,
        };
    }
}
