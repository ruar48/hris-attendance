<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'position',
        'department',
        'basic_salary',
        'daily_rate',
        'sunday_route_rate',
        'hourly_rate',
        'biometric_user_id',
        'hire_date',
        'status',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'sunday_route_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'hire_date' => 'date',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function biometricLogs(): HasMany
    {
        return $this->hasMany(BiometricLog::class);
    }

    public function dtrLogs(): HasMany
    {
        return $this->hasMany(DtrLog::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function cashAdvances(): HasMany
    {
        return $this->hasMany(CashAdvance::class);
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
