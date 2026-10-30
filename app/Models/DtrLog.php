<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One encoded time sheet day. `reason` holds the day's remarks.
 *
 * @property-read CarbonImmutable $work_date
 */
class DtrLog extends Model
{
    public const DAY_STATUSES = ['worked', 'day_off', 'absent', 'leave'];

    protected $fillable = [
        'employee_id',
        'work_date',
        'time_in',
        'break_out',
        'break_in',
        'time_out',
        'day_status',
        'reason',
        'status',
        'approved_by',
        'encoded_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function encoder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'encoded_by');
    }
}
