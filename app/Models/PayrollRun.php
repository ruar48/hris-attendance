<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    protected $fillable = [
        'payroll_period_id',
        'total_employees',
        'total_payroll',
        'total_deductions',
        'net_payroll',
        'status',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'total_payroll' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_payroll' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(PayrollPeriod::class, 'payroll_period_id');
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(Payslip::class);
    }
}
