<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;

class SerpRankingCollector implements SeoCollectorInterface
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

    public function key(): string
    {
        return 'serp_ranking';
    }

    public function name(): string
    {
        return 'SERP-Rankings';
    }

    public function estimateCost(Collection $urls): int
    {
        $keywordCount = 0;
        foreach ($urls as $url) {
            $keywordCount += $url->keywords()->count();
        }

        $costPerKeyword = config('seo.cost_estimates.serp', 10);

        return (int) ceil($keywordCount * $costPerKeyword);
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
        return (int) config('seo.refresh_intervals.serp_ranking', 168);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $api = $this->dataForSeoApi->forConnection($settings->resolveConnectionId());

        // Eigene Domains aus registrierten URLs ableiten
        $ownDomains = SeoUrl::where('team_id', $settings->team_id)
            ->where('is_own', true)
            ->pluck('domain')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $processed = 0;
        $totalCost = 0;
        $errors = [];

        foreach ($urls as $url) {
            $keywords = $url->keywords;
            if ($keywords->isEmpty()) {
                continue;
            }

            foreach ($keywords as $keyword) {
                try {
                    $serpResults = $api->getSerpOrganic(
                        null,
                        $keyword->keyword,
                        $settings->location_code,
                        $settings->resolveLanguageName(),
                    );
                } catch (\Throwable $e) {
                    $errors[] = "SERP fuer '{$keyword->keyword}': {$e->getMessage()}";
                    continue;
                }

                if (empty($serpResults)) {
                    continue;
                }

                $ownPosition = null;
                $serpFeatures = [];
                foreach ($serpResults as $serpResult) {
                    $serpFeatures[] = $serpResult->domain;
                    if (!empty($ownDomains) && $serpResult->url) {
                        foreach ($ownDomains as $ownDomain) {
                            if (str_contains($serpResult->url, $ownDomain)) {
                                $ownPosition = $serpResult->position;
                                break;
                            }
                        }
                    }
                }

                // Get previous position from ranking history
                $lastHistory = SeoRankingHistory::where('url_id', $url->id)
                    ->where('keyword_id', $keyword->id)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->orderByDesc('tracked_at')
                    ->first();

                if ($ownPosition !== null) {
                    // Store ranking history
                    SeoRankingHistory::updateOrCreate(
                        [
                            'url_id' => $url->id,
                            'keyword_id' => $keyword->id,
                            'tracked_at' => now()->toDateString(),
                            'search_engine' => 'google',
                            'device' => 'desktop',
                        ],
                        [
                            'position' => $ownPosition,
                            'previous_position' => $lastHistory?->position,
                            'serp_features' => array_unique(array_slice($serpFeatures, 0, 10)),
                        ],
                    );

                    // Update pivot position
                    $url->keywords()->updateExistingPivot($keyword->id, [
                        'position' => $ownPosition,
                        'previous_position' => $lastHistory?->position,
                        'position_updated_at' => now(),
                    ]);
                }

                // Detect competitor URLs from SERP
                foreach (array_slice($serpResults, 0, 10) as $serpResult) {
                    if ($serpResult->url && !in_array($serpResult->domain, $ownDomains, true)) {
                        $this->trackCompetitorUrl($settings, $url, $serpResult);
                    }
                }

                $processed++;
            }
        }

        $costPerKeyword = config('seo.cost_estimates.serp', 10);
        $totalCost = (int) ceil($processed * $costPerKeyword);

        // Update URL denormalized visibility
        foreach ($urls as $url) {
            $this->updateUrlVisibility($url);
            $url->update(['last_crawled_at' => now()]);
        }

        return ['processed' => $processed, 'cost_cents' => $totalCost, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 20;
    }

    protected function trackCompetitorUrl(SeoTeamSettings $settings, SeoUrl $ownUrl, object $serpResult): void
    {
        $competitorUrl = SeoUrl::firstOrCreate(
            [
                'team_id' => $settings->team_id,
                'url_hash' => SeoUrl::hashUrl($serpResult->url),
            ],
            [
                'url' => SeoUrl::normalizeUrl($serpResult->url),
                'domain' => $serpResult->domain,
                'is_own' => false,
                'priority' => config('seo.priority.competitor_url_default', 30),
            ],
        );

        SeoUrlRelationship::updateOrCreate(
            [
                'source_url_id' => $ownUrl->id,
                'target_url_id' => $competitorUrl->id,
                'type' => 'competitor',
            ],
            [
                'team_id' => $settings->team_id,
                'detected_at' => now(),
            ],
        );
    }

    protected function updateUrlVisibility(SeoUrl $url): void
    {
        $ctrModel = [1 => 0.316, 2 => 0.158, 3 => 0.094, 4 => 0.06, 5 => 0.06];

        $visibilityScore = 0;
        $keywords = $url->keywords;

        foreach ($keywords as $keyword) {
            $position = $keyword->pivot->position;
            if ($position === null || $position < 1) {
                continue;
            }
            $ctr = $ctrModel[$position] ?? ($position <= 10 ? 0.03 : 0.01);
            $visibilityScore += ($keyword->search_volume ?? 0) * $ctr;
        }

        $url->update([
            'visibility_score' => $visibilityScore,
            'keyword_count' => $keywords->count(),
            'total_search_volume' => $keywords->sum('search_volume'),
        ]);
    }
}
