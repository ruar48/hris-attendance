<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRecord extends Model
{
    protected $fillable = [
        'employee_id',
        'work_date',
        'time_in',
        'time_out',
        'source',
        'late_minutes',
        'undertime_minutes',
        'ot_minutes',
        'is_holiday',
        'is_sunday',
        'holiday_pay',
        'sunday_pay',
        'ot_pay',
        'late_deduction',
        'undertime_deduction',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'is_holiday' => 'boolean',
            'is_sunday' => 'boolean',
            'holiday_pay' => 'decimal:2',
            'sunday_pay' => 'decimal:2',
            'ot_pay' => 'decimal:2',
            'late_deduction' => 'decimal:2',
            'undertime_deduction' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
