<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * Google Search Console data collector.
 * Stub implementation — GSC API integration to be added.
 */
class GscCollector implements SeoCollectorInterface
{
    public function key(): string
    {
        return 'gsc';
    }

    public function name(): string
    {
        return 'Google Search Console';
    }

    public function estimateCost(Collection $urls): int
    {
        return 0; // GSC is free
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
        return (int) config('seo.refresh_intervals.gsc', 24);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        // TODO: Implement GSC API integration
        return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['GSC collector not yet implemented']];
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function order(): int
    {
        return 15;
    }
}
