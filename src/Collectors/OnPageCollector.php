<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoUrl;

/**
 * On-page analysis collector.
 * Stub implementation — on-page crawling to be added.
 */
class OnPageCollector implements SeoCollectorInterface
{
    public function key(): string
    {
        return 'on_page';
    }

    public function name(): string
    {
        return 'On-Page Analyse';
    }

    public function estimateCost(Collection $urls): int
    {
        $costPerUrl = config('seo.cost_estimates.on_page', 15);

        return (int) ceil($urls->count() * $costPerUrl);
    }

    public function urlsDueForRefresh(Collection $urls): Collection
    {
        $intervalHours = $this->refreshIntervalHours();

        return $urls->filter(function (SeoUrl $url) use ($intervalHours) {
            return $url->isDueForRefresh($intervalHours);
        });
    }

    public function refreshIntervalHours(): int
    {
        return (int) config('seo.refresh_intervals.on_page', 336);
    }

    public function collect(SeoProject $project, Collection $urls): array
    {
        // TODO: Implement on-page crawling
        // 1. Crawl each URL (or use DataForSEO On-Page API)
        // 2. Extract title, meta description, h1, headings, word count
        // 3. Store in SeoUrlOnPage
        return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['On-page collector not yet implemented']];
    }

    public function isEnabled(): bool
    {
        return false; // Disabled until on-page crawling is implemented
    }

    public function order(): int
    {
        return 40;
    }
}
