<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class Schedule extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'shift_type_id',
        'status',
        'notes',
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

    public function shiftType(): BelongsTo
    {
        return $this->belongsTo(ShiftType::class);
    }

    /**
     * @return Collection<string, self> keyed by Y-m-d
     */
    public static function mapForPeriod(int $employeeId, CarbonInterface $start, CarbonInterface $end): Collection
    {
        // whereBetween with plain "Y-m-d" strings would silently miss the
        // range's last day: work_date is cast to `date`, which Eloquent
        // stores as "Y-m-d 00:00:00", and that string sorts *after* the bare
        // "Y-m-d" upper bound lexicographically.
        return static::query()
            ->where('employee_id', $employeeId)
            ->whereDate('work_date', '>=', $start->toDateString())
            ->whereDate('work_date', '<=', $end->toDateString())
            ->with('shiftType')
            ->get()
            ->keyBy(fn (self $schedule) => $schedule->work_date->toDateString());
    }
}
