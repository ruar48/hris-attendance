<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_pay',
        'holiday_pay',
        'sunday_route',
        'overtime_pay',
        'thirteenth_month',
        'late_deduction',
        'undertime_deduction',
        'cash_advance_deduction',
        'sss',
        'philhealth',
        'pagibig',
        'withholding_tax',
        'total_earnings',
        'total_deductions',
        'net_pay',
    ];

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'sunday_route' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'thirteenth_month' => 'decimal:2',
            'late_deduction' => 'decimal:2',
            'undertime_deduction' => 'decimal:2',
            'cash_advance_deduction' => 'decimal:2',
            'sss' => 'decimal:2',
            'philhealth' => 'decimal:2',
            'pagibig' => 'decimal:2',
            'withholding_tax' => 'decimal:2',
            'total_earnings' => 'decimal:2',
            'total_deductions' => 'decimal:2',
            'net_pay' => 'decimal:2',
        ];
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
