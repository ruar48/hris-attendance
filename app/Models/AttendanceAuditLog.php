<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change to a time sheet day, a schedule day, or a person's Manning
 * entry. `changes` maps each field that moved to its [old, new] pair, and
 * `site` is the Manning site the change was made for.
 *
 * @property-read CarbonImmutable|null $work_date  Empty for Manning changes, which aren't tied to a day.
 * @property-read CarbonImmutable|null $created_at
 * @property-read array<string, array{0: string|null, 1: string|null}> $changes
 */
class AttendanceAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'employee_id',
        'site',
        'work_date',
        'action',
        'source',
        'changes',
        'ip_address',
    ];

    /** @var array<string, string> Where a change was made. */
    public const SOURCES = [
        'timesheet' => 'Time Sheet',
        'schedule' => 'Schedule',
        'manning' => 'Manning',
    ];

    protected static function booted(): void
    {
        // Unless given, a change belongs to the site the person works at now.
        static::creating(function (self $log) {
            $log->site ??= Employee::withTrashed()->whereKey($log->employee_id)->value('work_location');
        });
    }

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'changes' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
