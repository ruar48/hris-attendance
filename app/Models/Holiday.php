<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        'is_recurring',
        'month',
        'day',
        'date',
        'type',
        'pay_multiplier',
    ];

    protected function casts(): array
    {
        return [
            'is_recurring' => 'boolean',
            'date' => 'date',
            'pay_multiplier' => 'decimal:2',
        ];
    }

    public function displayLabel(): string
    {
        if ($this->is_recurring && $this->month && $this->day) {
            $monthName = date('F', mktime(0, 0, 0, (int) $this->month, 1));

            return "Every year · {$monthName} {$this->day}";
        }

        return $this->date?->format('M d, Y') ?? '—';
    }

    /**
     * Find the holiday that applies on a given calendar date.
     * One-time dates win over recurring month/day rules if both exist.
     */
    public static function findForDate(CarbonInterface $date): ?self
    {
        $oneTime = static::query()
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($oneTime) {
            return $oneTime;
        }

        return static::query()
            ->where('is_recurring', true)
            ->where('month', (int) $date->format('n'))
            ->where('day', (int) $date->format('j'))
            ->first();
    }

    /**
     * @return Collection<string, self> keyed by Y-m-d
     */
    public static function mapForPeriod(CarbonInterface $start, CarbonInterface $end): Collection
    {
        $map = collect();

        $cursor = \Carbon\Carbon::parse($start)->startOfDay();
        $last = \Carbon\Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $holiday = static::findForDate($cursor);
            if ($holiday) {
                $map->put($cursor->toDateString(), $holiday);
            }
            $cursor->addDay();
        }

        return $map;
    }
}
