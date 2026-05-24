<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoKeyword;
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
        // 1 Call pro Domain statt pro Keyword
        $domainCount = $urls->pluck('domain')->unique()->filter()->count();
        $costPerDomain = config('seo.cost_estimates.labs_ranked', 10);

        return (int) ceil($domainCount * $costPerDomain);
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
        $today = now()->toDateString();

        // Nach Domain gruppieren
        $byDomain = [];
        foreach ($urls as $url) {
            if ($url->domain) {
                $byDomain[$url->domain][] = $url;
            }
        }

        $processed = 0;
        $totalCost = 0;
        $errors = [];
        $apiCalls = 0;

        foreach ($byDomain as $domain => $domainUrls) {
            try {
                // 1 API-Call pro Domain
                $rankedResults = $api->getRankedKeywords(
                    null,
                    $domain,
                    $settings->location_code,
                    $settings->resolveLanguageName(),
                    500,
                );
                $apiCalls++;
            } catch (\Throwable $e) {
                $errors[] = "Domain '{$domain}': {$e->getMessage()}";
                continue;
            }

            if (empty($rankedResults)) {
                continue;
            }

            // URL-Pfade vorbereiten
            $urlPaths = [];
            foreach ($domainUrls as $url) {
                $path = $url->path ?: (parse_url($url->url, PHP_URL_PATH) ?: '/');
                $urlPaths[$url->id] = rtrim(strtolower($path), '/');
            }

            // Eigene Domains für Competitor-Erkennung
            $ownDomains = collect($domainUrls)->pluck('domain')->unique()->filter()->values()->toArray();

            foreach ($rankedResults as $rk) {
                if (!$rk->position || !$rk->url) {
                    continue;
                }

                $keywordLower = strtolower(trim($rk->keyword));

                // Keyword upserten
                $updateData = array_filter([
                    'search_volume' => $rk->searchVolume,
                    'cpc_cents' => $rk->cpc !== null ? (int) round($rk->cpc * 100) : null,
                    'competition' => $rk->competition,
                    'keyword_difficulty' => $rk->keywordDifficulty,
                    'last_fetched_at' => now(),
                ], fn ($v) => $v !== null);

                $keyword = SeoKeyword::updateOrCreate(
                    ['team_id' => $settings->team_id, 'keyword' => $keywordLower],
                    $updateData,
                );

                // URL-Pfad-Match
                $rankedPath = rtrim(strtolower(parse_url($rk->url, PHP_URL_PATH) ?: '/'), '/');
                $matchedUrlId = $this->findBestPathMatch($rankedPath, $urlPaths);

                if (!$matchedUrlId) {
                    continue;
                }

                $matchedUrl = collect($domainUrls)->firstWhere('id', $matchedUrlId);
                if (!$matchedUrl) {
                    continue;
                }

                // Ranking History
                $lastHistory = SeoRankingHistory::where('url_id', $matchedUrl->id)
                    ->where('keyword_id', $keyword->id)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->where('tracked_at', '<', $today)
                    ->orderByDesc('tracked_at')
                    ->first();

                SeoRankingHistory::updateOrCreate(
                    [
                        'url_id' => $matchedUrl->id,
                        'keyword_id' => $keyword->id,
                        'tracked_at' => $today,
                        'search_engine' => 'google',
                        'device' => 'desktop',
                    ],
                    [
                        'position' => $rk->position,
                        'previous_position' => $lastHistory?->position,
                        'serp_features' => $rk->serpFeatures,
                    ],
                );

                // Pivot position updaten
                $matchedUrl->keywords()->syncWithoutDetaching([
                    $keyword->id => [
                        'position' => $rk->position,
                        'previous_position' => $lastHistory?->position,
                        'position_updated_at' => now(),
                    ],
                ]);

                $processed++;
            }
        }

        $costPerDomain = config('seo.cost_estimates.labs_ranked', 10);
        $totalCost = (int) ceil($apiCalls * $costPerDomain);

        // URL-Denormalisierung updaten
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

    protected function findBestPathMatch(string $rankedPath, array $urlPaths): ?int
    {
        $bestMatch = null;
        $bestLength = -1;

        foreach ($urlPaths as $urlId => $entityPath) {
            if ($rankedPath === $entityPath || str_starts_with($rankedPath, $entityPath . '/')) {
                if (strlen($entityPath) > $bestLength) {
                    $bestMatch = $urlId;
                    $bestLength = strlen($entityPath);
                }
            }
        }

        return $bestMatch;
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
