<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

class KeywordMetricsCollector implements SeoCollectorInterface
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

    public function key(): string
    {
        return 'keyword_metrics';
    }

    public function name(): string
    {
        return 'Keyword-Metriken';
    }

    public function estimateCost(Collection $urls): int
    {
        $keywordCount = 0;
        foreach ($urls as $url) {
            $keywordCount += $url->keywords()->count();
        }

        $costPerKeyword = config('seo.cost_estimates.search_volume', 5);

        return (int) ceil($keywordCount * $costPerKeyword);
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
        return (int) config('seo.refresh_intervals.keyword_metrics', 168);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $api = $this->dataForSeoApi->forConnection($settings->resolveConnectionId());
        $processed = 0;
        $totalCost = 0;
        $errors = [];

        // Collect all keywords from all URLs that need metrics refresh
        $keywordsToFetch = collect();
        foreach ($urls as $url) {
            $keywords = $url->keywords()->whereNull('seo_keywords.last_fetched_at')
                ->orWhere('seo_keywords.last_fetched_at', '<', now()->subDays(7))
                ->get();
            foreach ($keywords as $kw) {
                $keywordsToFetch->put($kw->id, $kw);
            }
        }

        if ($keywordsToFetch->isEmpty()) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => []];
        }

        $keywordTexts = $keywordsToFetch->pluck('keyword')->toArray();

        try {
            $volumeResults = $api->getSearchVolume(
                null,
                $keywordTexts,
                $settings->location_code,
                $settings->resolveLanguageName(),
            );
        } catch (\Throwable $e) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => [$e->getMessage()]];
        }

        if (empty($volumeResults)) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => []];
        }

        $metricsMap = [];
        foreach ($volumeResults as $result) {
            $metricsMap[$result->keyword] = $result;
        }

        foreach ($keywordsToFetch as $keyword) {
            if (isset($metricsMap[$keyword->keyword])) {
                $m = $metricsMap[$keyword->keyword];
                $keyword->update([
                    'search_volume' => $m->searchVolume ?? $keyword->search_volume,
                    'cpc_cents' => $m->cpcHigh !== null ? (int) round($m->cpcHigh * 100) : $keyword->cpc_cents,
                    'last_fetched_at' => now(),
                    'dataforseo_raw' => $m->toArray(),
                ]);
                $processed++;
            }
        }

        $costPerKeyword = config('seo.cost_estimates.search_volume', 5);
        $totalCost = (int) ceil($processed * $costPerKeyword);

        // Update URL denormalized fields + collector timestamp
        foreach ($urls as $url) {
            $this->updateUrlMetrics($url);
            $url->setCollectorTimestamp($this->key());
        }

        return ['processed' => $processed, 'cost_cents' => $totalCost, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 10;
    }

    protected function updateUrlMetrics(SeoUrl $url): void
    {
        $keywords = $url->keywords;
        $url->update([
            'keyword_count' => $keywords->count(),
            'total_search_volume' => $keywords->sum('search_volume'),
        ]);
    }
}
