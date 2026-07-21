<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Attribute types produced by the casts below.
 *
 * @property-read CarbonImmutable|null $released_at
 */
class CashAdvance extends Model
{
    protected $fillable = [
        'employee_id',
        'amount',
        'balance',
        'deduction_per_payroll',
        'released_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'balance' => 'decimal:2',
            'deduction_per_payroll' => 'decimal:2',
            'released_at' => 'date',
        ];
    }

    /**
     * How much to take off this advance each payroll run.
     *
     * Falls back to the payroll-settings default (a monthly cap, so half of it
     * lands on each kinsena run) when this advance has no instalment of its own.
     */
    public function instalmentPerPayroll(): float
    {
        if ($this->deduction_per_payroll !== null) {
            return (float) $this->deduction_per_payroll;
        }

        return round(PayrollSetting::float('cash_advance_max_deduction') / 2, 2);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('balance', '>', 0);
    }
}
