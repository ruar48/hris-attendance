<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BiometricLog extends Model
{
    protected $fillable = [
        'employee_id',
        'biometric_device_id',
        'punched_at',
        'punch_type',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'punched_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(BiometricDevice::class, 'biometric_device_id');
    }
}
