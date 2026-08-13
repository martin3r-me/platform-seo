<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlBacklink;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoScoringService;

class SeoUrlDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoUrl $seoUrl;

    public string $activeTab = 'keywords';

    public ?int $selectedKeywordId = null;

    // Keywords-Tab: Sortierung + Filter
    public string $keywordSort = 'position';
    public string $keywordSortDir = 'asc';
    public string $keywordIntent = '';
    public string $keywordBucket = '';
    public int $keywordMinVolume = 0;

    // Infinite scroll limits
    public int $keywordLimit = 50;
    public int $rankingLimit = 50;
    public int $backlinkLimit = 50;
    public int $gscLimit = 50;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selectedKeywordId = null;
    }

    public function selectKeyword(int $keywordId)
    {
        $this->selectedKeywordId = $this->selectedKeywordId === $keywordId ? null : $keywordId;
    }

    /**
     * Stößt die kunden-gescopte Cluster-Discovery für diese URL im Hintergrund an
     * (SERP-basiert, kann dauern). Status läuft über clustering_status + wire:poll.
     */
    public function discoverClusters(): void
    {
        if (! $this->seoUrl->is_own) {
            return;
        }

        \Platform\Seo\Models\SeoTeamSettings::where('team_id', $this->seoUrl->team_id)
            ->update(['clustering_status' => 'running']);

        \Platform\Seo\Jobs\DiscoverClustersJob::dispatch($this->seoUrl->id);
    }

    public function sortKeywords(string $field): void
    {
        if ($this->keywordSort === $field) {
            $this->keywordSortDir = $this->keywordSortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->keywordSort = $field;
            // Sinnvolle Default-Richtung: Position/Alphabet aufsteigend, Metriken absteigend.
            $this->keywordSortDir = in_array($field, ['position', 'keyword'], true) ? 'asc' : 'desc';
        }
        $this->keywordLimit = 50;
    }

    public function resetKeywordFilters(): void
    {
        $this->keywordIntent = '';
        $this->keywordBucket = '';
        $this->keywordMinVolume = 0;
        $this->keywordLimit = 50;
    }

    public function updated($name): void
    {
        // Filteränderung setzt die Infinite-Scroll-Grenze zurück.
        if (in_array($name, ['keywordIntent', 'keywordBucket', 'keywordMinVolume'], true)) {
            $this->keywordLimit = 50;
        }
    }

    /**
     * Plausible für diese Domain aktivieren/deaktivieren (manuelles Opt-in).
     * Der Collector sammelt danach nur noch aktivierte Domains — kein Probing.
     */
    public function togglePlausible(): void
    {
        $this->seoUrl->update([
            'plausible_enabled' => ! $this->seoUrl->plausible_enabled,
        ]);
        $this->seoUrl->refresh();
    }

    public function setCompetitorDepth(int $keywordId, ?int $depth): void
    {
        $keyword = SeoKeyword::findOrFail($keywordId);
        $keyword->update([
            'competitor_tracking_depth' => $depth ?: null,
        ]);
    }

    public function loadMore(): void
    {
        match ($this->activeTab) {
            'keywords' => $this->keywordLimit += 50,
            'rankings' => $this->rankingLimit += 50,
            'backlinks' => $this->backlinkLimit += 50,
            'gsc' => $this->gscLimit += 50,
            default => null,
        };
    }

    private function getAllUrlIds(): array
    {
        return $this->getChildData()['allUrlIds'];
    }

    public function assignToNode(int $entityId): void
    {
        app(\Platform\Seo\Services\SeoOrganizationLinker::class)
            ->addNode(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, $this->seoUrl->id, $entityId);
    }

    public function removeFromNode(int $entityId): void
    {
        app(\Platform\Seo\Services\SeoOrganizationLinker::class)
            ->unlink(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, $this->seoUrl->id, $entityId);
    }

    #[Computed(persist: true)]
    public function childData(): array
    {
        $childRelations = SeoUrlRelationship::where('source_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('targetUrl')
            ->get();

        $childUrls = $childRelations->map(fn ($rel) => $rel->targetUrl)->filter();
        $childIds = $childUrls->pluck('id')->all();
        $allUrlIds = array_merge([$this->seoUrl->id], $childIds);

        return [
            'childUrls' => $childUrls,
            'allUrlIds' => $allUrlIds,
        ];
    }

    private function getChildData(): array
    {
        return $this->childData;
    }

    #[Computed]
    public function selectedKeyword()
    {
        if (! $this->selectedKeywordId) {
            return null;
        }

        return SeoKeyword::with([
            'cluster',
            'competitors' => fn ($q) => $q->orderBy('position')->limit(10),
            'positions' => fn ($q) => $q->latest('tracked_at')->limit(1),
        ])->find($this->selectedKeywordId);
    }

    #[Computed]
    public function selectedKeywordUrls()
    {
        if (! $this->selectedKeywordId) {
            return collect();
        }

        $allUrlIds = $this->getAllUrlIds();

        return SeoUrl::whereIn('id', $allUrlIds)
            ->whereHas('keywords', fn ($q) => $q->where('seo_keywords.id', $this->selectedKeywordId))
            ->with(['keywords' => fn ($q) => $q->where('seo_keywords.id', $this->selectedKeywordId)])
            ->get();
    }

    #[Computed]
    public function selectedKeywordHistory()
    {
        if (! $this->selectedKeywordId) {
            return collect();
        }

        return SeoRankingHistory::where('keyword_id', $this->selectedKeywordId)
            ->orderBy('tracked_at', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();
    }

    public function render()
    {
        $data = $this->getChildData();
        $childUrls = $data['childUrls'];
        $allUrlIds = $data['allUrlIds'];

        // Always: aggregate stats (cheap — uses cached fields on SeoUrl)
        $aggKeywordCount = $this->seoUrl->keyword_count + $childUrls->sum('keyword_count');
        $aggSearchVolume = $this->seoUrl->total_search_volume + $childUrls->sum('total_search_volume');
        $aggVisibility = (float) $this->seoUrl->visibility_score + (float) $childUrls->sum('visibility_score');
        $aggBacklinks = $this->seoUrl->backlink_count + $childUrls->sum('backlink_count');

        // Traffic rollt auf: Parent-Zeile + Summe der Kind-Pfade (30 Tage).
        $aggVisitors = $this->seoUrl->visitors_30d + $childUrls->sum('visitors_30d');
        $aggPageviews = $this->seoUrl->pageviews_30d + $childUrls->sum('pageviews_30d');

        // Always: on-page score for stats bar (just the score, not full data)
        $onPageScore = $this->seoUrl->onPage?->overall_score;

        // Always: parent URL for breadcrumb
        $parentRelation = SeoUrlRelationship::where('target_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('sourceUrl')
            ->first();
        $parentUrl = $parentRelation?->sourceUrl;

        // Tab-specific data
        $keywords = collect();
        $availableIntents = [];
        $keywordTotal = 0;
        $hasKeywords = false;
        $rankingRows = collect();
        $rankingSummary = null;
        $backlinks = collect();
        $onPage = null;
        $gscData = collect();
        $relationships = collect();
        $hasMore = false;

        switch ($this->activeTab) {
            case 'keywords':
                $allKeywords = SeoKeyword::whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
                    ->with(['urls' => fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds), 'competitors'])
                    ->get();

                $hasKeywords = $allKeywords->isNotEmpty();
                // Intent-Optionen aus dem echten Bestand (damit der Filter immer passt).
                $availableIntents = $allKeywords->pluck('search_intent')->filter()->unique()->sort()->values()->all();

                // Filter anwenden
                $filtered = $allKeywords->filter(function ($kw) {
                    if ($this->keywordIntent !== '' && $kw->search_intent !== $this->keywordIntent) {
                        return false;
                    }
                    if ($this->keywordMinVolume > 0 && (int) $kw->search_volume < $this->keywordMinVolume) {
                        return false;
                    }
                    if ($this->keywordBucket !== '') {
                        $pos = $kw->urls->min('pivot.position');
                        $ok = match ($this->keywordBucket) {
                            'top3' => $pos !== null && $pos <= 3,
                            'top10' => $pos !== null && $pos <= 10,
                            'striking' => $pos !== null && $pos >= 4 && $pos <= 20,
                            'beyond' => $pos === null || $pos > 20,
                            default => true,
                        };
                        if (! $ok) {
                            return false;
                        }
                    }

                    return true;
                });

                // Sortierung
                $descending = $this->keywordSortDir === 'desc';
                $filtered = $filtered->sortBy(fn ($kw) => match ($this->keywordSort) {
                    'search_volume' => (int) $kw->search_volume,
                    'cpc' => (float) ($kw->cpc_euro ?? 0),
                    'kd' => (int) ($kw->keyword_difficulty ?? 0),
                    'keyword' => mb_strtolower($kw->keyword),
                    default => $kw->urls->min('pivot.position') ?? 999,
                }, SORT_REGULAR, $descending)->values();

                $keywordTotal = $filtered->count();
                $hasMore = $filtered->count() > $this->keywordLimit;
                $keywords = $filtered->take($this->keywordLimit);
                break;

            case 'rankings':
                // Positions-Tracker: eine Zeile pro Keyword mit aktueller Position,
                // Delta zum vorherigen Tracking-Tag und Positions-Trend (beste
                // Position je Tag) — aus der SeoRankingHistory-Zeitreihe.
                $trackerRows = SeoRankingHistory::whereIn('url_id', $allUrlIds)
                    ->with(['keyword', 'url'])
                    ->orderBy('tracked_at')
                    ->get()
                    ->groupBy('keyword_id')
                    ->map(function ($entries) {
                        // Beste (niedrigste) Position je Tag, chronologisch sortiert.
                        $byDay = $entries
                            ->filter(fn ($e) => $e->position !== null)
                            ->groupBy(fn ($e) => $e->tracked_at->format('Y-m-d'))
                            ->map(fn ($day) => $day->sortBy('position')->first())
                            ->sortKeys();

                        if ($byDay->isEmpty()) {
                            return null;
                        }

                        $latest = $byDay->last();
                        $previous = $byDay->count() > 1 ? $byDay->slice(-2, 1)->first() : null;

                        return [
                            'keyword' => $latest->keyword,
                            'url' => $latest->url,
                            'position' => $latest->position,
                            'delta' => $previous ? $previous->position - $latest->position : null,
                            'serp_features' => $latest->serp_features,
                            'tracked_at' => $latest->tracked_at,
                            'trend' => $byDay->map(fn ($e) => $e->position)->values()->all(),
                            'points' => $byDay->count(),
                        ];
                    })
                    ->filter()
                    ->values();

                $rankingSummary = [
                    'total' => $trackerRows->count(),
                    'avg' => $trackerRows->isNotEmpty() ? round($trackerRows->avg('position'), 1) : null,
                    'top3' => $trackerRows->where('position', '<=', 3)->count(),
                    'top10' => $trackerRows->where('position', '<=', 10)->count(),
                    'top20' => $trackerRows->where('position', '<=', 20)->count(),
                    'improved' => $trackerRows->filter(fn ($r) => ($r['delta'] ?? 0) > 0)->count(),
                    'declined' => $trackerRows->filter(fn ($r) => ($r['delta'] ?? 0) < 0)->count(),
                ];

                $trackerRows = $trackerRows->sortBy(fn ($r) => $r['position'] ?? 999)->values();
                $hasMore = $trackerRows->count() > $this->rankingLimit;
                $rankingRows = $trackerRows->take($this->rankingLimit);
                break;

            case 'backlinks':
                $backlinks = SeoUrlBacklink::whereIn('url_id', $allUrlIds)
                    ->where('is_active', true)
                    ->orderByDesc('source_domain_authority')
                    ->take($this->backlinkLimit + 1)
                    ->get();
                $hasMore = $backlinks->count() > $this->backlinkLimit;
                $backlinks = $backlinks->take($this->backlinkLimit);
                break;

            case 'onpage':
                $onPage = $this->seoUrl->onPage;
                break;

            case 'gsc':
                $gscData = $this->seoUrl->gscData()
                    ->with('keyword')
                    ->orderByDesc('date')
                    ->take($this->gscLimit + 1)
                    ->get();
                $hasMore = $gscData->count() > $this->gscLimit;
                $gscData = $gscData->take($this->gscLimit);
                break;

            case 'relationships':
                $relationships = $this->seoUrl->sourceRelationships()
                    ->with('targetUrl')
                    ->get()
                    ->merge(
                        $this->seoUrl->targetRelationships()
                            ->with('sourceUrl')
                            ->get()
                    );
                break;

            case 'plausible':
                // Nutzt die bereits geladenen $childUrls + $seoUrl mit den
                // denormalisierten visitors_30d/pageviews_30d pro Pfad — keine
                // Extra-Query nötig.
                break;
        }

        // Organisations-Knoten: aktuell verlinkte + verfügbare (lose gekoppelt, guarded).
        $linker = app(\Platform\Seo\Services\SeoOrganizationLinker::class);
        $contextNodes = $linker->nodesForMany(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, [$this->seoUrl->id])[$this->seoUrl->id] ?? [];
        $availableNodes = $linker->availableNodes((int) $this->seoUrl->team_id);

        $clusteringSettings = \Platform\Seo\Models\SeoTeamSettings::where('team_id', $this->seoUrl->team_id)
            ->first(['clustering_status', 'clustering_result', 'updated_at']);

        return view('seo::livewire.seo-url-detail', [
            'contextNodes' => $contextNodes,
            'availableNodes' => $availableNodes,
            'clusteringStatus' => $clusteringSettings?->clustering_status,
            'clusteringResult' => $clusteringSettings?->clustering_result,
            'clusteringUpdatedAt' => $clusteringSettings?->updated_at,
            'parentUrl' => $parentUrl,
            'keywords' => $keywords,
            'availableIntents' => $availableIntents,
            'keywordTotal' => $keywordTotal,
            'hasKeywords' => $hasKeywords,
            'rankingRows' => $rankingRows,
            'rankingSummary' => $rankingSummary,
            'backlinks' => $backlinks,
            'onPage' => $onPage,
            'onPageScore' => $onPageScore,
            'gscData' => $gscData,
            'relationships' => $relationships,
            'childUrls' => $childUrls,
            'aggKeywordCount' => $aggKeywordCount,
            'aggSearchVolume' => $aggSearchVolume,
            'aggVisibility' => $aggVisibility,
            'aggBacklinks' => $aggBacklinks,
            'aggVisitors' => $aggVisitors,
            'aggPageviews' => $aggPageviews,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
