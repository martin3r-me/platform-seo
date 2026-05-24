<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;

class SeoClusteringService
{
    protected const CLUSTER_COLORS = [
        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6',
        '#EC4899', '#06B6D4', '#F97316', '#14B8A6', '#6366F1',
        '#84CC16', '#E11D48', '#0EA5E9', '#D946EF', '#F43F5E',
    ];

    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
        protected SeoKeywordService $keywordService,
        protected SeoBudgetGuardService $budgetGuard,
    ) {}

    /**
     * Auto-cluster keywords based on SERP overlap.
     */
    public function autoCluster(SeoTeamSettings $settings, User $user, int $minOverlap = 3): array
    {
        $teamId = $settings->team_id;
        $keywords = SeoKeyword::where('team_id', $teamId)->whereNull('cluster_id')->get();

        if ($keywords->count() < 2) {
            return [
                'clusters_created' => 0,
                'keywords_clustered' => 0,
                'keywords_fetched' => 0,
                'singletons_remaining' => $keywords->count(),
                'cost_cents' => 0,
                'clusters' => [],
            ];
        }

        $estimatedCost = $this->estimateCost('serp', $keywords->count());
        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return [
                'clusters_created' => 0,
                'keywords_clustered' => 0,
                'keywords_fetched' => 0,
                'singletons_remaining' => $keywords->count(),
                'cost_cents' => 0,
                'error' => 'Budget limit exceeded',
            ];
        }

        $settings->update(['clustering_status' => 'running']);

        $api = $this->resolveApiService($settings);

        // 1. Fetch SERP data for each keyword
        $serpMap = [];
        $fetchedCount = 0;

        foreach ($keywords as $keyword) {
            try {
                $serpResults = $api->getSerpOrganic($user, $keyword->keyword, $settings->location_code, $settings->language_code);

                if (empty($serpResults)) {
                    continue;
                }

                $urls = [];
                foreach (array_slice($serpResults, 0, 10) as $serpResult) {
                    if ($serpResult->url) {
                        $normalized = $this->normalizeUrl($serpResult->url);
                        if ($normalized) {
                            $urls[] = $normalized;
                        }
                    }
                }

                if (!empty($urls)) {
                    $serpMap[$keyword->id] = $urls;
                    $fetchedCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('SeoClusteringService: SERP fetch failed', [
                    'keyword_id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (count($serpMap) < 2) {
            $settings->update([
                'clustering_status' => 'completed',
                'clustering_result' => [
                    'clusters_created' => 0,
                    'keywords_fetched' => $fetchedCount,
                    'singletons_remaining' => $keywords->count(),
                ],
            ]);

            return [
                'clusters_created' => 0,
                'keywords_clustered' => 0,
                'keywords_fetched' => $fetchedCount,
                'singletons_remaining' => $keywords->count(),
                'cost_cents' => 0,
                'clusters' => [],
            ];
        }

        // 2. Build adjacency list
        $adjacency = $this->buildAdjacencyList($serpMap, $minOverlap);

        // 3. Find connected components (BFS)
        $allIds = array_keys($serpMap);
        $components = $this->findConnectedComponents($adjacency, $allIds);

        // 4. Create clusters
        $keywordsById = $keywords->keyBy('id');
        $result = $this->createClusters($teamId, $user, $components, $keywordsById);

        // 5. Record cost
        $actualCost = $this->estimateCost('serp', $fetchedCount);
        $this->budgetGuard->recordCost($settings, 'auto_cluster', $fetchedCount, $actualCost, $user);

        $singletonsRemaining = SeoKeyword::where('team_id', $teamId)->whereNull('cluster_id')->count();

        $clusteringResult = [
            'clusters_created' => $result['clusters_created'],
            'keywords_clustered' => $result['keywords_clustered'],
            'keywords_fetched' => $fetchedCount,
            'singletons_remaining' => $singletonsRemaining,
            'cost_cents' => $actualCost,
            'clusters' => $result['clusters'],
        ];

        $settings->update([
            'clustering_status' => 'completed',
            'clustering_result' => $clusteringResult,
        ]);

        return $clusteringResult;
    }

    protected function normalizeUrl(string $url): ?string
    {
        $parsed = parse_url($url);

        if (!isset($parsed['host'])) {
            return null;
        }

        $host = strtolower($parsed['host']);
        $host = preg_replace('/^www\./', '', $host);
        $path = rtrim($parsed['path'] ?? '', '/');

        return $host . $path;
    }

    protected function buildAdjacencyList(array $serpMap, int $minOverlap): array
    {
        $ids = array_keys($serpMap);
        $adjacency = [];

        foreach ($ids as $id) {
            $adjacency[$id] = [];
        }

        $count = count($ids);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $idA = $ids[$i];
                $idB = $ids[$j];

                $overlap = count(array_intersect($serpMap[$idA], $serpMap[$idB]));

                if ($overlap >= $minOverlap) {
                    $adjacency[$idA][] = $idB;
                    $adjacency[$idB][] = $idA;
                }
            }
        }

        return $adjacency;
    }

    protected function findConnectedComponents(array $adjacency, array $allIds): array
    {
        $visited = [];
        $components = [];

        foreach ($allIds as $id) {
            if (isset($visited[$id])) {
                continue;
            }

            $component = [];
            $queue = [$id];
            $visited[$id] = true;

            while (!empty($queue)) {
                $current = array_shift($queue);
                $component[] = $current;

                foreach ($adjacency[$current] ?? [] as $neighbor) {
                    if (!isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }

            if (count($component) > 1) {
                $components[] = $component;
            }
        }

        usort($components, fn($a, $b) => count($b) - count($a));

        return $components;
    }

    protected function createClusters(int $teamId, User $user, array $components, $keywordsById): array
    {
        $clustersCreated = 0;
        $keywordsClustered = 0;
        $clusterDetails = [];

        foreach ($components as $index => $component) {
            $bestKeyword = null;
            $bestVolume = -1;
            $keywordNames = [];

            foreach ($component as $keywordId) {
                $kw = $keywordsById[$keywordId] ?? null;
                if (!$kw) {
                    continue;
                }

                $keywordNames[] = $kw->keyword;
                $volume = $kw->search_volume ?? 0;
                if ($volume > $bestVolume) {
                    $bestVolume = $volume;
                    $bestKeyword = $kw;
                }
            }

            if (!$bestKeyword) {
                continue;
            }

            $color = self::CLUSTER_COLORS[$index % count(self::CLUSTER_COLORS)];

            $cluster = $this->keywordService->createCluster($teamId, [
                'name' => $bestKeyword->keyword,
                'color' => $color,
            ], $user);

            foreach ($component as $keywordId) {
                $kw = $keywordsById[$keywordId] ?? null;
                if ($kw) {
                    $kw->update(['cluster_id' => $cluster->id]);
                    $keywordsClustered++;
                }
            }

            $clustersCreated++;
            $clusterDetails[] = [
                'name' => $cluster->name,
                'color' => $color,
                'keyword_count' => count($component),
                'keywords' => $keywordNames,
            ];
        }

        return [
            'clusters_created' => $clustersCreated,
            'keywords_clustered' => $keywordsClustered,
            'clusters' => $clusterDetails,
        ];
    }

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
