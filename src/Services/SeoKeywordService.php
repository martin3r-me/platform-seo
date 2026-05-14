<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoProject;

class SeoKeywordService
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
        protected SeoBudgetGuardService $budgetGuard,
    ) {}

    public function addKeyword(SeoProject $project, array $data, ?User $user = null): SeoKeyword
    {
        return SeoKeyword::create([
            'project_id' => $project->id,
            'team_id' => $project->team_id,
            'cluster_id' => $data['cluster_id'] ?? null,
            'keyword' => $data['keyword'],
            'search_volume' => $data['search_volume'] ?? null,
            'keyword_difficulty' => $data['keyword_difficulty'] ?? null,
            'cpc_cents' => $data['cpc_cents'] ?? null,
            'competition' => $data['competition'] ?? null,
            'search_intent' => $data['search_intent'] ?? null,
            'topic' => $data['topic'] ?? null,
            'priority' => $data['priority'] ?? null,
            'notes' => $data['notes'] ?? null,
            'content_status' => $data['content_status'] ?? null,
            'target_url' => $data['target_url'] ?? null,
            'published_url' => $data['published_url'] ?? null,
        ]);
    }

    public function addKeywords(SeoProject $project, array $keywordsData, ?User $user = null): Collection
    {
        $keywords = collect();

        foreach ($keywordsData as $data) {
            $keywords->push($this->addKeyword($project, $data, $user));
        }

        return $keywords;
    }

    public function updateKeyword(SeoKeyword $keyword, array $data): SeoKeyword
    {
        $updateData = [];

        foreach ([
            'keyword', 'cluster_id', 'search_volume', 'keyword_difficulty',
            'cpc_cents', 'competition', 'search_intent', 'topic',
            'priority', 'notes', 'content_status', 'target_url', 'published_url',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $keyword->update($updateData);
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

    public function createCluster(SeoProject $project, array $data, ?User $user = null): SeoKeywordCluster
    {
        return SeoKeywordCluster::create([
            'project_id' => $project->id,
            'team_id' => $project->team_id,
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
     * Fetch keyword metrics via DataForSEO.
     */
    public function fetchMetrics(SeoProject $project, ?Collection $keywords = null, ?User $user = null): array
    {
        $keywords = $keywords ?? $project->keywords;

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0];
        }

        $user = $user ?? $project->user;
        $keywordTexts = $keywords->pluck('keyword')->toArray();
        $estimatedCost = $this->estimateCost('search_volume', count($keywordTexts));

        if (!$this->budgetGuard->canFetch($project, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($project);
        $volumeResults = $api->getSearchVolume($user, $keywordTexts, $project->location_code, $project->language_code);

        if (empty($volumeResults)) {
            return ['fetched' => 0, 'cost_cents' => 0];
        }

        $metricsMap = [];
        foreach ($volumeResults as $result) {
            $metricsMap[$result->keyword] = $result;
        }

        $fetchedCount = 0;
        foreach ($keywords as $keyword) {
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
        $this->budgetGuard->recordCost($project, 'fetch_metrics', $fetchedCount, $actualCost, $user);

        $project->update(['next_refresh_at' => now()->addHours($project->refresh_interval_hours)]);

        return ['fetched' => $fetchedCount, 'cost_cents' => $actualCost];
    }

    /**
     * Fetch SERP rankings and create position snapshots.
     */
    public function fetchRankings(SeoProject $project, ?User $user = null): array
    {
        $keywords = $project->keywords;

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0];
        }

        $user = $user ?? $project->user;
        $estimatedCost = $this->estimateCost('serp', $keywords->count());

        if (!$this->budgetGuard->canFetch($project, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($project);
        $fetchedCount = 0;
        $positionSnapshots = 0;
        $competitorEntries = [];

        foreach ($keywords as $keyword) {
            $serpResults = $api->getSerpOrganic($user, $keyword->keyword, $project->location_code, $project->language_code);

            if (empty($serpResults)) {
                continue;
            }

            $ownPosition = null;
            $serpFeatures = [];
            foreach ($serpResults as $serpResult) {
                $serpFeatures[] = $serpResult->domain;
                if ($keyword->target_url && str_contains($serpResult->url ?? '', parse_url($keyword->target_url, PHP_URL_HOST) ?? '')) {
                    $ownPosition = $serpResult->position;
                }
            }

            if ($ownPosition !== null) {
                $lastSnapshot = SeoKeywordPosition::where('keyword_id', $keyword->id)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->orderByDesc('tracked_at')
                    ->first();

                SeoKeywordPosition::create([
                    'keyword_id' => $keyword->id,
                    'position' => $ownPosition,
                    'previous_position' => $lastSnapshot?->position,
                    'serp_features' => array_unique(array_slice($serpFeatures, 0, 10)),
                    'tracked_at' => now()->toDateString(),
                    'search_engine' => 'google',
                    'device' => 'desktop',
                ]);
                $positionSnapshots++;

                $keyword->update(['position' => $ownPosition, 'ranked_url' => $keyword->target_url]);
            }

            foreach (array_slice($serpResults, 0, 10) as $serpResult) {
                if ($serpResult->domain) {
                    $competitorEntries[$serpResult->domain] = ($competitorEntries[$serpResult->domain] ?? 0) + 1;
                }
            }

            $fetchedCount++;
        }

        $actualCost = $this->estimateCost('serp', $fetchedCount);
        $this->budgetGuard->recordCost($project, 'fetch_rankings', $fetchedCount, $actualCost, $user);

        $project->update(['next_refresh_at' => now()->addHours($project->refresh_interval_hours)]);

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

    /**
     * Discover keyword suggestions via Labs API.
     */
    public function discoverKeywords(SeoProject $project, array $seedKeywords, ?User $user = null, int $limit = 100): array
    {
        if (empty($seedKeywords)) {
            return ['keywords' => [], 'cost_cents' => 0];
        }

        $user = $user ?? $project->user;
        $estimatedCost = $this->estimateCost('labs_suggestions', 1);

        if (!$this->budgetGuard->canFetch($project, $estimatedCost)) {
            return ['keywords' => [], 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($project);
        $labsResults = $api->getLabsKeywordSuggestions($user, $seedKeywords, $project->location_code, $project->language_code, $limit);

        $keywords = array_map(fn($r) => $r->toArray(), $labsResults);

        $actualCost = $this->estimateCost('labs_suggestions', 1);
        $this->budgetGuard->recordCost($project, 'discover_keywords', count($keywords), $actualCost, $user);

        return ['keywords' => $keywords, 'cost_cents' => $actualCost];
    }

    /**
     * Discover keywords a domain ranks for.
     */
    public function discoverFromDomain(SeoProject $project, string $domain, ?User $user = null, int $limit = 100): array
    {
        $user = $user ?? $project->user;
        $estimatedCost = $this->estimateCost('labs_ranked', 1);

        if (!$this->budgetGuard->canFetch($project, $estimatedCost)) {
            return ['keywords' => [], 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($project);
        $rankedResults = $api->getRankedKeywords($user, $domain, $project->location_code, $project->language_code, $limit);

        $keywords = array_map(fn($r) => $r->toArray(), $rankedResults);

        $actualCost = $this->estimateCost('labs_ranked', 1);
        $this->budgetGuard->recordCost($project, 'discover_from_domain', count($keywords), $actualCost, $user);

        return ['keywords' => $keywords, 'cost_cents' => $actualCost];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    protected function resolveApiService(SeoProject $project): DataForSeoApiService
    {
        $connectionId = $project->dataforseo_connection_id;

        return $this->dataForSeoApi->forConnection($connectionId);
    }

    protected function estimateCost(string $action, int $count): int
    {
        $costPerUnit = config("seo.cost_estimates.{$action}", 5);

        return (int) ceil($count * $costPerUnit);
    }
}
