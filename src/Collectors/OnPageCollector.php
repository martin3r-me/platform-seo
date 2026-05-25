<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlOnPage;

class OnPageCollector implements SeoCollectorInterface
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

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
            return $url->isDueForCollector($this->key(), $intervalHours);
        });
    }

    public function refreshIntervalHours(): int
    {
        return (int) config('seo.refresh_intervals.on_page', 336);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $api = $this->dataForSeoApi->forConnection($settings->resolveConnectionId());

        $processed = 0;
        $totalCost = 0;
        $errors = [];

        foreach ($urls as $url) {
            try {
                $results = $api->getOnPageInstant(null, $url->url);
            } catch (\Throwable $e) {
                $errors[] = "OnPage für '{$url->url}': {$e->getMessage()}";
                continue;
            }

            if (empty($results)) {
                continue;
            }

            $result = $results[0];

            // Headings zusammenführen (h2, h3)
            $headings = [];
            foreach ($result->h2 as $h) {
                $headings[] = ['level' => 2, 'text' => $h];
            }
            foreach ($result->h3 as $h) {
                $headings[] = ['level' => 3, 'text' => $h];
            }

            // Issues aus checks extrahieren
            $issues = [];
            if (!empty($result->checks)) {
                foreach ($result->checks as $checkName => $checkValue) {
                    if ($checkValue === false) {
                        $issues[] = [
                            'check' => $checkName,
                            'status' => 'fail',
                        ];
                    }
                }
            }

            // Overall Score: onpageScore von API (0-100) oder aus Issues berechnen
            $overallScore = $result->onpageScore !== null
                ? (int) round($result->onpageScore)
                : null;

            SeoUrlOnPage::updateOrCreate(
                ['url_id' => $url->id],
                [
                    'title' => $result->title,
                    'meta_description' => $result->description,
                    'h1' => $result->h1[0] ?? null,
                    'headings' => $headings,
                    'word_count' => $result->wordCount,
                    'page_speed_score' => $result->loadTime !== null
                        ? $this->loadTimeToScore($result->loadTime)
                        : null,
                    'mobile_score' => null,
                    'structured_data_types' => [],
                    'issues' => $issues,
                    'overall_score' => $overallScore,
                    'analyzed_at' => now(),
                ],
            );

            // HTTP-Status + Collector-Timestamp setzen
            $urlUpdates = [];
            if ($result->statusCode !== null) {
                $urlUpdates['http_status'] = $result->statusCode;
            }
            $url->update($urlUpdates);
            $url->setCollectorTimestamp($this->key());
            $processed++;
        }

        $costPerUrl = config('seo.cost_estimates.on_page', 15);
        $totalCost = (int) ceil($processed * $costPerUrl);

        return ['processed' => $processed, 'cost_cents' => $totalCost, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 40;
    }

    /**
     * Convert load time (seconds) to a 0-100 score.
     */
    protected function loadTimeToScore(float $loadTime): int
    {
        return match (true) {
            $loadTime <= 1.0 => 100,
            $loadTime <= 2.0 => 90,
            $loadTime <= 3.0 => 75,
            $loadTime <= 5.0 => 50,
            $loadTime <= 8.0 => 30,
            default => 10,
        };
    }
}
