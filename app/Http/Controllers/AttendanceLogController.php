<?php

namespace App\Http\Controllers;

use App\Models\AttendanceAuditLog;
use App\Models\Employee;
use App\Models\OptionList;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every change made to time sheets and schedules: who made it, when, from
 * which computer, and the value before and after — so a wrong entry can be
 * traced back to the person who made it.
 */
class AttendanceLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $this->filters($request);

        $logs = $this->query($filters)
            ->with(['user:id,name', 'employee:id,employee_code,first_name,last_name,work_location'])
            ->latest('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AttendanceAuditLog $log) => $this->row($log));

        // Who changed what in the period, most active first.
        $byUser = $this->query($filters)
            ->select('user_id', 'action', DB::raw('count(*) as total'))
            ->groupBy('user_id', 'action')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows, $userId) => [
                'user_id' => $userId === '' ? null : (int) $userId,
                'created' => (int) $rows->where('action', 'created')->sum('total'),
                'updated' => (int) $rows->where('action', 'updated')->sum('total'),
                'deleted' => (int) $rows->where('action', 'deleted')->sum('total'),
            ])
            ->values();
        $names = User::query()->whereIn('id', $byUser->pluck('user_id')->filter())->pluck('name', 'id');

        return Inertia::render('attendance-logs/index', [
            'logs' => $logs,
            'summary' => $byUser
                ->map(fn (array $row) => [
                    ...$row,
                    'name' => $row['user_id'] ? ($names[$row['user_id']] ?? 'Deleted user') : 'System',
                    'total' => $row['created'] + $row['updated'] + $row['deleted'],
                ])
                ->sortByDesc('total')
                ->values(),
            'filters' => [
                ...$filters,
                'from' => $filters['from']->toDateString(),
                'to' => $filters['to']->toDateString(),
            ],
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'employees' => Employee::query()->withTrashed()->orderBy('last_name')->get(['id', 'employee_code', 'first_name', 'last_name']),
            'sites' => OptionList::values('work_location'),
            'sources' => AttendanceAuditLog::SOURCES,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filters($request);
        $name = 'attendance-changes-'.$filters['from']->toDateString().'-to-'.$filters['to']->toDateString().'.csv';

        return response()->streamDownload(function () use ($filters) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Changed at', 'Changed by', 'IP address', 'Employee code', 'Employee', 'Site', 'Work date', 'Where', 'Action', 'Changes']);

            $this->query($filters)
                ->with(['user:id,name', 'employee:id,employee_code,first_name,last_name,work_location'])
                ->latest('id')
                ->chunk(500, function ($logs) use ($out) {
                    foreach ($logs as $log) {
                        $row = $this->row($log);
                        fputcsv($out, [
                            $row['at'],
                            $row['user'],
                            $row['ip'],
                            $row['employee_code'],
                            $row['employee'],
                            $row['site'],
                            $row['work_date'],
                            $row['source'],
                            $row['action'],
                            collect($row['changes'])
                                ->map(fn ($pair, $field) => "{$field}: ".($pair[0] ?? '—').' → '.($pair[1] ?? '—'))
                                ->implode('; '),
                        ]);
                    }
                });

            fclose($out);
        }, $name, ['Content-Type' => 'text/csv']);
    }

    /**
     * @return array{from: Carbon, to: Carbon, user_id: int|null, employee_id: int|null, site: string|null, source: string|null, action: string|null, work_date: string|null}
     */
    protected function filters(Request $request): array
    {
        $workDate = $request->string('work_date')->toString();

        return [
            // Defaults to the last 30 days of changes.
            'from' => $request->date('from')?->startOfDay() ?? Carbon::today()->subDays(29),
            'to' => $request->date('to')?->endOfDay() ?? Carbon::today()->endOfDay(),
            'user_id' => $request->integer('user_id') ?: null,
            'employee_id' => $request->integer('employee_id') ?: null,
            'site' => $request->string('site')->toString() ?: null,
            'source' => array_key_exists($request->string('source')->toString(), AttendanceAuditLog::SOURCES)
                ? $request->string('source')->toString()
                : null,
            'action' => in_array($request->string('action')->toString(), ['created', 'updated', 'deleted'], true)
                ? $request->string('action')->toString()
                : null,
            // A single day's full history: every change to it, whenever made.
            'work_date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) ? $workDate : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AttendanceAuditLog>
     */
    protected function query(array $filters): Builder
    {
        return AttendanceAuditLog::query()
            // A day's history spans all time, so the change-date range only
            // applies when not looking at one day.
            ->when($filters['work_date'] === null, fn ($q) => $q->whereBetween('created_at', [$filters['from'], $filters['to']]))
            ->when($filters['work_date'], fn ($q, $date) => $q->whereDate('work_date', $date))
            ->when($filters['user_id'], fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['employee_id'], fn ($q, $id) => $q->where('employee_id', $id))
            ->when($filters['site'], fn ($q, $site) => $q->whereHas('employee', fn ($e) => $e->withTrashed()->where('work_location', $site)))
            ->when($filters['source'], fn ($q, $source) => $q->where('source', $source))
            ->when($filters['action'], fn ($q, $action) => $q->where('action', $action));
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(AttendanceAuditLog $log): array
    {
        $employee = $log->employee;

        return [
            'id' => $log->id,
            'at' => $log->created_at?->format('M j, Y g:i:s A'),
            'user' => $log->user?->name ?? 'System',
            'user_id' => $log->user_id,
            'ip' => $log->ip_address,
            'employee_id' => $log->employee_id,
            'employee' => $employee ? "{$employee->last_name}, {$employee->first_name}" : '—',
            'employee_code' => $employee?->employee_code,
            'site' => $employee?->work_location,
            'work_date' => $log->work_date->toDateString(),
            'work_date_label' => $log->work_date->format('D, M j, Y'),
            'month' => $log->work_date->format('Y-m'),
            'source' => AttendanceAuditLog::SOURCES[$log->source] ?? $log->source,
            'source_key' => $log->source,
            'action' => $log->action,
            'changes' => $log->changes,
        ];
    }
}
