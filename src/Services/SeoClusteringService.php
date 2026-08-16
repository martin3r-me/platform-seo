<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\Log;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordSerp;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Models\SeoPortfolio;

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
        protected SeoOrganizationLinker $linker,
    ) {}

    /**
     * Auto-cluster keywords based on SERP overlap.
     */
    /**
     * Kunden-gescopte Discovery für eine eigene URL (+ Kinder). Löst Scope +
     * Ziel-Knoten auf und clustert. Gemeinsame Logik für CLI-Command, Queue-Job
     * und MCP-Tool.
     */
    public function autoClusterForUrl(int $rootId, int $minOverlap = 3): array
    {
        $root = SeoUrl::find($rootId);
        if (! $root) {
            return ['error' => 'URL nicht gefunden'];
        }

        $settings = SeoTeamSettings::where('team_id', $root->team_id)->first();
        if (! $settings) {
            return ['error' => 'Keine SEO-Einstellungen für dieses Team'];
        }

        $childIds = SeoUrlRelationship::where('source_url_id', $rootId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->all();

        $urlIds = SeoUrl::whereIn('id', array_merge([$rootId], $childIds))
            ->where('team_id', $root->team_id)
            ->where('is_own', true)
            ->pluck('id')->all();

        if (empty($urlIds)) {
            $root->markClustering('failed', ['error' => 'Keine eigenen URLs im Scope']);

            return ['error' => 'Keine eigenen URLs im Scope'];
        }

        $entityId = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_URL, $rootId)[0] ?? null;

        $root->markClustering('running');

        try {
            $result = $this->autoCluster($settings, null, $minOverlap, $urlIds, $entityId);
            $root->markClustering(empty($result['error']) ? 'completed' : 'failed', $result);

            return $result;
        } catch (\Throwable $e) {
            $root->markClustering('failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Nach-Clustern des ungeclusterten Rests eines Wirkungsraums: die wild
     * rankenden Keywords ALLER Mitglieds-URLs zu Themen bündeln. Bestehende
     * Cluster bleiben unangetastet (autoCluster filtert whereNull('cluster_id')).
     * Neue Cluster hängen am Org-Knoten des Wirkungsraums (Rollup).
     */
    public function autoClusterForPortfolio(int $portfolioId, int $minOverlap = 3, ?int $minVolume = null, ?int $deadlineTs = null): array
    {
        $portfolio = SeoPortfolio::find($portfolioId);
        if (! $portfolio) {
            return ['error' => 'Wirkungsraum nicht gefunden'];
        }

        $settings = SeoTeamSettings::where('team_id', $portfolio->team_id)->first();
        if (! $settings) {
            return ['error' => 'Keine SEO-Einstellungen für dieses Team'];
        }

        // Property-Ebene: eigene Mitglieds-URLs + ihre eigenen Unterseiten (dedupliziert).
        $urlIds = $portfolio->effectiveUrlIds();
        if (empty($urlIds)) {
            $portfolio->markClustering('failed', ['error' => 'Keine eigenen URLs im Wirkungsraum']);

            return ['error' => 'Keine eigenen URLs im Wirkungsraum'];
        }

        $entityId = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_PORTFOLIO, $portfolioId)[0] ?? null;

        $portfolio->markClustering('running');

        try {
            $result = $this->autoCluster($settings, null, $minOverlap, $urlIds, $entityId, $minVolume, $deadlineTs);

            if (! empty($result['error'])) {
                $portfolio->markClustering('failed', $result);
            } elseif (($result['complete'] ?? true) === false) {
                // Checkpoint: SERP-Abruf noch nicht durch — bleibt „running",
                // der Job setzt in einem Folgelauf fort (Cache trägt den Fortschritt).
                $portfolio->markClustering('running', $result);
            } else {
                $portfolio->markClustering('completed', $result);
            }

            return $result;
        } catch (\Throwable $e) {
            $portfolio->markClustering('failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * Ein „Zimmer" der semantischen Karte manifestieren: der semantische Vorschlag
     * (Keyword-Menge) wird per SERP GEPRÜFT — bestätigt als 1 Cluster ODER in echte
     * Seiten-Cluster gesplittet — und persistiert. Das ist der Punkt, an dem aus
     * Bedeutung (billig, unscharf) eine SERP-Entscheidung (teuer, Grundwahrheit) wird.
     * Scoped auf genau diese Keywords → billig. Neue Cluster hängen am Wirkungsraum.
     *
     * @param  int[]  $keywordIds
     */
    public function autoClusterForRoom(int $portfolioId, array $keywordIds, int $minOverlap = 3, ?int $deadlineTs = null): array
    {
        $portfolio = SeoPortfolio::find($portfolioId);
        if (! $portfolio) {
            return ['error' => 'Wirkungsraum nicht gefunden'];
        }
        $settings = SeoTeamSettings::where('team_id', $portfolio->team_id)->first();
        if (! $settings) {
            return ['error' => 'Keine SEO-Einstellungen für dieses Team'];
        }

        $keywordIds = array_values(array_filter(array_map('intval', $keywordIds)));
        if (count($keywordIds) < 2) {
            return ['error' => 'Zu wenige Keywords im Zimmer'];
        }

        $entityId = $this->linker->nodeIdsFor(SeoOrganizationLinker::ALIAS_PORTFOLIO, $portfolioId)[0] ?? null;

        $portfolio->markClustering('running');

        try {
            $result = $this->autoCluster($settings, null, $minOverlap, null, $entityId, null, $deadlineTs, $keywordIds);

            if (! empty($result['error'])) {
                $portfolio->markClustering('failed', $result);
            } elseif (($result['complete'] ?? true) === false) {
                $portfolio->markClustering('running', $result);
            } else {
                $portfolio->markClustering('completed', $result);
            }

            return $result;
        } catch (\Throwable $e) {
            $portfolio->markClustering('failed', ['error' => $e->getMessage()]);

            throw $e;
        }
    }

    /**
     * @param  int[]|null  $urlIds     Wenn gesetzt: nur Keywords dieser URLs clustern (kunden-scoped).
     * @param  int|null    $entityId   Wenn gesetzt: neue Cluster an diesen Org-Knoten hängen.
     * @param  int|null    $minVolume  Wenn gesetzt: nur Keywords mit >= diesem Suchvolumen (spart Budget/Rauschen).
     * @param  int[]|null  $onlyKeywordIds  Wenn gesetzt: exakt diese Keywords (Zimmer-Übernahme), scoped.
     */
    public function autoCluster(SeoTeamSettings $settings, ?User $user = null, int $minOverlap = 3, ?array $urlIds = null, ?int $entityId = null, ?int $minVolume = null, ?int $deadlineTs = null, ?array $onlyKeywordIds = null): array
    {
        $teamId = $settings->team_id;

        $query = SeoKeyword::where('team_id', $teamId)->whereNull('cluster_id');
        if ($onlyKeywordIds !== null) {
            // Ein Zimmer übernehmen: SERP nur auf genau diese Keywords (billig, scoped).
            $query->whereIn('id', $onlyKeywordIds);
        } elseif ($urlIds !== null) {
            $query->whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $urlIds));
        }
        if ($minVolume !== null) {
            $query->where('search_volume', '>=', $minVolume);
        }
        $keywords = $query->get();
        $keywordIds = $keywords->pluck('id')->all();

        if ($keywords->count() < 2) {
            return [
                'complete' => true,
                'clusters_created' => 0,
                'keywords_clustered' => 0,
                'keywords_fetched' => 0,
                'singletons_remaining' => $keywords->count(),
                'cost_cents' => 0,
                'clusters' => [],
            ];
        }

        $settings->update(['clustering_status' => 'running']);

        try {
            // === Phase 1: SERP abrufen (deadline-begrenzt, persistent, häppchenweise gebucht) ===
            // Bereits frisch gecachte Keywords überspringen — macht den Lauf
            // wiederaufsetzbar: ein Timeout/Neustart holt nur noch das Fehlende.
            $cachedIds = $this->freshSerpKeywordIds($keywordIds);
            $toFetch = $keywords->reject(fn ($k) => isset($cachedIds[$k->id]))->values();

            $stoppedAtDeadline = false;
            if ($toFetch->isNotEmpty()) {
                $estimatedCost = $this->estimateCost('serp', $toFetch->count());
                if (! $this->budgetGuard->canFetch($settings, $estimatedCost)) {
                    return [
                        'complete' => false,
                        'clusters_created' => 0,
                        'keywords_clustered' => 0,
                        'keywords_fetched' => count($cachedIds),
                        'singletons_remaining' => $keywords->count(),
                        'cost_cents' => 0,
                        'error' => 'Budget limit exceeded',
                    ];
                }

                $fetch = $this->fetchSerpForKeywords($settings, $toFetch, $user, $deadlineTs);
                $stoppedAtDeadline = $fetch['stopped_at_deadline'];

                // Sackgassen-Schutz: nichts holbar (alle Fehler) und nicht wegen
                // Deadline gestoppt → als Fehler sichtbar machen (API/Connection),
                // NICHT endlos fortsetzen.
                if ($fetch['fetched'] === 0 && ! $stoppedAtDeadline && count($cachedIds) === 0) {
                    $result = [
                        'complete' => false,
                        'clusters_created' => 0,
                        'keywords_clustered' => 0,
                        'keywords_fetched' => 0,
                        'singletons_remaining' => $keywords->count(),
                        'cost_cents' => 0,
                        'error' => 'SERP-Abruf lieferte nichts — API/Connection prüfen.',
                    ];
                    $settings->update(['clustering_status' => 'failed', 'clustering_result' => $result]);

                    return $result;
                }
            }

            // Checkpoint NUR wenn die Deadline uns gestoppt hat (es ist noch echte
            // Arbeit da). Bleibt ohne Deadline-Stopp etwas offen, sind das dauerhaft
            // fehlschlagende Keywords — dann clustern wir mit dem, was da ist, statt
            // endlos fortzusetzen.
            $freshCount = count($this->freshSerpKeywordIds($keywordIds));
            $remaining = $keywords->count() - $freshCount;
            if ($remaining > 0 && $stoppedAtDeadline) {
                return [
                    'complete' => false,
                    'clusters_created' => 0,
                    'keywords_clustered' => 0,
                    'keywords_fetched' => $freshCount,
                    'remaining' => $remaining,
                    'singletons_remaining' => $keywords->count(),
                    'cost_cents' => 0,
                ];
            }

            // === Phase 2: Clustern (aus dem Cache, kein API-Call, kein Timeout-Risiko) ===
            $serpMap = $this->serpMapFromCache($keywordIds);

            if (count($serpMap) < 2) {
                $result = [
                    'complete' => true,
                    'clusters_created' => 0,
                    'keywords_clustered' => 0,
                    'keywords_fetched' => $freshCount,
                    'singletons_remaining' => $keywords->count(),
                    'cost_cents' => 0,
                    'clusters' => [],
                    'finished_at' => now()->toIso8601String(),
                ];
                $settings->update(['clustering_status' => 'completed', 'clustering_result' => $result]);

                return $result;
            }

            $adjacency = $this->buildAdjacencyList($serpMap, $minOverlap);
            $components = $this->findConnectedComponents($adjacency, array_keys($serpMap));

            $keywordsById = $keywords->keyBy('id');
            $created = $this->createClusters($teamId, $user, $components, $keywordsById, $entityId);

            $singletonsRemaining = SeoKeyword::where('team_id', $teamId)->whereNull('cluster_id')->count();

            $clusteringResult = [
                'complete' => true,
                'clusters_created' => $created['clusters_created'],
                'keywords_clustered' => $created['keywords_clustered'],
                'keywords_fetched' => $freshCount,
                'singletons_remaining' => $singletonsRemaining,
                // schon häppchenweise während des Abrufs gebucht; hier nur die
                // Gesamtsumme fürs Reporting (der echte Verbrauch steht im Budget-Log).
                'cost_cents' => $this->estimateCost('serp', $freshCount),
                'clusters' => $created['clusters'],
                'finished_at' => now()->toIso8601String(),
            ];

            $settings->update([
                'clustering_status' => 'completed',
                'clustering_result' => $clusteringResult,
            ]);

            return $clusteringResult;
        } catch (\Throwable $e) {
            // Fehler sichtbar machen (UI liest clustering_result) statt still hängen.
            $settings->update([
                'clustering_status' => 'failed',
                'clustering_result' => [
                    'error' => $e->getMessage(),
                    'finished_at' => now()->toIso8601String(),
                ],
            ]);

            throw $e;
        }
    }

    /**
     * Holt SERP für die übergebenen Keywords, persistiert JEDES Ergebnis sofort
     * in den Cache und bucht die Kosten in kleinen Häppchen — so geht bei einem
     * Timeout weder Fortschritt noch Geld-Buchung verloren. Stoppt bei $deadlineTs.
     *
     * @return array{fetched:int,stopped_at_deadline:bool}
     */
    protected function fetchSerpForKeywords(SeoTeamSettings $settings, $keywords, ?User $user, ?int $deadlineTs): array
    {
        $api = $this->resolveApiService($settings);
        $fetched = 0;
        $batch = 0;
        $batchCost = 0;
        $flush = 25; // Kosten alle 25 Keywords buchen (durabel, aber wenige Log-Zeilen)
        $stoppedAtDeadline = false;

        foreach ($keywords as $keyword) {
            if ($deadlineTs !== null && time() >= $deadlineTs) {
                $stoppedAtDeadline = true;
                break;
            }

            try {
                $serpResults = $api->getSerpOrganic($user, $keyword->keyword, $settings->location_code, $settings->resolveLanguageName());

                $urls = [];
                foreach (array_slice($serpResults ?? [], 0, 10) as $serpResult) {
                    if ($serpResult->url) {
                        $normalized = $this->normalizeUrl($serpResult->url);
                        if ($normalized) {
                            $urls[] = $normalized;
                        }
                    }
                }

                // Auch ein leeres Ergebnis cachen — der Live-Call ist bezahlt, ein
                // erneuter Abruf beim Fortsetzen wäre reine Geldverschwendung.
                SeoKeywordSerp::updateOrCreate(
                    ['keyword_id' => $keyword->id],
                    ['team_id' => $settings->team_id, 'urls' => $urls, 'fetched_at' => now()],
                );

                $fetched++;
                $batch++;
                $batchCost += $this->estimateCost('serp', 1);

                if ($batch >= $flush) {
                    $this->budgetGuard->recordCost($settings, 'auto_cluster', $batch, $batchCost, $user);
                    $batch = 0;
                    $batchCost = 0;
                }
            } catch (\Throwable $e) {
                Log::warning('SeoClusteringService: SERP fetch failed', [
                    'keyword_id' => $keyword->id,
                    'keyword' => $keyword->keyword,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($batch > 0) {
            $this->budgetGuard->recordCost($settings, 'auto_cluster', $batch, $batchCost, $user);
        }

        return ['fetched' => $fetched, 'stopped_at_deadline' => $stoppedAtDeadline];
    }

    /**
     * Keyword-IDs, deren SERP-Cache noch frisch ist (TTL 30 Tage) — reicht fürs
     * Gruppieren. Rückgabe als Set (id => true) für schnelles Nachschlagen.
     */
    protected function freshSerpKeywordIds(array $keywordIds): array
    {
        if (empty($keywordIds)) {
            return [];
        }

        return SeoKeywordSerp::whereIn('keyword_id', $keywordIds)
            ->where('fetched_at', '>=', now()->subDays(30))
            ->pluck('keyword_id')
            ->flip()
            ->all();
    }

    /**
     * Baut die serpMap (keyword_id => URLs) aus dem Cache; leere Einträge fallen
     * raus (können nicht überlappen).
     */
    protected function serpMapFromCache(array $keywordIds): array
    {
        $serpMap = [];
        SeoKeywordSerp::whereIn('keyword_id', $keywordIds)
            ->where('fetched_at', '>=', now()->subDays(30))
            ->get(['keyword_id', 'urls'])
            ->each(function ($row) use (&$serpMap) {
                if (! empty($row->urls)) {
                    $serpMap[$row->keyword_id] = $row->urls;
                }
            });

        return $serpMap;
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

    protected function createClusters(int $teamId, ?User $user, array $components, $keywordsById, ?int $entityId = null): array
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

            // Discovery-Ergebnis: Cluster an den Kunden-Knoten hängen (bleibt candidate).
            if ($entityId !== null) {
                $this->linker->setNode(SeoOrganizationLinker::ALIAS_CLUSTER, $cluster->id, $entityId);
            }

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
