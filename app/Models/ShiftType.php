<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShiftType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'time_in',
        'time_out',
        'is_rest_day',
    ];

    protected function casts(): array
    {
        return [
            'is_rest_day' => 'boolean',
        ];
    }
}
