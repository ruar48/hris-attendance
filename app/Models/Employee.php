<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Attribute types produced by the casts below.
 *
 * @property-read CarbonImmutable|null $hire_date
 * @property-read CarbonImmutable|null $last_working_day
 * @property-read CarbonImmutable|null $deleted_at
 */
class Employee extends Model
{
    use SoftDeletes;

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
        'last_working_day',
        'status',
        'user_id',
        ...self::PROFILE_FIELDS,
        ...self::MASTER_FILE_FIELDS,
    ];

    /** @var list<string> Extended fields shown on the Employee Master File tab. */
    public const MASTER_FILE_FIELDS = [
        'middle_name',
        'suffix',
        'photo_url',
        'personal_email',
        'company_email',
        'current_address',
        'permanent_address',
    ];

    /** @var list<string> Extended profile fields shown on the employee Profile tab. */
    public const PROFILE_FIELDS = [
        'gender',
        'marital_status',
        'date_of_birth',
        'place_of_birth',
        'nationality',
        'religion',
        'contact_number',
        'address',
        'emergency_contact_name',
        'emergency_contact_number',
        'emergency_contact_address',
        'emergency_contact_relationship',
        'immediate_superior',
        'regularization_date',
        'separation_date',
        'division',
        'job_level',
        'employment_status',
        'work_location',
        'shift_schedule',
        'time_in_schedule',
        'time_out_schedule',
        'salary_type',
        'tax_status',
        'rice_allowance',
        'transpo_allowance',
        'meal_allowance',
        'de_minimis_allowance',
        'bank_name',
        'bank_account_number',
        'bank_account_status',
        'sss_number',
        'philhealth_number',
        'pagibig_number',
        'tin_number',
        'government_benefits_remarks',
    ];

    protected function casts(): array
    {
        return [
            'basic_salary' => 'decimal:2',
            'daily_rate' => 'decimal:2',
            'sunday_route_rate' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'hire_date' => 'date',
            'last_working_day' => 'date',
            'date_of_birth' => 'date',
            'regularization_date' => 'date',
            'separation_date' => 'date',
            'rice_allowance' => 'decimal:2',
            'transpo_allowance' => 'decimal:2',
            'meal_allowance' => 'decimal:2',
            'de_minimis_allowance' => 'decimal:2',
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

    /**
     * Everyone who should be paid for a period.
     *
     * This is scopeActive plus anyone archived part-way through: someone who
     * worked until the 10th still earns for those days, so they stay on the
     * payroll until their last working day falls before the period starts.
     * Their basic pay is prorated in PayrollCalculator::periodBasicPay().
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeForPayrollPeriod(Builder $query, CarbonInterface $startDate): Builder
    {
        return $query->withTrashed()
            ->where('status', 'active')
            ->where(function ($q) use ($startDate) {
                $q->whereNull('deleted_at')
                    ->orWhere('last_working_day', '>=', $startDate->toDateString());
            });
    }

    /**
     * Whether this employee stopped working part-way through the period.
     */
    public function leftDuring(CarbonInterface $endDate): bool
    {
        return $this->last_working_day !== null
            && $this->last_working_day->lt($endDate);
    }
}
