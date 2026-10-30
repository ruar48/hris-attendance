<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One change made to an encoded time sheet day. `changes` maps each field
 * that moved to its [old, new] pair.
 *
 * @property-read CarbonImmutable $work_date
 * @property-read CarbonImmutable|null $created_at
 * @property-read array<string, array{0: string|null, 1: string|null}> $changes
 */
class AttendanceAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'employee_id',
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
    ];

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
