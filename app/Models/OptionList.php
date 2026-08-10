<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionList extends Model
{
    protected $fillable = [
        'category',
        'value',
        'label',
        'sort_order',
        'time_in',
        'time_out',
    ];

    /** @var list<string> The categories editable from the Source Data tab. */
    public const CATEGORIES = [
        'employee_status',
        'employment_status',
        'position',
        'department',
        'job_level',
        'shift_schedule',
    ];

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * All values currently defined for a category, for validation rules.
     *
     * @return list<string>
     */
    public static function values(string $category): array
    {
        return static::query()
            ->category($category)
            ->orderBy('sort_order')
            ->pluck('value')
            ->all();
    }

    /**
     * Every category's rows, grouped, for the Source Data tab and shared
     * Inertia props.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public static function grouped(): array
    {
        $rows = static::query()->orderBy('sort_order')->get();

        return collect(self::CATEGORIES)
            ->mapWithKeys(fn (string $category) => [
                $category => $rows->where('category', $category)
                    ->map(fn (OptionList $row) => [
                        'id' => $row->id,
                        'value' => $row->value,
                        'label' => $row->label,
                        'time_in' => $row->time_in,
                        'time_out' => $row->time_out,
                    ])
                    ->values()
                    ->all(),
            ])
            ->all();
    }
}
