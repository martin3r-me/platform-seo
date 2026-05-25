<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * Backlink data collector.
 * Stub implementation — backlink API integration to be added.
 */
class BacklinkCollector implements SeoCollectorInterface
{
    public function key(): string
    {
        return 'backlinks';
    }

    public function name(): string
    {
        return 'Backlinks';
    }

    public function estimateCost(Collection $urls): int
    {
        $costPerUrl = config('seo.cost_estimates.backlinks', 15);

        return (int) ceil($urls->count() * $costPerUrl);
    }

    public function urlsDueForRefresh(Collection $urls): Collection
    {
        $intervalHours = $this->refreshIntervalHours();

        return $urls->filter(function (SeoUrl $url) use ($intervalHours) {
            return $url->isDueForCollector($this->key(), $intervalHours);
        });
    }

    public function refreshIntervalHours(): int
    {
        return (int) config('seo.refresh_intervals.backlinks', 336);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        // TODO: Implement backlink API integration
        return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Backlink collector not yet implemented']];
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function order(): int
    {
        return 30;
    }
}
