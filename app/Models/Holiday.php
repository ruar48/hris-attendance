<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Holiday extends Model
{
    protected $fillable = [
        'name',
        // Empty: nationwide. Otherwise the one site (Manning work location) it applies to.
        'site',
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
     * Whether this holiday applies to people at a site: a nationwide holiday
     * applies everywhere, a local one only at its own site.
     */
    public function appliesTo(?string $site): bool
    {
        return $this->site === null || $this->site === $site;
    }

    /**
     * Find the holiday that applies on a given calendar date at a site
     * (null: nationwide holidays only). A site's own holiday wins over a
     * nationwide one, and one-time dates win over recurring month/day rules.
     */
    public static function findForDate(CarbonInterface $date, ?string $site = null): ?self
    {
        $forSite = fn ($query) => $query
            ->where(fn ($q) => $q->whereNull('site')->when($site, fn ($q2) => $q2->orWhere('site', $site)))
            // Local (site) holidays first, then nationwide.
            ->orderByRaw('site is null');

        $oneTime = static::query()
            ->whereDate('date', $date->toDateString())
            ->tap($forSite)
            ->first();

        if ($oneTime) {
            return $oneTime;
        }

        return static::query()
            ->where('is_recurring', true)
            ->where('month', (int) $date->format('n'))
            ->where('day', (int) $date->format('j'))
            ->tap($forSite)
            ->first();
    }

    /**
     * @return Collection<string, self> keyed by Y-m-d
     */
    public static function mapForPeriod(CarbonInterface $start, CarbonInterface $end, ?string $site = null): Collection
    {
        $map = collect();

        $cursor = \Carbon\Carbon::parse($start)->startOfDay();
        $last = \Carbon\Carbon::parse($end)->startOfDay();

        while ($cursor->lte($last)) {
            $holiday = static::findForDate($cursor, $site);
            if ($holiday) {
                $map->put($cursor->toDateString(), $holiday);
            }
            $cursor->addDay();
        }

        return $map;
    }
}
