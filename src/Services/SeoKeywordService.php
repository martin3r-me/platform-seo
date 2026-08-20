<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Core\Contracts\SeoKeywordServiceInterface;
use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Integrations\DTOs\DataForSeo\RankedKeywordResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoKeywordCompetitor;
use Platform\Seo\Models\SeoKeywordSerp;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;

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

    public function fetchMetrics(int $teamId, ?int $projectId = null, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->first();

        // Optionaler Ziel-Filter: statt des gesamten Team-Pools nur eine URL,
        // ein Cluster oder eine explizite Keyword-Liste auffrischen. So lässt
        // sich gezielt messen, ohne den ganzen Pool (und dessen Budget) anzufassen.
        $query = SeoKeyword::where('team_id', $teamId);
        if (!empty($options['url_id'])) {
            $query->whereHas('urls', fn ($q) => $q->where('seo_urls.id', (int) $options['url_id']));
        }
        if (!empty($options['cluster_id'])) {
            $query->where('cluster_id', (int) $options['cluster_id']);
        }
        if (!empty($options['keywords'])) {
            $query->whereIn('keyword', array_map('strval', (array) $options['keywords']));
        }
        $keywords = $query->get();

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0];
        }

        // Filter to keywords that need refreshing (force = auch frische neu holen)
        $force = !empty($options['force']);
        $staleKeywords = $force ? $keywords : $keywords->filter(function ($kw) {
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

    public function fetchRankings(int $teamId, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $query = SeoKeyword::where('team_id', $teamId);

        // Filter by URL if url_id is given
        if (!empty($options['url_id'])) {
            $query->whereHas('urls', fn ($q) => $q->where('seo_urls.id', $options['url_id']));
        }

        // Filter by domain if given
        if (!empty($options['domain'])) {
            $query->whereHas('urls', fn ($q) => $q->where('domain', $options['domain']));
        }

        // Filter by minimum search volume
        if (!empty($options['min_volume'])) {
            $query->where('search_volume', '>=', $options['min_volume']);
        }

        // Only keywords with competitor_tracking_depth set
        if (!empty($options['only_with_competitor_tracking'])) {
            $query->whereNotNull('competitor_tracking_depth')
                ->where('competitor_tracking_depth', '>', 0);
        }

        // Only keywords without any competitor data yet
        if (!empty($options['only_new'])) {
            $query->whereDoesntHave('competitors');
        }

        $limit = $options['limit'] ?? 0;
        if ($limit > 0) {
            $query->limit($limit);
        }
        $keywords = $query->get();

        if ($keywords->isEmpty()) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0];
        }

        $estimatedCost = $this->estimateCost('serp', $keywords->count());

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);

        // Eigene Domains aus registrierten URLs ableiten (nicht aus Team-Settings)
        $ownDomains = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->pluck('domain')
            ->unique()
            ->filter()
            ->values()
            ->toArray();

        $fetchedCount = 0;
        $positionSnapshots = 0;
        $competitorEntries = [];

        foreach ($keywords as $keyword) {
            $serp = $api->getSerpWithFeatures($user, $keyword->keyword, $settings->location_code, $settings->resolveLanguageName());
            $serpResults = $serp['organic'] ?? [];
            $serpFeaturesResult = $serp['features'] ?? null;

            // SERP-Features aus demselben (bezahlten) Call persistieren — auch wenn
            // wir organisch nicht ranken (dann bleibt die Features-Zeile trotzdem wertvoll).
            if ($serpFeaturesResult) {
                SeoKeywordSerp::updateOrCreate(
                    ['keyword_id' => $keyword->id],
                    [
                        'team_id' => $teamId,
                        'item_types' => $serpFeaturesResult->itemTypes,
                        'people_also_ask' => $serpFeaturesResult->peopleAlsoAsk,
                        'related_searches' => $serpFeaturesResult->relatedSearches,
                        'featured_snippet' => $serpFeaturesResult->featuredSnippet,
                        'has_ai_overview' => $serpFeaturesResult->hasAiOverview,
                        'ai_overview_references' => $serpFeaturesResult->aiOverviewReferences,
                        'fetched_at' => now(),
                    ],
                );
            }

            if (empty($serpResults)) {
                continue;
            }

            $ownPosition = null;
            $ownUrl = null;
            $serpFeatures = [];
            foreach ($serpResults as $serpResult) {
                $serpFeatures[] = $serpResult->domain;
                if (!empty($ownDomains) && $serpResult->url) {
                    foreach ($ownDomains as $ownDomain) {
                        if (str_contains($serpResult->url, $ownDomain)) {
                            $ownPosition = $serpResult->position;
                            $ownUrl = $serpResult->url;
                            break;
                        }
                    }
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

                    SeoKeywordCompetitor::updateOrCreate(
                        [
                            'keyword_id' => $keyword->id,
                            'domain' => $serpResult->domain,
                            'tracked_at' => now()->toDateString(),
                        ],
                        [
                            'team_id' => $teamId,
                            'url' => $serpResult->url,
                            'position' => $serpResult->position,
                        ],
                    );
                }
            }

            // --- Competitor URL auto-enrichment ---
            if ($keyword->competitor_tracking_depth) {
                $depth = $keyword->competitor_tracking_depth;
                $topCompetitors = array_slice($serpResults, 0, $depth);
                $urlService = app(SeoUrlService::class);

                foreach ($topCompetitors as $serpResult) {
                    if (!$serpResult->url || !$serpResult->domain) {
                        continue;
                    }
                    if (in_array($serpResult->domain, $ownDomains)) {
                        continue;
                    }

                    $urlService->register($teamId, $serpResult->url, [
                        'is_own' => false,
                        'reason' => 'competitor_tracking',
                        'keyword_id' => $keyword->id,
                    ]);
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

    /**
     * Füllt das (bisher immer leere) search_intent — Bulk-Klassifikation über
     * DataForSeo Labs. Standard: nur Keywords ohne Intent, in 1000er-Chunks.
     *
     * @return array{classified:int, candidates:int, cost_cents:int, error?:string, dry_run?:bool}
     */
    public function classifyIntentForTeam(int $teamId, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $onlyMissing = $options['only_missing'] ?? true;
        $limit = (int) ($options['limit'] ?? 1000);
        $dryRun = $options['dry_run'] ?? false;

        $query = SeoKeyword::where('team_id', $teamId);
        if ($onlyMissing) {
            $query->whereNull('search_intent');
        }
        // Wertvollste zuerst — so bekommen hochvolumige Keywords ihren Intent zuerst.
        $keywords = $query->orderByDesc('search_volume')->limit($limit)->get();

        if ($keywords->isEmpty()) {
            return ['classified' => 0, 'candidates' => 0, 'cost_cents' => 0];
        }

        $callCount = (int) ceil($keywords->count() / 1000);
        $estimatedCost = $this->estimateCost('search_intent', $callCount);

        if ($dryRun) {
            return [
                'dry_run' => true,
                'classified' => 0,
                'candidates' => $keywords->count(),
                'cost_cents' => 0,
                'estimated_cost_cents' => $estimatedCost,
            ];
        }

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['classified' => 0, 'candidates' => $keywords->count(), 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $valid = ['informational', 'navigational', 'commercial', 'transactional'];
        $classified = 0;

        foreach ($keywords->chunk(1000) as $chunk) {
            $map = $api->getSearchIntent($user, $chunk->pluck('keyword')->all(), $settings->resolveLanguageName());
            foreach ($chunk as $kw) {
                $hit = $map[mb_strtolower(trim($kw->keyword))] ?? null;
                if ($hit && in_array($hit['label'], $valid, true)) {
                    $kw->update(['search_intent' => $hit['label']]);
                    $classified++;
                }
            }
        }

        $actualCost = $this->estimateCost('search_intent', $callCount);
        $this->budgetGuard->recordCost($settings, 'classify_intent', $keywords->count(), $actualCost, $user);

        return ['classified' => $classified, 'candidates' => $keywords->count(), 'cost_cents' => $actualCost];
    }

    /**
     * Keyword-Gap: Keywords, für die ein Wettbewerber rankt, die eigene Domain
     * aber NICHT (Domain-Intersection, intersections=false). Ein Call, nach
     * Volumen sortiert; optional direkt in den Team-Pool importieren.
     *
     * @return array{own_domain:string, competitor_domain:string, gap_count:int, imported:int, cost_cents:int, gap:array, error?:string}
     */
    public function keywordGap(int $teamId, string $competitorDomain, ?string $ownDomain = null, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $limit = (int) ($options['limit'] ?? 100);
        $minVolume = (int) ($options['min_volume'] ?? 0);
        $import = (bool) ($options['import'] ?? false);

        $ownDomain = $ownDomain ?: SeoUrl::where('team_id', $teamId)->where('is_own', true)->value('domain');
        if (! $ownDomain) {
            return ['own_domain' => '', 'competitor_domain' => $competitorDomain, 'gap_count' => 0, 'imported' => 0, 'cost_cents' => 0, 'gap' => [], 'error' => 'Keine eigene Domain gefunden.'];
        }

        $estimatedCost = $this->estimateCost('labs_ranked', 1);
        if (! $this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['own_domain' => $ownDomain, 'competitor_domain' => $competitorDomain, 'gap_count' => 0, 'imported' => 0, 'cost_cents' => 0, 'gap' => [], 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        // target1 = Wettbewerber (rankt), target2 = eigen (rankt nicht) → die Lücke.
        $results = $api->getDomainIntersection($user, $competitorDomain, $ownDomain, false, $limit, $settings->location_code, $settings->resolveLanguageName());

        $actualCost = $this->estimateCost('labs_ranked', 1);
        $this->budgetGuard->recordCost($settings, 'keyword_gap', count($results), $actualCost, $user);

        $gap = collect($results)
            ->filter(fn ($r) => ($r->searchVolume ?? 0) >= $minVolume)
            ->sortByDesc(fn ($r) => $r->searchVolume ?? 0)
            ->values();

        $imported = 0;
        if ($import && $gap->isNotEmpty()) {
            $toImport = $gap->map(fn ($r) => [
                'keyword' => $r->keyword,
                'search_volume' => $r->searchVolume,
                'keyword_difficulty' => $r->keywordDifficulty,
                'cpc_cents' => $r->cpc !== null ? (int) round($r->cpc * 100) : null,
                'competition' => $r->competition,
            ])->all();
            $imported = $this->addKeywords($teamId, $toImport, $user)->count();
        }

        return [
            'own_domain' => $ownDomain,
            'competitor_domain' => $competitorDomain,
            'gap_count' => $gap->count(),
            'imported' => $imported,
            'cost_cents' => $actualCost,
            'gap' => $gap->take(50)->map(fn ($r) => [
                'keyword' => $r->keyword,
                'search_volume' => $r->searchVolume,
                'keyword_difficulty' => $r->keywordDifficulty,
                'competitor_position' => $r->firstPosition,
                'competitor_url' => $r->firstUrl,
            ])->all(),
        ];
    }

    /**
     * Wettbewerber first-class: holt die ranked keywords einer Wettbewerber-Domain
     * (1 API-Call, dieselbe getRankedKeywords-Quelle wie das Rankings-Tracking) und
     * verknüpft sie MIT Position an die Wettbewerber-URL — inkl. Pool-Upsert und
     * Neuberechnung von keyword_count / total_search_volume / visibility_score.
     *
     * Bewusst getrennt von fetchRankingsByDomain: das trackt eigene URLs pfad-genau
     * und legt Unterseiten an. Hier wird der Domain-Footprint an die eine
     * Wettbewerber-URL gehängt (kein Auto-Create, keine is_own-Beschränkung).
     */
    public function linkCompetitorKeywords(int $teamId, SeoUrl $url, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $limit = $options['keywords_limit'] ?? 200;
        $minVolume = $options['min_volume'] ?? 0;

        if (!$url->domain) {
            return ['linked' => 0, 'imported' => 0, 'keywords' => 0, 'cost_cents' => 0, 'error' => 'URL hat keine Domain'];
        }

        $estimatedCost = $this->estimateCost('labs_ranked', 1);
        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['linked' => 0, 'imported' => 0, 'keywords' => 0, 'cost_cents' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $rankedResults = $api->getRankedKeywords($user, $url->domain, $settings->location_code, $settings->resolveLanguageName(), $limit);

        $actualCost = $this->estimateCost('labs_ranked', 1);
        $this->budgetGuard->recordCost($settings, 'link_competitor_keywords', count($rankedResults), $actualCost, $user);

        if ($minVolume > 0) {
            $rankedResults = array_values(array_filter($rankedResults, fn ($rk) => ($rk->searchVolume ?? 0) >= $minVolume));
        }

        if (empty($rankedResults)) {
            return ['linked' => 0, 'imported' => 0, 'keywords' => 0, 'cost_cents' => $actualCost];
        }

        $linked = 0;
        $keywordModels = [];

        DB::beginTransaction();
        try {
            // Pool-Upsert (seo_keywords) mit Metriken — dieselbe Quelle wie eigene URLs
            $keywordModels = $this->upsertKeywordsFromRanked($teamId, $rankedResults);

            foreach ($rankedResults as $rk) {
                if (!$rk->position) {
                    continue;
                }
                $keywordLower = strtolower(trim($rk->keyword));
                $keywordModel = $keywordModels[$keywordLower] ?? null;
                if (!$keywordModel) {
                    continue;
                }

                $existingPivot = $url->keywords()
                    ->where('keyword_id', $keywordModel->id)
                    ->first();
                $previousPosition = $existingPivot?->pivot?->position;

                $url->keywords()->syncWithoutDetaching([
                    $keywordModel->id => [
                        'position' => $rk->position,
                        'previous_position' => $previousPosition,
                        'position_updated_at' => now(),
                    ],
                ]);
                $linked++;
            }

            // keyword_count / total_search_volume / visibility_score neu berechnen
            $this->updateUrlDenormalized($url);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return [
                'linked' => 0,
                'imported' => count($keywordModels),
                'keywords' => count($rankedResults),
                'cost_cents' => $actualCost,
                'error' => 'persist: '.$e->getMessage(),
            ];
        }

        return [
            'linked' => $linked,
            'imported' => count($keywordModels),
            'keywords' => count($rankedResults),
            'cost_cents' => $actualCost,
        ];
    }

    /**
     * Fetch rankings per domain using getRankedKeywords() — 1 API-Call pro Domain statt N pro Keyword.
     *
     * Kosten: ~10 Cent pro Domain statt ~10 Cent pro Keyword.
     */
    public function fetchRankingsByDomain(int $teamId, ?User $user = null, array $options = []): array
    {
        $settings = SeoTeamSettings::where('team_id', $teamId)->firstOrFail();
        $keywordsLimit = $options['keywords_limit'] ?? 1000;
        $dryRun = $options['dry_run'] ?? false;
        $filterDomain = $options['domain'] ?? null;
        $maxUrls = $options['max_urls'] ?? null;

        // Eigene URLs laden (is_own=true)
        $urlQuery = SeoUrl::where('team_id', $teamId)->where('is_own', true);
        if ($filterDomain) {
            $urlQuery->where('domain', $filterDomain);
        }
        if ($maxUrls) {
            $urlQuery->limit($maxUrls);
        }
        $ownUrls = $urlQuery->get();

        if ($ownUrls->isEmpty()) {
            $hint = $filterDomain
                ? "Domain \"{$filterDomain}\" ist nicht als eigene URL registriert (is_own=true). Rankings-Tracking ist nur für eigene URLs verfügbar. Nutze seo.urls.PUT um is_own zu ändern, oder seo.urls.POST mit is_own=true."
                : 'Keine eigenen URLs registriert (is_own=true). Rankings-Tracking ist nur für eigene URLs verfügbar.';

            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => $hint];
        }

        // Nach Domain gruppieren
        $byDomain = [];
        foreach ($ownUrls as $url) {
            if ($url->domain) {
                $byDomain[$url->domain][] = $url;
            }
        }

        if (empty($byDomain)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => 'Keine Domains in URLs gefunden.'];
        }

        $domainCount = count($byDomain);
        $estimatedCost = $this->estimateCost('labs_ranked', $domainCount);

        // Dry run: nur Kosten schätzen
        if ($dryRun) {
            return [
                'dry_run' => true,
                'domains' => array_keys($byDomain),
                'domain_count' => $domainCount,
                'urls_count' => $ownUrls->count(),
                'keywords_limit_per_domain' => $keywordsLimit,
                'estimated_cost_cents' => $estimatedCost,
                'fetched' => 0,
                'cost_cents' => 0,
                'position_snapshots' => 0,
            ];
        }

        if (!$this->budgetGuard->canFetch($settings, $estimatedCost)) {
            return ['fetched' => 0, 'cost_cents' => 0, 'position_snapshots' => 0, 'error' => 'Budget limit exceeded'];
        }

        $api = $this->resolveApiService($settings);
        $today = now()->toDateString();

        $totalKeywordsUpserted = 0;
        $positionSnapshots = 0;
        $urlsUpdated = 0;
        $urlsAutoCreated = 0;
        $apiCallsMade = 0;
        $domainResults = [];

        foreach ($byDomain as $domain => $domainUrls) {
            try {
                $rankedResults = $api->getRankedKeywords(
                    $user,
                    $domain,
                    $settings->location_code,
                    $settings->resolveLanguageName(),
                    $keywordsLimit,
                );
                $apiCallsMade++;
            } catch (\Throwable $e) {
                $domainResults[$domain] = ['error' => $e->getMessage()];
                continue;
            }

            if (empty($rankedResults)) {
                $domainResults[$domain] = ['keywords' => 0, 'matched' => 0];
                continue;
            }

            // Alle Schreibvorgänge dieser Domain atomar — bei Fehler kein Teilzustand.
            try {
                DB::beginTransaction();

            // Phase 1: Keywords upserten mit Metriken
            $keywordModels = $this->upsertKeywordsFromRanked($teamId, $rankedResults);
            $totalKeywordsUpserted += count($keywordModels);

            // Phase 2: URL-Pfad-Match + Rankings zuordnen
            $urlPaths = [];
            $parentUrlId = null;
            $shortestPath = null;
            foreach ($domainUrls as $url) {
                $path = $url->path ?: (parse_url($url->url, PHP_URL_PATH) ?: '/');
                $normalizedPath = rtrim(strtolower($path), '/');
                $urlPaths[$url->id] = $normalizedPath;
                // Root-URL = kürzester Pfad (typisch "/" → "")
                if ($shortestPath === null || strlen($normalizedPath) < strlen($shortestPath)) {
                    $shortestPath = $normalizedPath;
                    $parentUrlId = $url->id;
                }
            }

            // Tracking: welches Keyword geht an welche URL (für Cleanup)
            $keywordUrlAssignments = []; // keyword_id => matched_url_id
            $autoCreatedUrls = []; // Auto-erstellte URLs für deferred Relationship-Erstellung

            $matchedCount = 0;
            foreach ($rankedResults as $rk) {
                if (!$rk->position || !$rk->url) {
                    continue;
                }

                $keywordLower = strtolower(trim($rk->keyword));
                $keywordModel = $keywordModels[$keywordLower] ?? null;
                if (!$keywordModel) {
                    continue;
                }

                // URL-Pfad-Match
                $rankedPath = rtrim(strtolower(parse_url($rk->url, PHP_URL_PATH) ?: '/'), '/');
                $matchedUrlId = $this->findBestPathMatch($rankedPath, $urlPaths);

                // Auto-Register: Unterseite anlegen wenn kein Match
                if (!$matchedUrlId) {
                    $normalizedUrl = SeoUrl::normalizeUrl($rk->url);
                    $newUrl = SeoUrl::firstOrCreate(
                        [
                            'team_id' => $teamId,
                            'url_hash' => SeoUrl::hashUrl($normalizedUrl),
                        ],
                        [
                            'url' => $normalizedUrl,
                            'domain' => $domain,
                            'is_own' => true,
                            'status' => 'active',
                            'priority' => 50,
                        ],
                    );
                    $matchedUrlId = $newUrl->id;
                    $urlPaths[$newUrl->id] = $rankedPath;
                    $domainUrls[] = $newUrl;
                    $urlsAutoCreated++;
                    $autoCreatedUrls[] = $newUrl;
                }

                $matchedCount++;

                // Pivot: seo_url_keywords updaten + SeoRankingHistory
                $matchedUrl = collect($domainUrls)->firstWhere('id', $matchedUrlId);
                if ($matchedUrl) {
                    $existingPivot = $matchedUrl->keywords()
                        ->where('keyword_id', $keywordModel->id)
                        ->first();

                    $previousPosition = $existingPivot?->pivot?->position;

                    $matchedUrl->keywords()->syncWithoutDetaching([
                        $keywordModel->id => [
                            'position' => $rk->position,
                            'previous_position' => $previousPosition,
                            'position_updated_at' => now(),
                        ],
                    ]);

                    $keywordUrlAssignments[$keywordModel->id] = $matchedUrlId;

                    // SeoRankingHistory (für Ranking-Tab im Frontend)
                    $lastHistory = SeoRankingHistory::where('url_id', $matchedUrl->id)
                        ->where('keyword_id', $keywordModel->id)
                        ->where('search_engine', 'google')
                        ->where('device', 'desktop')
                        ->where('tracked_at', '<', $today)
                        ->orderByDesc('tracked_at')
                        ->first();

                    SeoRankingHistory::updateOrCreate(
                        [
                            'url_id' => $matchedUrl->id,
                            'keyword_id' => $keywordModel->id,
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
                }

                // SeoKeywordPosition Snapshot (Tages-Aggregat)
                $existingSnapshot = SeoKeywordPosition::where('keyword_id', $keywordModel->id)
                    ->where('team_id', $teamId)
                    ->where('tracked_at', $today)
                    ->where('search_engine', 'google')
                    ->where('device', 'desktop')
                    ->first();

                if ($existingSnapshot) {
                    $existingSnapshot->update([
                        'position' => $rk->position,
                        'ranked_url' => $rk->url,
                        'serp_features' => $rk->serpFeatures,
                    ]);
                } else {
                    $lastSnapshot = SeoKeywordPosition::where('keyword_id', $keywordModel->id)
                        ->where('team_id', $teamId)
                        ->where('search_engine', 'google')
                        ->where('device', 'desktop')
                        ->where('tracked_at', '<', $today)
                        ->orderByDesc('tracked_at')
                        ->first();

                    SeoKeywordPosition::create([
                        'keyword_id' => $keywordModel->id,
                        'team_id' => $teamId,
                        'position' => $rk->position,
                        'previous_position' => $lastSnapshot?->position,
                        'ranked_url' => $rk->url,
                        'serp_features' => $rk->serpFeatures,
                        'tracked_at' => $today,
                        'search_engine' => 'google',
                        'device' => 'desktop',
                    ]);
                }
                $positionSnapshots++;
            }

            // Parent-Child Relationships: deferred erstellen nach dem Loop,
            // damit die Root-URL korrekt bestimmt wird (auch wenn sie auto-erstellt wurde)
            if (!empty($autoCreatedUrls)) {
                // Root-URL neu bestimmen über alle bekannten URLs (inkl. auto-erstellter)
                $rootUrlId = null;
                $rootPathLen = PHP_INT_MAX;
                foreach ($urlPaths as $urlId => $normalizedPath) {
                    if (strlen($normalizedPath) < $rootPathLen) {
                        $rootPathLen = strlen($normalizedPath);
                        $rootUrlId = $urlId;
                    }
                }

                if ($rootUrlId) {
                    $allDomainUrlIds = array_keys($urlPaths);

                    // Fehlerhafte Relationships bereinigen: Root darf nie target sein
                    SeoUrlRelationship::where('type', 'parent_child')
                        ->where('target_url_id', $rootUrlId)
                        ->whereIn('source_url_id', $allDomainUrlIds)
                        ->delete();

                    // Parent-Child für auto-erstellte URLs anlegen
                    foreach ($autoCreatedUrls as $newUrl) {
                        if ($newUrl->id !== $rootUrlId) {
                            SeoUrlRelationship::firstOrCreate(
                                [
                                    'source_url_id' => $rootUrlId,
                                    'target_url_id' => $newUrl->id,
                                    'type' => 'parent_child',
                                ],
                                [
                                    'team_id' => $teamId,
                                    'detected_at' => now(),
                                ],
                            );
                        }
                    }
                }
            }

            // Stale Pivot-Einträge bereinigen: Keyword nur an die gematchte URL,
            // von anderen URLs derselben Domain entfernen
            $domainUrlIds = collect($domainUrls)->pluck('id')->all();
            foreach ($keywordUrlAssignments as $kwId => $correctUrlId) {
                $staleUrlIds = array_filter($domainUrlIds, fn ($id) => $id !== $correctUrlId);
                if (!empty($staleUrlIds)) {
                    \Illuminate\Support\Facades\DB::table('seo_url_keywords')
                        ->where('keyword_id', $kwId)
                        ->whereIn('url_id', $staleUrlIds)
                        ->delete();
                }
            }

            // Denormalisierte Felder auf URLs updaten
            foreach ($domainUrls as $url) {
                $this->updateUrlDenormalized($url);
                $urlsUpdated++;
            }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $domainResults[$domain] = ['error' => 'persist: '.$e->getMessage()];
                continue;
            }

            $domainResults[$domain] = [
                'keywords' => count($rankedResults),
                'upserted' => count($keywordModels),
                'matched' => $matchedCount,
            ];
        }

        $actualCost = $this->estimateCost('labs_ranked', $apiCallsMade);
        $this->budgetGuard->recordCost($settings, 'fetch_rankings_by_domain', $apiCallsMade, $actualCost, $user);

        $settings->update(['next_refresh_at' => now()->addHours($settings->refresh_interval_hours)]);

        return [
            'fetched' => $totalKeywordsUpserted,
            'cost_cents' => $actualCost,
            'position_snapshots' => $positionSnapshots,
            'api_calls' => $apiCallsMade,
            'domains' => $domainResults,
            'urls_updated' => $urlsUpdated,
            'urls_auto_created' => $urlsAutoCreated,
        ];
    }

    /**
     * Upsert keywords from getRankedKeywords() results with all metrics.
     *
     * @param RankedKeywordResult[] $rankedResults
     * @return array<string, SeoKeyword> Indexed by lowercase keyword
     */
    protected function upsertKeywordsFromRanked(int $teamId, array $rankedResults): array
    {
        $models = [];

        foreach ($rankedResults as $rk) {
            $keywordLower = strtolower(trim($rk->keyword));

            $monthlyVolumes = null;
            $peakMonth = null;
            $seasonalityIndex = null;

            if ($rk->monthlySearches && count($rk->monthlySearches) >= 6) {
                $byMonth = [];
                foreach ($rk->monthlySearches as $m) {
                    $month = $m['month'] ?? 0;
                    if ($month >= 1 && $month <= 12) {
                        $byMonth[$month] = $m['search_volume'] ?? 0;
                    }
                }
                if (count($byMonth) >= 6) {
                    $monthlyVolumes = $byMonth;
                    $peakMonth = array_search(max($byMonth), $byMonth);
                    $avg = array_sum($byMonth) / count($byMonth);
                    $seasonalityIndex = $avg > 0 ? round(max($byMonth) / $avg, 2) : null;
                }
            }

            $updateData = array_filter([
                'search_volume' => $rk->searchVolume,
                'cpc_cents' => $rk->cpc !== null ? (int) round($rk->cpc * 100) : null,
                'competition' => $rk->competition,
                'keyword_difficulty' => $rk->keywordDifficulty,
                'monthly_volumes' => $monthlyVolumes,
                'peak_month' => $peakMonth,
                'seasonality_index' => $seasonalityIndex,
                'last_fetched_at' => now(),
            ], fn ($v) => $v !== null);

            $models[$keywordLower] = SeoKeyword::updateOrCreate(
                ['team_id' => $teamId, 'keyword' => $keywordLower],
                $updateData,
            );
        }

        return $models;
    }

    /**
     * Find best matching URL by path prefix (longest match wins).
     *
     * @param array<int, string> $urlPaths URL ID => normalized path
     */
    protected function findBestPathMatch(string $rankedPath, array $urlPaths): ?int
    {
        $bestMatch = null;
        $bestLength = -1;

        foreach ($urlPaths as $urlId => $entityPath) {
            // Root-Pfad ("") matcht nur exakt auf Root, nicht auf alle Unterseiten
            if ($entityPath === '') {
                if ($rankedPath === '') {
                    $bestMatch = $urlId;
                    $bestLength = 0;
                }
                continue;
            }

            if ($rankedPath === $entityPath || str_starts_with($rankedPath, $entityPath . '/')) {
                if (strlen($entityPath) > $bestLength) {
                    $bestMatch = $urlId;
                    $bestLength = strlen($entityPath);
                }
            }
        }

        return $bestMatch;
    }

    /**
     * Update denormalized fields on SeoUrl (keyword_count, total_search_volume, visibility_score).
     */
    protected function updateUrlDenormalized(SeoUrl $url): void
    {
        $ctrModel = [1 => 0.316, 2 => 0.158, 3 => 0.094, 4 => 0.06, 5 => 0.06];

        // Ignorierte Keywords (retired_at) zählen nicht mit — sonst schönt incidental
        // Ranking (z.B. für fremde Marken) Sichtbarkeit/Keyword-Zahl/Volumen.
        $keywords = $url->keywords()->whereNull('seo_keywords.retired_at')->get();
        $visibilityScore = 0;

        foreach ($keywords as $keyword) {
            $position = $keyword->pivot->position;
            if ($position === null || $position < 1) {
                continue;
            }
            $ctr = $ctrModel[$position] ?? ($position <= 10 ? 0.03 : 0.01);
            $visibilityScore += ($keyword->search_volume ?? 0) * $ctr;
        }

        $url->update([
            'keyword_count' => $keywords->count(),
            'total_search_volume' => $keywords->sum('search_volume'),
            'visibility_score' => $visibilityScore,
        ]);
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

        // Bulk-Endpoints (z.B. getSearchVolume, bis 1000 Keywords/Call): der
        // cost_estimates-Wert gilt PRO CALL, nicht pro Keyword. Sonst würde ein
        // großer Pool eine absurd hohe Phantom-Schätzung erzeugen und der
        // Budget-Guard blockte, obwohl der echte Bulk-Call Cent-Bruchteile kostet.
        $batch = (int) config("seo.cost_bulk_batch.{$action}", 0);
        if ($batch > 0) {
            return (int) (ceil($count / $batch) * $costPerUnit);
        }

        return (int) ceil($count * $costPerUnit);
    }
}
