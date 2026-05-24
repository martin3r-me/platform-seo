<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoTeamSettings;

class SeoKeywordService implements SeoKeywordServiceInterface
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
        protected SeoBudgetGuardService $budgetGuard,
    ) {}

    // =========================================================================
    // Contract: SeoKeywordServiceInterface
    // =========================================================================

    public function createProject(Team $team, User $user, array $data): ?object
    {
        return SeoTeamSettings::firstOrCreate(
            ['team_id' => $team->id],
            [
                'domain' => $data['domain'] ?? null,
                'budget_limit_cents' => $data['budget_limit_cents'] ?? null,
                'refresh_interval_hours' => $data['refresh_interval_hours'] ?? 168,
                'location_code' => $data['location_code'] ?? 2276,
                'language_code' => $data['language_code'] ?? null,
            ]
        );
    }

    public function attachKeywords(int $teamId, int $projectId, array $keywords): array
    {
        $attached = [];

        foreach ($keywords as $kw) {
            $keywordText = is_string($kw) ? $kw : ($kw['keyword'] ?? null);
            if (!$keywordText) {
                continue;
            }

            $keywordText = strtolower(trim($keywordText));

            $keyword = SeoKeyword::firstOrCreate(
                ['team_id' => $teamId, 'keyword' => $keywordText],
                [
                    'search_intent' => is_array($kw) ? ($kw['search_intent'] ?? null) : null,
                    'topic' => is_array($kw) ? ($kw['topic'] ?? null) : null,
                ]
            );

            $attached[] = $keyword;
        }

        return $attached;
    }

    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->first();

        $keywords = SeoKeyword::where('team_id', $teamId)->get();

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0];
        }

        // Filter to keywords that need refreshing
        $staleKeywords = $keywords->filter(function ($kw) {
            return !$kw->last_fetched_at || $kw->last_fetched_at->lt(now()->subDays(7));
        });

        if ($staleKeywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0, 'skipped' => $keywords->count()];
        }

        if (!$settings) {
            return ['fetched' => 0, 'cost_cents' => 0, 'error' => 'Team settings required for budget + API resolution'];
        }

        $keywordTexts = $staleKeywords->pluck('keyword')->toArray();
        $estimatedCost = $this->estimateCost('search_volume', count($keywordTexts));

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $volumeResults = $api->getSearchVolume($user, $keywordTexts, $settings->location_code, $settings->resolveLanguageName());

        if (empty($volumeResults)) {
            return ['fetched' => 0, 'cost_cents' => 0];
        }

        $metricsMap = [];
        foreach ($volumeResults as $result) {
            $metricsMap[$result->keyword] = $result;
        }

        $fetchedCount = 0;
        foreach ($staleKeywords as $keyword) {
            if (isset($metricsMap[$keyword->keyword])) {
                $m = $metricsMap[$keyword->keyword];

                $keyword->update([
                    'search_volume' => $m->searchVolume ?? $keyword->search_volume,
                    'cpc_cents' => $m->cpcHigh !== null ? (int) round($m->cpcHigh * 100) : $keyword->cpc_cents,
                    'last_fetched_at' => now(),
                    'dataforseo_raw' => $m->toArray(),
                ]);

                $fetchedCount++;
            }
        }

        $actualCost = $this->estimateCost('search_volume', $fetchedCount);
        $this->budgetGuard->recordCost($settings, 'fetch_metrics', $fetchedCount, $actualCost, $user);

        $settings->update(['next_refresh_at' => now()->addHours($settings->refresh_interval_hours)]);

        return ['fetched' => $fetchedCount, 'cost_cents' => $actualCost];
    }

    public function fetchRankings(int $teamId, ?User $user = null): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $keywords = SeoKeyword::where('team_id', $teamId)->get();

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0];
        }

        $estimatedCost = $this->estimateCost('serp', $keywords->count());

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $projectDomain = $settings->domain ? parse_url($settings->domain, PHP_URL_HOST) ?? $settings->domain : null;
        $fetchedCount = 0;
        $positionSnapshots = 0;
        $competitorEntries = [];

        foreach ($keywords as $keyword) {
            $serpResults = $api->getSerpOrganic($user, $keyword->keyword, $settings->location_code, $settings->resolveLanguageName());

            if (empty($serpResults)) {
                continue;
            }

            $ownPosition = null;
            $ownUrl = null;
            $serpFeatures = [];
            foreach ($serpResults as $serpResult) {
                $serpFeatures[] = $serpResult->domain;
                if ($projectDomain && str_contains($serpResult->url ?? '', $projectDomain)) {
                    $ownPosition = $serpResult->position;
                    $ownUrl = $serpResult->url;
                }
            }

            if ($ownPosition !== null) {
                $lastSnapshot = SeoKeywordPosition::where('keyword_id', $keyword->id)
                    ->where('team_id', $teamId)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->orderByDesc('tracked_at')
                    ->first();

                SeoKeywordPosition::create([
                    'keyword_id' => $keyword->id,
                    'team_id' => $teamId,
                    'position' => $ownPosition,
                    'previous_position' => $lastSnapshot?->position,
                    'ranked_url' => $ownUrl,
                    'serp_features' => array_unique(array_slice($serpFeatures, 0, 10)),
                    'tracked_at' => now()->toDateString(),
                    'search_engine' => 'google',
                    'device' => 'desktop',
                ]);
                $positionSnapshots++;
            }

            foreach (array_slice($serpResults, 0, 10) as $serpResult) {
                if ($serpResult->domain) {
                    $competitorEntries[$serpResult->domain] = ($competitorEntries[$serpResult->domain] ?? 0) + 1;
                }
            }

            $fetchedCount++;
        }

        $actualCost = $this->estimateCost('serp', $fetchedCount);
        $this->budgetGuard->recordCost($settings, 'fetch_rankings', $fetchedCount, $actualCost, $user);

        $settings->update(['next_refresh_at' => now()->addHours($settings->refresh_interval_hours)]);

        return [
            'fetched' => $fetchedCount,
            'cost_cents' => $actualCost,
            'position_snapshots' => $positionSnapshots,
            'top_competitors' => collect($competitorEntries)
                ->sortDesc()
                ->take(20)
                ->map(fn($count, $domain) => ['domain' => $domain, 'keyword_overlaps' => $count])
                ->values()
                ->toArray(),
        ];
    }

    public function getKeywordsForProject(int $teamId): Collection
    {
        return SeoKeyword::where('team_id', $teamId)->with('cluster')->get();
    }

    public function getKeywordSummary(int $teamId): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)->get();
        $clustersCount = SeoKeywordCluster::where('team_id', $teamId)->count();

        return [
            'total_keywords' => $keywords->count(),
            'clusters_count' => $clustersCount,
            'avg_search_volume' => (int) $keywords->avg('search_volume'),
            'avg_difficulty' => (int) $keywords->avg('keyword_difficulty'),
            'total_search_volume' => (int) $keywords->sum('search_volume'),
            'intents' => $keywords->pluck('search_intent')->filter()->countBy()->toArray(),
            'with_metrics' => $keywords->whereNotNull('search_volume')->count(),
            'without_metrics' => $keywords->whereNull('search_volume')->count(),
        ];
    }

    // =========================================================================
    // Internal methods (not part of contract)
    // =========================================================================

    public function addKeyword(int $teamId, array $data, ?User $user = null): SeoKeyword
    {
        $keywordText = strtolower(trim($data['keyword']));

        $attributes = array_filter([
            'cluster_id' => $data['cluster_id'] ?? null,
            'search_volume' => $data['search_volume'] ?? null,
            'keyword_difficulty' => $data['keyword_difficulty'] ?? null,
            'cpc_cents' => $data['cpc_cents'] ?? null,
            'competition' => $data['competition'] ?? null,
            'search_intent' => $data['search_intent'] ?? null,
            'topic' => $data['topic'] ?? null,
        ], fn ($v) => $v !== null);

        return SeoKeyword::updateOrCreate(
            ['team_id' => $teamId, 'keyword' => $keywordText],
            $attributes,
        );
    }

    public function addKeywords(int $teamId, array $keywordsData, ?User $user = null): Collection
    {
        $keywords = collect();

        foreach ($keywordsData as $data) {
            $keywords->push($this->addKeyword($teamId, $data, $user));
        }

        return $keywords;
    }

    public function updateKeyword(SeoKeyword $keyword, array $data): SeoKeyword
    {
        $keywordFields = [];

        foreach ([
            'keyword', 'cluster_id', 'search_volume', 'keyword_difficulty',
            'cpc_cents', 'competition', 'search_intent', 'topic',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $keywordFields[$field] = $data[$field];
            }
        }

        if (!empty($keywordFields)) {
            $keyword->update($keywordFields);
        }

        return $keyword->fresh();
    }

    public function moveToCluster(SeoKeyword $keyword, ?int $clusterId): SeoKeyword
    {
        $keyword->update(['cluster_id' => $clusterId]);
        return $keyword->fresh();
    }

    public function deleteKeyword(SeoKeyword $keyword): void
    {
        $keyword->delete();
    }

    public function createCluster(int $teamId, array $data, ?User $user = null): SeoKeywordCluster
    {
        return SeoKeywordCluster::create([
            'team_id' => $teamId,
            'name' => $data['name'] ?? 'Neuer Cluster',
            'description' => $data['description'] ?? null,
            'color' => $data['color'] ?? null,
        ]);
    }

    public function updateCluster(SeoKeywordCluster $cluster, array $data): SeoKeywordCluster
    {
        $updateData = [];

        foreach (['name', 'description', 'color'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $cluster->update($updateData);
        }

        return $cluster->fresh();
    }

    public function deleteCluster(SeoKeywordCluster $cluster): void
    {
        $cluster->delete();
    }

    /**
     * Discover keyword suggestions via Labs API.
     */
    public function discoverKeywords(SeoTeamSettings $settings, array $seedKeywords, ?User $user = null, int $limit = 100): array
    {
        if (empty($seedKeywords)) {
            return ['keywords' => [], 'cost_cents' => 0];
        }

        $estimatedCost = $this->estimateCost('labs_suggestions', 1);

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['keywords' => [], 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $labsResults = $api->getLabsKeywordSuggestions($user, $seedKeywords, $settings->location_code, $settings->resolveLanguageName(), $limit);

        $keywords = array_map(fn($r) => $r->toArray(), $labsResults);

        $actualCost = $this->estimateCost('labs_suggestions', 1);
        $this->budgetGuard->recordCost($settings, 'discover_keywords', count($keywords), $actualCost, $user);

        return ['keywords' => $keywords, 'cost_cents' => $actualCost];
    }

    /**
     * Discover keywords a domain ranks for.
     */
    public function discoverFromDomain(SeoTeamSettings $settings, string $domain, ?User $user = null, int $limit = 100): array
    {
        $estimatedCost = $this->estimateCost('labs_ranked', 1);

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['keywords' => [], 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $rankedResults = $api->getRankedKeywords($user, $domain, $settings->location_code, $settings->resolveLanguageName(), $limit);

        $keywords = array_map(fn($r) => $r->toArray(), $rankedResults);

        $actualCost = $this->estimateCost('labs_ranked', 1);
        $this->budgetGuard->recordCost($settings, 'discover_from_domain', count($keywords), $actualCost, $user);

        return ['keywords' => $keywords, 'cost_cents' => $actualCost];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function resolveApiService(SeoTeamSettings $settings): DataForSeoApiService
    {
        return $this->dataForSeoApi->forConnection($settings->resolveConnectionId());
    }

    protected function estimateCost(string $action, int $count): int
    {
        $costPerUnit = config("seo.cost_estimates.{$action}", 5);

        return (int) ceil($count * $costPerUnit);
    }
}
