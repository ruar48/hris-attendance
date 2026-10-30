<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Throwable;

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

    /**
     * The working shift with these hours, created when no shift code has
     * them yet. Accepts "6:00 AM" (Manning schedule times) or "06:00".
     */
    public static function forTimes(string $timeIn, string $timeOut, ?string $name = null): ?self
    {
        $in = static::toDbTime($timeIn);
        $out = static::toDbTime($timeOut);

        if ($in === null || $out === null) {
            return null;
        }

        return static::query()
            ->where('is_rest_day', false)
            ->where('time_in', $in)
            ->where('time_out', $out)
            ->first()
            ?? static::query()->create([
                'code' => static::nextCode(),
                'name' => $name ?? Carbon::parse($in)->format('g:i A').' - '.Carbon::parse($out)->format('g:i A'),
                'time_in' => $in,
                'time_out' => $out,
                'is_rest_day' => false,
            ]);
    }

    /**
     * The shift code that marks a day off, created on first use.
     */
    public static function restDay(): self
    {
        return static::query()->where('is_rest_day', true)->orderBy('code')->first()
            ?? static::query()->create([
                'code' => static::nextCode(),
                'name' => 'Day Off',
                'is_rest_day' => true,
            ]);
    }

    /**
     * "6:00 AM", "06:00" or "06:00:00" → "06:00:00"; null when unreadable.
     */
    public static function toDbTime(?string $time): ?string
    {
        if (blank($time)) {
            return null;
        }

        try {
            return Carbon::parse($time)->format('H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    protected static function nextCode(): int
    {
        return (int) static::query()->max('code') + 1;
    }
}
