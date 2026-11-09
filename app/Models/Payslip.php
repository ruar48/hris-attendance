<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payslip extends Model
{
    /**
     * Adjustments HR enters after the run. They are added to net pay.
     *
     * @var array<string, string>
     */
    public const ADJUSTMENT_FIELDS = [
        'reimbursement' => 'Reimbursement',
        'allowance' => 'Allowance',
    ];

    /**
     * Warehouse subsidy: forklift diesel plus each warehouse's disbursement.
     * Released with the payslip but kept out of net pay (it is not salary).
     *
     * @var array<string, string>
     */
    public const WAREHOUSE_SUBSIDY_FIELDS = [
        'forklift_diesel' => 'Forklift Diesel',
        'disbursement_batangas' => 'Disbursement – Batangas',
        'disbursement_dasma' => 'Disbursement – Dasma',
        'disbursement_san_pablo' => 'Disbursement – San Pablo',
        'disbursement_tayabas' => 'Disbursement – Tayabas',
        'disbursement_taguig' => 'Disbursement – Taguig',
    ];

    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'basic_pay',
        'absent_days',
        'unpaid_leave_days',
        'holiday_pay',
        'sunday_route',
        'overtime_pay',
        'night_diff_pay',
        'thirteenth_month',
        'late_deduction',
        'undertime_deduction',
        'absence_deduction',
        'cash_advance_deduction',
        'sss',
        'philhealth',
        'pagibig',
        'withholding_tax',
        'total_earnings',
        'total_deductions',
        'reimbursement',
        'allowance',
        'total_adjustments',
        'net_pay',
        'forklift_diesel',
        'disbursement_batangas',
        'disbursement_dasma',
        'disbursement_san_pablo',
        'disbursement_tayabas',
        'disbursement_taguig',
        'warehouse_subsidy',
    ];

    protected function casts(): array
    {
        return [
            'basic_pay' => 'decimal:2',
            'holiday_pay' => 'decimal:2',
            'sunday_route' => 'decimal:2',
            'overtime_pay' => 'decimal:2',
            'night_diff_pay' => 'decimal:2',
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
            'reimbursement' => 'decimal:2',
            'allowance' => 'decimal:2',
            'total_adjustments' => 'decimal:2',
            'net_pay' => 'decimal:2',
            'forklift_diesel' => 'decimal:2',
            'disbursement_batangas' => 'decimal:2',
            'disbursement_dasma' => 'decimal:2',
            'disbursement_san_pablo' => 'decimal:2',
            'disbursement_tayabas' => 'decimal:2',
            'disbursement_taguig' => 'decimal:2',
            'warehouse_subsidy' => 'decimal:2',
        ];
    }

    /**
     * Set the adjustment and warehouse subsidy amounts, then recompute their
     * totals and net pay (earnings − deductions + adjustments).
     *
     * @param  array<string, float|int|string|null>  $amounts
     */
    public function applyAdjustments(array $amounts): void
    {
        foreach ([...array_keys(self::ADJUSTMENT_FIELDS), ...array_keys(self::WAREHOUSE_SUBSIDY_FIELDS)] as $field) {
            if (array_key_exists($field, $amounts)) {
                $this->{$field} = round((float) ($amounts[$field] ?? 0), 2);
            }
        }

        $adjustments = round(collect(array_keys(self::ADJUSTMENT_FIELDS))
            ->sum(fn (string $field) => (float) $this->{$field}), 2);

        $this->total_adjustments = $adjustments;
        $this->warehouse_subsidy = round(collect(array_keys(self::WAREHOUSE_SUBSIDY_FIELDS))
            ->sum(fn (string $field) => (float) $this->{$field}), 2);
        $this->net_pay = round((float) $this->total_earnings - (float) $this->total_deductions + $adjustments, 2);
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
