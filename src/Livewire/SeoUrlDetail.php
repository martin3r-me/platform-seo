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

    // Plausible: explizite site_id (Plausible-Site-Name, falls ≠ Domain) + Test-Ergebnis.
    public string $plausibleSiteId = '';
    public ?array $plausibleTest = null;

    // GSC: explizite Property-URL (falls Domain-Matching nicht greift, z. B. Alias).
    public string $gscProperty = '';

    // v2: Rückmeldung nach der Antwort-Einheit-Extraktion.
    public ?string $answerFlash = null;

    // Conversion-Attribution: gewähltes Ziel (Switcher).
    public ?string $conversionGoal = null;

    // URL-Steckbrief (das erklärte SOLL) — editierbare Felder + KI-Vorschlags-Status.
    public array $steckbrief = [
        'page_type' => null,
        'target_intent' => null,
        'funnel_stage' => null,
        'page_objective' => null,
        'focus_keyword' => null,
    ];
    public ?string $sbRationale = null;
    public ?string $sbSource = null;
    public ?string $sbConfirmedAt = null;
    public ?string $sbError = null;
    public bool $sbDirty = false;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
        $this->plausibleSiteId = (string) ($seoUrl->plausible_site_id ?? '');
        $this->gscProperty = (string) ($seoUrl->gsc_property ?? '');
        $this->loadSteckbrief();
    }

    /** Steckbrief aus der URL-Meta in den Formular-State laden. */
    protected function loadSteckbrief(): void
    {
        $s = $this->seoUrl->steckbrief;
        $this->steckbrief = [
            'page_type' => $s['page_type'],
            'target_intent' => $s['target_intent'],
            'funnel_stage' => $s['funnel_stage'],
            'page_objective' => $s['page_objective'],
            'focus_keyword' => $s['focus_keyword'],
        ];
        $this->sbRationale = $s['rationale'];
        $this->sbSource = $s['source'];
        $this->sbConfirmedAt = $s['confirmed_at'];
        $this->sbDirty = false;
    }

    /** KI schlägt den Steckbrief vor (aus On-Page + rankenden Keywords). Noch nicht bestätigt. */
    public function proposeSteckbrief(): void
    {
        $this->sbError = null;
        $proposal = app(\Platform\Seo\Services\SeoUrlMetaAdvisor::class)->propose($this->seoUrl);

        if (! empty($proposal['error'])) {
            $this->sbError = $proposal['error'];

            return;
        }

        $this->steckbrief = [
            'page_type' => $proposal['page_type'],
            'target_intent' => $proposal['target_intent'],
            'funnel_stage' => $proposal['funnel_stage'],
            'page_objective' => $proposal['page_objective'],
            'focus_keyword' => $proposal['focus_keyword'],
        ];
        $this->sbRationale = $proposal['rationale'];
        $this->sbSource = 'ai';
        $this->sbConfirmedAt = null; // Vorschlag — Bestätigung fehlt noch
        $this->sbDirty = true;
    }

    /** Manuelle Änderung markiert den Steckbrief als ungespeichert. */
    public function updatedSteckbrief(): void
    {
        $this->sbDirty = true;
    }

    /** Steckbrief bestätigen & speichern (das SOLL festschreiben). */
    public function saveSteckbrief(): void
    {
        $this->seoUrl->saveSteckbrief([
            'page_type' => $this->steckbrief['page_type'] ?: null,
            'target_intent' => $this->steckbrief['target_intent'] ?: null,
            'funnel_stage' => $this->steckbrief['funnel_stage'] ?: null,
            'page_objective' => $this->steckbrief['page_objective'] ?: null,
            'focus_keyword' => $this->steckbrief['focus_keyword'] ?: null,
            'rationale' => $this->sbRationale,
            'source' => $this->sbSource ?: 'human',
            'confirmed_at' => now()->toIso8601String(),
        ]);
        $this->seoUrl->refresh();
        $this->loadSteckbrief();
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

    // Cluster-Discovery gibt es nur noch im Wirkungsraum (Station „Ordnen"),
    // nicht mehr je URL — die URL ist nur noch lesende Basis.

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

    /**
     * GSC für diese URL an-/abschalten (symmetrisch zu Plausible). Der Collector
     * sammelt nur noch aktivierte URLs; bei Aus wird GSC übersprungen.
     */
    public function toggleGsc(): void
    {
        $this->seoUrl->update([
            'gsc_enabled' => ! $this->seoUrl->gsc_enabled,
        ]);
        $this->seoUrl->refresh();
    }

    /**
     * Explizite GSC-Property (siteUrl) speichern (leer = Domain-Auto-Matching).
     * Nötig, wenn die verifizierte Property anders heißt als die Domain (Alias).
     */
    public function saveGscProperty(): void
    {
        $value = trim($this->gscProperty);
        $this->seoUrl->update(['gsc_property' => $value !== '' ? $value : null]);
        $this->seoUrl->refresh();
    }

    /**
     * v2: den echten Seiteninhalt in Antwort-Einheiten zerlegen (füllt die Spine
     * seo_entities + seo_answer_units). Holt die Seite, liest JSON-LD + Text,
     * KI leitet je Entität einen Claim ab.
     */
    public function extractAnswerUnits(): void
    {
        $res = app(\Platform\Seo\Services\SeoAnswerExtractor::class)->extractForUrl($this->seoUrl);
        if (! empty($res['error'])) {
            $this->answerFlash = 'Fehler: '.$res['error'];

            return;
        }
        $this->answerFlash = ($res['created'] ?? 0).' neue Antwort-Einheit(en) · '.($res['entities'] ?? 0).' Entität(en) berührt.';
    }

    /** v2: Multi-Surface-Präsenz je Antwort-Einheit messen (SERP + AI). */
    public function checkPresence(): void
    {
        $n = app(\Platform\Seo\Services\SeoPresenceProbe::class)->forUrl($this->seoUrl);
        $this->answerFlash = $n === 0
            ? 'Keine Antwort-Einheiten zum Messen — erst extrahieren.'
            : (int) ($n / 2).' Antwort-Einheit(en) gemessen (SERP + AI).';
    }

    /**
     * Explizite Plausible-site_id speichern (leer = Fallback auf die Domain).
     * Nötig, wenn die Site in Plausible anders heißt als die Domain — sonst 401.
     */
    public function savePlausibleSiteId(): void
    {
        $value = trim($this->plausibleSiteId);
        $this->seoUrl->update(['plausible_site_id' => $value !== '' ? $value : null]);
        $this->seoUrl->refresh();
        $this->plausibleTest = null;
    }

    /**
     * Live-Test: prüft die aktuell eingetragene site_id (bzw. die Domain als
     * Fallback) direkt gegen die Plausible Stats-API. Schließt die Rate-Schleife
     * „site_id setzen → testen → grün/rot", statt still ins 401 zu laufen.
     */
    public function testPlausible(): void
    {
        $siteId = trim($this->plausibleSiteId) ?: preg_replace('/^www\./', '', strtolower($this->seoUrl->domain));

        $team = \Platform\Core\Models\Team::find($this->seoUrl->team_id);
        $connection = $team
            ? app(\Platform\Integrations\Services\IntegrationConnectionResolver::class)->resolveForTeam('plausible', $team)
            : null;

        if (! $connection) {
            $this->plausibleTest = ['ok' => false, 'msg' => 'Keine aktive Plausible-Connection für das Team dieser URL.'];

            return;
        }

        // Status vor dem Test merken: ein site-spezifisches 401 (falscher site_id)
        // markiert sonst die GETEILTE Connection als 'error' — und resolveForTeam
        // liefert nur aktive → das würde die Plausible-Sammlung des ganzen Teams
        // lahmlegen. Ein Site-Test darf die Connection nicht vergiften.
        $prevStatus = $connection->status;
        $prevError = $connection->last_error;

        try {
            $res = app(\Platform\Integrations\Services\PlausibleApiService::class)
                ->forConnection($connection->id)
                ->getBreakdown(null, [
                    'site_id' => $siteId,
                    'property' => 'event:page',
                    'period' => '7d',
                    'metrics' => 'visitors',
                    'limit' => 1,
                ]);
            $n = count($res['results'] ?? []);
            $this->plausibleTest = ['ok' => true, 'msg' => "OK — {$siteId} liefert Daten ({$n} Zeile(n) in 7 T)."];
        } catch (\Throwable $e) {
            // Connection-Status zurücksetzen, falls der Test-Call sie gekippt hat.
            $connection->refresh();
            if ($prevStatus === 'active' && $connection->status !== 'active') {
                $connection->update(['status' => $prevStatus, 'last_error' => $prevError]);
            }
            $this->plausibleTest = ['ok' => false, 'msg' => "{$siteId}: " . $e->getMessage()];
        }
    }

    public function setCompetitorDepth(int $keywordId, ?int $depth): void
    {
        $keyword = SeoKeyword::findOrFail($keywordId);
        $keyword->update([
            'competitor_tracking_depth' => $depth ?: null,
        ]);
    }

    public function setProfile(string $profile): void
    {
        $svc = app(\Platform\Seo\Services\SeoDataProfileService::class);
        if ($svc->isValidProfile((bool) $this->seoUrl->is_own, $profile)) {
            $this->seoUrl->update(['data_profile' => $profile]);
            $this->seoUrl->refresh();
        }
    }

    public function setBoost(int $days): void
    {
        $this->seoUrl->update([
            'boost_until' => $days > 0 ? now()->addDays($days) : null,
        ]);
        $this->seoUrl->refresh();
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

    /** Nach dem Speichern des SEO-Ziels neu rendern (Badges aktualisieren). */
    #[\Livewire\Attributes\On('url-target-saved')]
    public function onTargetSaved(): void
    {
        // Kein State nötig — der Re-Render liest die frischen Dimensionen.
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
        // Backlinks/Traffic nur summieren, wo sie im Daten-Profil erhoben wurden
        // (fetched_at gesetzt). Sonst null → UI zeigt „—" statt falscher 0.
        $backlinkUrls = collect([$this->seoUrl])->merge($childUrls)
            ->filter(fn ($u) => $u->backlinks_fetched_at !== null);
        $aggBacklinks = $backlinkUrls->isEmpty() ? null : (int) $backlinkUrls->sum('backlink_count');

        // Traffic rollt auf: Parent-Zeile + Summe der Kind-Pfade (30 Tage).
        $trafficUrls = collect([$this->seoUrl])->merge($childUrls)
            ->filter(fn ($u) => $u->traffic_fetched_at !== null);
        $aggVisitors = $trafficUrls->isEmpty() ? null : (int) $trafficUrls->sum('visitors_30d');
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

        // Per-URL-Clustering-Status frisch aus der DB (damit wire:poll den Abschluss erkennt).
        $freshMeta = SeoUrl::select('id', 'meta')->find($this->seoUrl->id)?->meta ?? [];
        $clustering = $freshMeta['clustering'] ?? null;
        $clusteringUpdatedAt = isset($clustering['at']) ? \Illuminate\Support\Carbon::parse($clustering['at']) : null;

        $profileSvc = app(\Platform\Seo\Services\SeoDataProfileService::class);
        $costSvc = app(\Platform\Seo\Services\SeoCostProjectionService::class);

        // Gemeinsamer Scope-Kennzahlen-Kern (Durchdringung/Ordnungsgrad/Wettbewerber)
        // über diese URL + ihre Unterseiten — dieselbe Lesart wie Portfolio/Liste.
        $scope = app(\Platform\Seo\Services\SeoScopeMetrics::class)
            ->forUrlIds((int) $this->seoUrl->team_id, $allUrlIds);

        // Conversion-Verlauf dieser URL (site-level Snapshots).
        $conversionTrend = \Platform\Seo\Models\SeoConversionSnapshot::where('url_id', $this->seoUrl->id)
            ->where('snapshot_date', '>=', now()->subDays(90))
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($s) => ['date' => $s->snapshot_date->format('Y-m-d'), 'value' => (int) $s->conversions_30d])
            ->all();

        // GSC-Sichtbarkeits-Verlauf dieser URL (Clicks + Ø-Position je Snapshot).
        $gscTrend = \Platform\Seo\Models\SeoGscSnapshot::where('url_id', $this->seoUrl->id)
            ->where('snapshot_date', '>=', now()->subDays(90))
            ->orderBy('snapshot_date')
            ->get()
            ->map(fn ($s) => [
                'date' => $s->snapshot_date->format('Y-m-d'),
                'clicks' => (int) $s->clicks_28d,
                'impressions' => (int) $s->impressions_28d,
                'position' => (float) $s->avg_position,
            ])
            ->all();

        // v2: Antwort-Einheiten der URL + je Einheit die letzte Präsenz je Surface.
        $answerUnits = \Platform\Seo\Models\SeoAnswerUnit::where('url_id', $this->seoUrl->id)
            ->with('entity')->orderByDesc('id')->get();
        $presenceByUnit = [];
        if ($answerUnits->isNotEmpty()) {
            foreach (\Platform\Seo\Models\SeoAnswerPresence::whereIn('answer_unit_id', $answerUnits->pluck('id'))
                ->orderByDesc('checked_at')->get() as $pr) {
                $presenceByUnit[$pr->answer_unit_id][$pr->surface] ??= $pr;
            }
        }

        return view('seo::livewire.seo-url-detail', [
            'isOrphan' => app(\Platform\Seo\Services\SeoOrphanService::class)->isOrphan($this->seoUrl),
            'answerUnits' => $answerUnits,
            'presenceByUnit' => $presenceByUnit,
            'scope' => $scope,
            'conversionTrend' => $conversionTrend,
            'gscTrend' => $gscTrend,
            'contextNodes' => $contextNodes,
            'availableNodes' => $availableNodes,
            'effectiveProfile' => $profileSvc->effectiveProfile($this->seoUrl),
            'availableProfiles' => $profileSvc->availableProfiles((bool) $this->seoUrl->is_own),
            'profileCostBreakdown' => $costSvc->urlBreakdown($this->seoUrl),
            'profileMonthlyCents' => $costSvc->urlMonthlyCents($this->seoUrl),
            'clusteringStatus' => $clustering['status'] ?? null,
            'clusteringResult' => $clustering['result'] ?? null,
            'clusteringUpdatedAt' => $clusteringUpdatedAt,
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
