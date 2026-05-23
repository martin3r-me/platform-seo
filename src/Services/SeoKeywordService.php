<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoProject;

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
        return SeoProject::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'] ?? $team->name . ' SEO',
            'domain' => $data['domain'] ?? null,
            'description' => $data['description'] ?? null,
            'industry_preset' => $data['industry_preset'] ?? null,
            'budget_limit_cents' => $data['budget_limit_cents'] ?? null,
            'refresh_interval_hours' => $data['refresh_interval_hours'] ?? 168,
            'location_code' => $data['location_code'] ?? 2276,
            'language_code' => $data['language_code'] ?? null,
        ]);
    }

    public function attachKeywords(int $teamId, int $projectId, array $keywords): array
    {
        $project = SeoProject::findOrFail($projectId);
        $attached = [];

        foreach ($keywords as $kw) {
            $keywordText = is_string($kw) ? $kw : ($kw['keyword'] ?? null);
            if (!$keywordText) {
                continue;
            }

            $keywordText = strtolower(trim($keywordText));

            // Team-level: firstOrCreate
            $keyword = SeoKeyword::firstOrCreate(
                ['team_id' => $teamId, 'keyword' => $keywordText],
                [
                    'search_intent' => is_array($kw) ? ($kw['search_intent'] ?? null) : null,
                    'topic' => is_array($kw) ? ($kw['topic'] ?? null) : null,
                ]
            );

            // Pivot: project-specific data
            $pivotData = [];
            if (is_array($kw)) {
                foreach (['priority', 'notes', 'content_status', 'target_url'] as $field) {
                    if (isset($kw[$field])) {
                        $pivotData[$field] = $kw[$field];
                    }
                }
            }

            $project->keywords()->syncWithoutDetaching([$keyword->id => $pivotData]);
            $attached[] = $keyword;
        }

        return $attached;
    }

    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null): array
    {
        $project = $projectId ? SeoProject::findOrFail($projectId) : null;

        // Get keywords: either for the project or all team keywords
        if ($project) {
            $keywords = $project->keywords;
            $user = $user ?? $project->user;
        } else {
            $keywords = SeoKeyword::where('team_id', $teamId)->get();
        }

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

        if (!$project) {
            return ['fetched' => 0, 'cost_cents' => 0, 'error' => 'Project required for budget + API resolution'];
        }

        $keywordTexts = $staleKeywords->pluck('keyword')->toArray();
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
        $this->budgetGuard->recordCost($project, 'fetch_metrics', $fetchedCount, $actualCost, $user);

        $project->update(['next_refresh_at' => now()->addHours($project->refresh_interval_hours)]);

        return ['fetched' => $fetchedCount, 'cost_cents' => $actualCost];
    }

    public function fetchRankings(int $projectId, ?User $user = null): array
    {
        $project = SeoProject::findOrFail($projectId);
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
        $projectDomain = $project->domain ? parse_url($project->domain, PHP_URL_HOST) ?? $project->domain : null;
        $fetchedCount = 0;
        $positionSnapshots = 0;
        $competitorEntries = [];

        foreach ($keywords as $keyword) {
            $serpResults = $api->getSerpOrganic($user, $keyword->keyword, $project->location_code, $project->language_code);

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
                    ->where('project_id', $project->id)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->orderByDesc('tracked_at')
                    ->first();

                SeoKeywordPosition::create([
                    'keyword_id' => $keyword->id,
                    'project_id' => $project->id,
                    'position' => $ownPosition,
                    'previous_position' => $lastSnapshot?->position,
                    'ranked_url' => $ownUrl,
                    'serp_features' => array_unique(array_slice($serpFeatures, 0, 10)),
                    'tracked_at' => now()->toDateString(),
                    'search_engine' => 'google',
                    'device' => 'desktop',
                ]);
                $positionSnapshots++;

                // Update pivot with current position
                $project->keywords()->updateExistingPivot($keyword->id, [
                    'position' => $ownPosition,
                    'ranked_url' => $ownUrl,
                ]);
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

    public function getKeywordsForProject(int $projectId): Collection
    {
        $project = SeoProject::findOrFail($projectId);

        return $project->keywords()->with('cluster')->get();
    }

    public function getKeywordSummary(int $projectId): array
    {
        $project = SeoProject::findOrFail($projectId);
        $keywords = $project->keywords;

        return [
            'total_keywords' => $keywords->count(),
            'clusters_count' => $project->clusters()->count(),
            'avg_search_volume' => (int) $keywords->avg('search_volume'),
            'avg_difficulty' => (int) $keywords->avg('keyword_difficulty'),
            'total_search_volume' => (int) $keywords->sum('search_volume'),
            'intents' => $keywords->pluck('search_intent')->filter()->countBy()->toArray(),
            'priorities' => $keywords->pluck('pivot.priority')->filter()->countBy()->toArray(),
            'with_metrics' => $keywords->whereNotNull('search_volume')->count(),
            'without_metrics' => $keywords->whereNull('search_volume')->count(),
        ];
    }

    // =========================================================================
    // Internal methods (not part of contract)
    // =========================================================================

    public function addKeyword(SeoProject $project, array $data, ?User $user = null): SeoKeyword
    {
        $keywordText = strtolower(trim($data['keyword']));

        // Create or get team-level keyword
        $keyword = SeoKeyword::firstOrCreate(
            ['team_id' => $project->team_id, 'keyword' => $keywordText],
            [
                'cluster_id' => $data['cluster_id'] ?? null,
                'search_volume' => $data['search_volume'] ?? null,
                'keyword_difficulty' => $data['keyword_difficulty'] ?? null,
                'cpc_cents' => $data['cpc_cents'] ?? null,
                'competition' => $data['competition'] ?? null,
                'search_intent' => $data['search_intent'] ?? null,
                'topic' => $data['topic'] ?? null,
            ]
        );

        // Attach to project via pivot with project-specific data
        $pivotData = [];
        foreach (['priority', 'notes', 'content_status', 'target_url'] as $field) {
            if (isset($data[$field])) {
                $pivotData[$field] = $data[$field];
            }
        }
        $project->keywords()->syncWithoutDetaching([$keyword->id => $pivotData]);

        return $keyword;
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
        $keywordFields = [];
        $pivotFields = [];

        // Keyword-level fields
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

    public function updateKeywordPivot(SeoProject $project, int $keywordId, array $pivotData): void
    {
        $update = [];
        foreach (['priority', 'notes', 'content_status', 'target_url', 'position', 'ranked_url'] as $field) {
            if (array_key_exists($field, $pivotData)) {
                $update[$field] = $pivotData[$field];
            }
        }

        if (!empty($update)) {
            $project->keywords()->updateExistingPivot($keywordId, $update);
        }
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

    public function detachKeywordFromProject(SeoProject $project, int $keywordId): void
    {
        $project->keywords()->detach($keywordId);
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
