<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PayrollSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Schedule
            'shift_start' => '08:00',
            'shift_end' => '17:00',
            'late_grace_minutes' => '5',

            // Undertime: leaving before shift_end
            'undertime_grace_minutes' => '5',
            'undertime_deduction_unit' => 'exact', // exact | 30_minutes | hour

            // Overtime: work beyond shift_end counts as OT only after minimum minutes
            'ot_minimum_minutes' => '60',
            'ot_rate_multiplier' => '1.25',
            'ot_use_employee_hourly' => '1',
            'ot_fixed_hourly_rate' => '0',

            // Sunday route pay (used when employee sunday_route_rate is empty/0)
            'sunday_route_default_amount' => '800',
            'sunday_route_use_employee_rate' => '1',

            // Holiday
            'holiday_pay_multiplier' => '2.00',

            // Cash advance deduction per payroll run
            'cash_advance_max_deduction' => '1000',
            'cash_advance_auto_deduct' => '1',

            // Government benefits
            'sss_rate' => '0.05',
            'philhealth_rate' => '0.025',
            'pagibig_fixed' => '200',

            // Pay schedule: kinsenas (semi-monthly / quincena) only
            'payroll_frequency' => 'kinsenas',

            // 13th month
            'auto_13th_month' => '1',
            'thirteenth_month_divisor' => '12', // total basic earned in the year ÷ this
            // Release the 13th month in two instalments rather than all in
            // December. The first lands after the 2nd cutoff of the month below.
            'thirteenth_month_split' => '1',
            'thirteenth_month_first_month' => '6', // June
        ];
    }

    public static function isKinsenas(): bool
    {
        return true;
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $fallback = $default ?? (static::defaults()[$key] ?? null);

        return Cache::remember("payroll_setting_{$key}", 3600, function () use ($key, $fallback) {
            $setting = static::query()->where('key', $key)->first();

            return $setting?->value ?? $fallback;
        });
    }

    public static function setValue(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        Cache::forget("payroll_setting_{$key}");
    }

    /**
     * @return array<string, string>
     */
    public static function allValues(): array
    {
        $stored = static::query()->pluck('value', 'key')->all();

        return array_merge(static::defaults(), $stored);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function syncMany(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, static::defaults())) {
                continue;
            }

            static::setValue($key, $value);
        }
    }

    public static function float(string $key): float
    {
        return (float) static::getValue($key);
    }

    public static function int(string $key): int
    {
        return (int) static::getValue($key);
    }

    public static function bool(string $key): bool
    {
        return in_array((string) static::getValue($key), ['1', 'true', 'yes', 'on'], true);
    }
}
