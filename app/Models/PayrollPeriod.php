<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PayrollPeriod extends Model
{
    protected $fillable = [
        'name',
        'year',
        'month',
        'half',
        'start_date',
        'end_date',
        'cutoff_date',
        'process_start',
        'process_end',
        'payslip_release',
        'payday',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cutoff_date' => 'date',
            'process_start' => 'date',
            'process_end' => 'date',
            'payslip_release' => 'date',
            'payday' => 'date',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(PayrollRun::class);
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(PayrollRun::class)->latestOfMany();
    }
}
