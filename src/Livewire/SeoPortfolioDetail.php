<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Jobs\ClusterPortfolioRestJob;
use Platform\Seo\Jobs\BuildPortfolioSemanticMapJob;
use Platform\Seo\Jobs\AdoptRoomJob;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoAnswerExperiment;
use Platform\Seo\Models\SeoAnswerPresence;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoConversionSnapshot;
use Platform\Seo\Models\SeoEntity;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoPortfolioMeasure;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlSnapshot;
use Platform\Seo\Services\SeoPortfolioAdvisor;
use Platform\Seo\Services\SeoPortfolioHealth;
use Platform\Seo\Services\SeoScopeMetrics;

/**
 * Wirkungsraum-Detail — der Arbeitsraum (Slice 2: Listen-Niveau + Mitglieder-
 * Management). Aggregat-KPIs, Mitglieder-Tabelle, URLs zufügen/lösen. Steuer-
 * Invariante: nur eigene (kontrollierte) URLs. Steuer-Facetten (Durchdringung,
 * ungeclusterter Rest, Wettbewerber, KI) folgen in Slice 3/4.
 */
class SeoPortfolioDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public bool $showAddUrls = false;
    public string $urlSearch = '';
    public array $selectedUrlIds = [];

    /** KI-Verteilungs-Vorschlag: ['text' => md] | ['error' => msg] | null. */
    public ?array $advice = null;

    /** Nur Keywords ab diesem Suchvolumen nach-clustern (spart Budget/Rauschen). */
    public int $clusterMinVolume = 10;

    public ?string $clusterFlash = null;

    /** Rückmeldung nach dem Maßnahmen-Generieren (Posteingang). */
    public ?string $measureFlash = null;

    /**
     * Angezeigte Reifegrad-Phase (gated Werkbank). null = aktuelles Gate.
     * Der Stepper ist klickbar — man kann jede Phase ansteuern, ohne Funktion
     * zu verlieren; gezeigt wird immer nur das Werkzeug einer Phase.
     */
    public ?string $viewPhase = null;

    /**
     * Aktive Ansicht der inneren Sidebar-Navigation: 'dashboard' (Überblick),
     * eine der 5 Stationen (Reifegrad-Gates) oder eine Bestand-Sicht
     * (keywords/clusters/competitors). Ersetzt den reinen Phasen-Stepper —
     * gibt dem Wirkungsraum semantisch gruppierte Views.
     */
    public string $view = 'dashboard';

    private const PHASES = ['messen', 'ordnen', 'verteilen', 'vertiefen', 'konvertieren'];
    private const BESTAND = ['keywords', 'clusters', 'competitors', 'entities'];

    /** Rückmeldung in der Entitäten-Sicht (Experiment/AI-Probe). */
    public ?string $entityFlash = null;

    public function setView(string $view): void
    {
        $valid = array_merge(['dashboard'], self::PHASES, self::BESTAND);
        $this->view = in_array($view, $valid, true) ? $view : 'dashboard';
        $this->viewPhase = in_array($this->view, self::PHASES, true) ? $this->view : null;
    }

    public function setPhase(string $phase): void
    {
        $this->setView($phase);
    }

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
    }

    public function openAddUrls(): void
    {
        $this->urlSearch = '';
        $this->selectedUrlIds = [];
        $this->showAddUrls = true;
    }

    public function addUrls(): void
    {
        if (empty($this->selectedUrlIds)) {
            return;
        }

        // Steuer-Invariante: nur eigene URLs des Teams.
        $ownIds = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', true)
            ->whereIn('id', $this->selectedUrlIds)
            ->pluck('id');

        $this->portfolio->urls()->syncWithoutDetaching(
            $ownIds->mapWithKeys(fn ($id) => [$id => ['added_at' => now()]])->all()
        );

        $this->reset('showAddUrls', 'selectedUrlIds', 'urlSearch');
    }

    public function removeUrl(int $urlId): void
    {
        $this->portfolio->urls()->detach($urlId);
    }

    /**
     * Property-Ebene: jede Mitglieds-URL PLUS ihre eigenen Unterseiten
     * (parent_child, eine Ebene — wie die URL-Detailseite rollt), über die
     * Vereinigungsmenge dedupliziert. Liefert die Mitglieder-Modelle, deren
     * Property-Totale (Mitglied + Unterseiten), die effektive URL-Menge (für
     * alle Portfolio-weiten Facetten) und das deduplizierte Aggregat. Damit
     * stimmen Portfolio- und URL-Sicht überein und der Fußabdruck ist echt.
     *
     * @return array{members: \Illuminate\Support\Collection, effectiveIds: int[], memberTotals: array<int,array>, agg: array}
     */
    protected function propertyView(): array
    {
        $members = $this->portfolio->urls()->orderByDesc('visibility_score')->get();
        $memberIds = $members->pluck('id')->all();

        if (empty($memberIds)) {
            return ['members' => $members, 'effectiveIds' => [], 'memberTotals' => [],
                'agg' => ['visibility' => 0.0, 'keywords' => 0, 'search_volume' => 0, 'urls' => 0]];
        }

        // Eigene Unterseiten je Mitglied (parent_child, eine Ebene).
        $childRels = DB::table('seo_url_relationships as r')
            ->join('seo_urls as c', 'c.id', '=', 'r.target_url_id')
            ->whereIn('r.source_url_id', $memberIds)
            ->where('r.type', 'parent_child')
            ->where('c.is_own', true)
            ->get(['r.source_url_id', 'r.target_url_id']);

        $childrenByParent = $childRels->groupBy('source_url_id')
            ->map(fn ($g) => $g->pluck('target_url_id')->all());

        // Vereinigungsmenge, dedupliziert (keine Doppelzählung bei Überlapp).
        $effectiveIds = array_values(array_unique(array_merge(
            $memberIds, $childRels->pluck('target_url_id')->all()
        )));

        $metrics = SeoUrl::whereIn('id', $effectiveIds)
            ->get(['id', 'keyword_count', 'total_search_volume', 'visibility_score'])
            ->keyBy('id');

        // Property-Total je Mitglied (Mitglied + eigene Unterseiten).
        $memberTotals = [];
        foreach ($members as $m) {
            $ids = array_merge([$m->id], $childrenByParent->get($m->id, []));
            $kw = 0; $sv = 0; $vis = 0.0;
            foreach ($ids as $id) {
                $row = $metrics->get($id);
                if (! $row) {
                    continue;
                }
                $kw += (int) $row->keyword_count;
                $sv += (int) $row->total_search_volume;
                $vis += (float) $row->visibility_score;
            }
            $memberTotals[$m->id] = ['keywords' => $kw, 'search_volume' => $sv,
                'visibility' => $vis, 'subpages' => count($childrenByParent->get($m->id, []))];
        }

        // Nach Property-Sichtbarkeit sortieren (nicht nur Knoten).
        $members = $members->sortByDesc(fn ($m) => $memberTotals[$m->id]['visibility'] ?? 0)->values();

        $agg = [
            'visibility' => (float) $metrics->sum('visibility_score'),
            'keywords' => (int) $metrics->sum('keyword_count'),
            'search_volume' => (int) $metrics->sum('total_search_volume'),
            'urls' => count($memberIds),
        ];

        return compact('members', 'effectiveIds', 'memberTotals', 'agg');
    }

    /**
     * Wirkung im Verbund: die Plausible-Fakten aussagekräftig aufs Portfolio
     * heben — welche Property wirklich wandelt (nicht nur rankt) und welche
     * Seiten Verbund-weit den Wert bringen. Quelle: Conversion-Felder auf den
     * Mitglieds-Root-URLs (site-level Plausible).
     *
     * @param  \Illuminate\Support\Collection  $members
     */
    protected function verbundWirkung($members): array
    {
        $memberRows = $members->map(fn ($u) => [
            'domain' => $u->domain . ($u->path !== '/' ? $u->path : ''),
            // Frische 30-Tage-Summe aus den organischen Landingpages (site-level,
            // ein Call) statt des dünn akkumulierten organic_visitors_30d.
            'org_visitors' => (int) collect($u->organic_landing_pages ?? [])->sum('visitors'),
            'conversions' => (int) ($u->conversions_30d ?? 0),
            'organic' => (int) ($u->organic_conversions_30d ?? 0),
            'rate' => (float) ($u->conversion_rate ?? 0),
            'goal' => $u->primary_goal,
        ])->filter(fn ($m) => $m['conversions'] > 0)->sortByDesc('conversions')->values();

        // Top konvertierende Seiten über alle Mitglieder (Events je Seite summiert, beste Rate).
        $pages = [];
        foreach ($members as $u) {
            foreach (($u->conversion_pages ?? []) as $group) {
                $goal = $group['goal'] ?? '';
                foreach (($group['pages'] ?? []) as $p) {
                    $key = $u->domain . '|' . ($p['page'] ?? '');
                    if (! isset($pages[$key])) {
                        $pages[$key] = ['site' => $u->domain, 'page' => (string) ($p['page'] ?? ''), 'conversions' => 0, 'rate' => 0.0, 'goal' => $goal];
                    }
                    $pages[$key]['conversions'] += (int) ($p['events'] ?? 0);
                    if ((float) ($p['rate'] ?? 0) > $pages[$key]['rate']) {
                        $pages[$key]['rate'] = (float) $p['rate'];
                        $pages[$key]['goal'] = $goal;
                    }
                }
            }
        }
        $topPages = collect($pages)->sortByDesc('conversions')->take(12)->values()->all();

        return ['members' => $memberRows->all(), 'topPages' => $topPages, 'has_data' => $memberRows->isNotEmpty()];
    }

    /**
     * Verbund-Verweise: speist der Verbund sich selbst? Für jede Property matchen
     * wir ihre Traffic-Quellen (visit:source) gegen die ANDEREN Verbund-Domains.
     * Ein Treffer = eine Verbund-Property schickt Besucher an eine andere (Ranker
     * → Endpunkt). Das ist der Verbund-Effekt, endlich messbar — nicht behauptet.
     * Gegen ALLE team-eigenen Domains gematcht (nicht nur Portfolio-Mitglieder),
     * damit auch Verweise von Verbund-Nachbarn außerhalb des Portfolios zählen.
     *
     * @param  \Illuminate\Support\Collection  $members
     */
    protected function verbundReferrals($members): array
    {
        $norm = fn ($s) => strtolower(preg_replace('/^www\./', '', trim((string) $s)));

        // Alle Verbund-Domains (team-eigen) als mögliche Verweis-Quellen.
        $ownDomains = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', true)
            ->pluck('domain')
            ->map($norm)->filter()->unique()->values()->all();

        $memberDomains = $members->pluck('domain')->map($norm)->filter()->unique()->values()->all();

        $edges = [];
        foreach ($members as $m) {
            $toDomain = $norm($m->domain);
            foreach (($m->traffic_sources ?? []) as $src) {
                $s = $norm($src['source'] ?? '');
                $v = (int) ($src['visitors'] ?? 0);
                if ($s === '' || $v <= 0) {
                    continue;
                }
                // matcht die Quelle eine ANDERE Verbund-Domain (nicht die eigene)?
                $match = null;
                foreach ($ownDomains as $d) {
                    if ($d === $toDomain) {
                        continue; // eigene Subdomain = Selbstverweis, nicht Verbund
                    }
                    if ($s === $d || str_ends_with($s, '.' . $d)) {
                        $match = $d;
                        break;
                    }
                }
                if ($match === null) {
                    continue;
                }
                $edges[] = [
                    'from' => (string) ($src['source'] ?? ''), // Rohquelle (zeigt ggf. Subdomain)
                    'from_domain' => $match,
                    'to' => $m->domain . ($m->path !== '/' ? $m->path : ''),
                    'visitors' => $v,
                    'from_is_member' => in_array($match, $memberDomains, true),
                ];
            }
        }

        usort($edges, fn ($a, $b) => $b['visitors'] <=> $a['visitors']);

        return [
            'edges' => $edges,
            'total' => array_sum(array_column($edges, 'visitors')),
            'has_data' => ! empty($edges),
        ];
    }

    /**
     * Conversion-Verlauf über Zeit (Summe über die URL-Menge je Snapshot-Tag).
     * Empty-State-first — jeder Punkt eine echte Messung. Zeigt, ob die Wirkung
     * STEIGT, statt nur den aktuellen Stand.
     *
     * @param  int[]  $urlIds
     */
    protected function conversionTrend(array $urlIds): array
    {
        $empty = ['points' => [], 'count' => 0, 'since' => null, 'current' => null, 'delta' => null];
        if (empty($urlIds)) {
            return $empty;
        }

        $rows = SeoConversionSnapshot::whereIn('url_id', $urlIds)
            ->where('snapshot_date', '>=', now()->subDays(90))
            ->selectRaw('snapshot_date, SUM(conversions_30d) as total')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $points = $rows->map(fn ($r) => [
            'date' => \Illuminate\Support\Carbon::parse($r->snapshot_date)->format('Y-m-d'),
            'value' => (int) $r->total,
        ])->values()->all();

        $count = count($points);

        return [
            'points' => $points,
            'count' => $count,
            'since' => $points[0]['date'],
            'current' => $points[$count - 1]['value'],
            'delta' => $count >= 2 ? $points[$count - 1]['value'] - $points[0]['value'] : null,
        ];
    }

    /**
     * KI-Verteilung: die vier Facetten in einen Aussteuerungs-Vorschlag gießen.
     */
    public function analyze(): void
    {
        // Hartes Gate: keine Verteilung auf ungeordneten Daten (Garbage-in).
        $health = app(SeoPortfolioHealth::class)->evaluate($this->portfolio);
        if (! $health['can_distribute']) {
            $this->advice = ['error' => 'Verteilung gesperrt — ' . $health['block_reason'] . ' Erst ordnen, dann aussteuern.'];

            return;
        }

        $pv = $this->propertyView();
        $members = $pv['members'];
        $totals = $pv['memberTotals'];
        $scope = app(SeoScopeMetrics::class)->forUrlIds($this->seoSettings->team_id, $pv['effectiveIds']);
        $pen = ['clusters' => $scope['clusters'], 'unclustered' => $scope['unclustered']];
        $comp = $scope['competitors'];

        $facets = [
            'members' => $members->map(fn ($u) => [
                'url' => $u->domain . ($u->path !== '/' ? $u->path : ''),
                'keywords' => (int) ($totals[$u->id]['keywords'] ?? $u->keyword_count),
                'visibility' => (int) round((float) ($totals[$u->id]['visibility'] ?? $u->visibility_score)),
            ])->all(),
            'penetration' => $pen['clusters']->map(fn ($c) => [
                'name' => $c['name'], 'soll' => $c['soll'], 'ist' => $c['ist'], 'pct' => $c['pct'], 'volume' => $c['volume'],
            ])->all(),
            'unclustered' => $pen['unclustered'],
            'competitors' => $comp->map(fn ($c) => [
                'domain' => $c->domain, 'shared' => (int) $c->shared_keywords, 'visibility' => (int) round((float) $c->visibility),
            ])->all(),
            'own_visibility' => (int) round((float) $pv['agg']['visibility']),
        ];

        $this->advice = app(SeoPortfolioAdvisor::class)->advise($this->portfolio, $facets);
    }

    /**
     * Nach-Clustern: den ungeclusterten Rest (wild rankende Keywords der
     * Mitglieder) zu Themen bündeln — abgegrenzt von bereits geclusterten
     * (der Service fasst nur cluster_id=null an). Läuft im Hintergrund (SERP).
     */
    public function clusterRest(): void
    {
        if (($this->portfolio->clustering_status ?? null) === 'running') {
            return;
        }

        $count = $this->clusterableCount($this->portfolio->effectiveUrlIds(), $this->clusterMinVolume);

        if ($count < 2) {
            $this->clusterFlash = 'Nichts zu clustern — kein ungeclusterter Rest über der Volumen-Schwelle.';

            return;
        }

        $this->portfolio->markClustering('running');
        ClusterPortfolioRestJob::dispatch($this->portfolio->id, 3, $this->clusterMinVolume);

        $this->clusterFlash = "Nach-Clustern gestartet für {$count} Keywords (läuft im Hintergrund).";
    }

    /**
     * Semantische Karte (neu) aufbauen: die Wirkungsraum-Linse auf die Keyword-
     * Vektoren in Qdrant. Kein SERP, kein Content — nur sehen. Läuft im Hintergrund.
     */
    /** Quelle der semantischen Karte: 'own' (Faden 1) oder 'both' (+Wettbewerber = Faden 2). */
    // Default 'both': die Karte zeigt eigene UND Wettbewerber-Keywords zusammen
    // (Besitz-Mix je Themenfeld/Cluster) — man braucht beide, nicht entweder/oder.
    public string $semanticSource = 'both';

    public function buildSemanticMap(?string $source = null): void
    {
        if (($this->portfolio->semantic_status ?? null) === 'running') {
            return;
        }
        if ($source !== null && in_array($source, ['own', 'both'], true)) {
            $this->semanticSource = $source;
        }

        $this->portfolio->markSemantic('running', null);
        BuildPortfolioSemanticMapJob::dispatch($this->portfolio->id, $this->semanticSource === 'both');
    }

    /**
     * Ein Zimmer eines Quartiers übernehmen: SERP-prüfen + als Cluster manifestieren.
     * Keyword-IDs werden serverseitig aus der Karte gelöst (kein großes DOM-Array).
     */
    public function adoptRoom(int $nbIndex, int $roomIndex): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}.keyword_ids", []);
        $this->adoptKeywords(is_array($ids) ? $ids : []);
    }

    /** Eine einfache Nachbarschaft (schon ein Thema) direkt übernehmen. */
    public function adoptSimple(int $nbIndex): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.keyword_ids", []);
        $this->adoptKeywords(is_array($ids) ? $ids : []);
    }

    /**
     * Gemeinsamer Übernahme-Pfad: SERP-Clustering scoped auf diese Keywords,
     * Ergebnis wird persistiert (der einzige schreibende Schritt der Semantik-Sicht).
     *
     * @param  int[]  $ids
     */
    protected function adoptKeywords(array $ids): void
    {
        if (($this->portfolio->clustering_status ?? null) === 'running') {
            return;
        }
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (count($ids) < 2) {
            $this->clusterFlash = 'Zimmer zu klein zum Übernehmen (min. 2 Keywords).';

            return;
        }

        $this->portfolio->markClustering('running');
        AdoptRoomJob::dispatch($this->portfolio->id, $ids);

        $cost = count($ids) * (int) config('seo.cost_estimates.serp', 10);
        $this->clusterFlash = count($ids) . ' Keywords werden per SERP geprüft und als Cluster übernommen (~'
            . number_format($cost / 100, 2, ',', '.') . ' € · läuft im Hintergrund).';
    }

    // ── Zimmer-Detailansicht: welche Keywords + welche URLs hängen dran ──────────

    public bool $showRoomDetail = false;

    public ?array $roomDetail = null;

    public function openRoom(int $nbIndex, int $roomIndex): void
    {
        $room = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}");
        if (is_array($room)) {
            $this->loadRoomDetail($room, $nbIndex, $roomIndex);
        }
    }

    public function openSimple(int $nbIndex): void
    {
        $nb = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}");
        if (is_array($nb)) {
            $this->loadRoomDetail($nb, $nbIndex, null);
        }
    }

    public function closeRoomDetail(): void
    {
        $this->showRoomDetail = false;
        $this->roomDetail = null;
    }

    /** Übernehmen direkt aus dem Detail (nutzt die gespeicherten Indizes). */
    public function adoptFromDetail(): void
    {
        if (! $this->roomDetail) {
            return;
        }
        $nb = $this->roomDetail['nb_index'];
        $ri = $this->roomDetail['room_index'];
        $ri !== null ? $this->adoptRoom($nb, $ri) : $this->adoptSimple($nb);
        $this->closeRoomDetail();
    }

    // ── Zimmer-Aktionen: merken (Kandidaten-Cluster) · abstellen (stilllegen) ────

    public function rememberRoom(int $nbIndex, int $roomIndex): void
    {
        $room = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}");
        if (is_array($room)) {
            $this->rememberKeywords($room['keyword_ids'] ?? [], $room['label'] ?? 'Gemerktes Thema');
            $this->spliceRoom($nbIndex, $roomIndex);
        }
    }

    public function rememberSimple(int $nbIndex): void
    {
        $nb = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}");
        if (is_array($nb)) {
            $this->rememberKeywords($nb['keyword_ids'] ?? [], $nb['label'] ?? 'Gemerktes Thema');
            $this->spliceRoom($nbIndex, null);
        }
    }

    public function retireRoom(int $nbIndex, int $roomIndex): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}.keyword_ids", []);
        $this->retireKeywords(is_array($ids) ? $ids : []);
        $this->spliceRoom($nbIndex, $roomIndex);
    }

    public function integrateRoom(int $nbIndex, int $roomIndex, int $clusterId): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}.keyword_ids", []);
        $this->integrateKeywords(is_array($ids) ? $ids : [], $clusterId);
        $this->spliceRoom($nbIndex, $roomIndex);
    }

    public function integrateSimple(int $nbIndex, int $clusterId): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.keyword_ids", []);
        $this->integrateKeywords(is_array($ids) ? $ids : [], $clusterId);
        $this->spliceRoom($nbIndex, null);
    }

    // ── A+.3: Thema einer Firma im Verbund zuordnen (Routing) ───────────────────

    public function assignRoomToCompany(int $nbIndex, int $roomIndex, string $domain): void
    {
        $room = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.rooms.{$roomIndex}");
        if (is_array($room)) {
            $this->assignKeywordsToCompany($room['keyword_ids'] ?? [], $room['label'] ?? 'Thema', $domain);
            $this->spliceRoom($nbIndex, $roomIndex);
        }
    }

    public function assignSimpleToCompany(int $nbIndex, string $domain): void
    {
        $nb = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}");
        if (is_array($nb)) {
            $this->assignKeywordsToCompany($nb['keyword_ids'] ?? [], $nb['label'] ?? 'Thema', $domain);
            $this->spliceRoom($nbIndex, null);
        }
    }

    /**
     * Thema → Firma: Kandidaten-Cluster mit Pillar = beste eigene URL dieser
     * Domain. „Themen sauber auf die Firmen verteilen" (der Verbund-Job).
     *
     * @param  int[]  $ids
     */
    protected function assignKeywordsToCompany(array $ids, string $label, string $domain): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }

        $pillarUrlId = SeoUrl::where('team_id', $this->portfolio->team_id)
            ->where('domain', $domain)
            ->where('is_own', true)
            ->orderByDesc('visibility_score')
            ->value('id');

        $cluster = SeoKeywordCluster::create([
            'team_id' => $this->portfolio->team_id,
            'name' => mb_substr(trim($label), 0, 120),
            'status' => SeoKeywordCluster::STATUS_CANDIDATE,
            'keyword_count' => count($ids),
            'pillar_url_id' => $pillarUrlId,
        ]);

        SeoKeyword::where('team_id', $this->portfolio->team_id)
            ->whereIn('id', $ids)
            ->whereNull('cluster_id')
            ->update(['cluster_id' => $cluster->id]);

        $this->clusterFlash = count($ids) . ' Keywords „' . $cluster->name . '" → ' . $domain
            . ' zugeordnet' . ($pillarUrlId ? ' (Pillar gesetzt)' : '') . '.';
    }

    // ── C2: Seiten zurückbauen (De-Invest) — abschaffen/umbauen/re-targeten ──────

    public function setDisposition(int $urlId, string $disposition): void
    {
        if (! in_array($disposition, ['retire', 'rebuild', 'retarget'], true)) {
            return;
        }
        SeoUrl::where('team_id', $this->portfolio->team_id)->whereKey($urlId)
            ->update(['disposition' => $disposition, 'disposition_at' => now()]);

        $label = ['retire' => 'Abschaffen', 'rebuild' => 'Umbauen', 'retarget' => 'Re-Targeten'][$disposition];
        $this->clusterFlash = 'Seite zum ' . $label . ' markiert (Angebots-Achse).';
    }

    public function clearDisposition(int $urlId): void
    {
        SeoUrl::where('team_id', $this->portfolio->team_id)->whereKey($urlId)
            ->update(['disposition' => null, 'disposition_at' => null]);
    }

    // ── Ausreißer: routen (zu Firma) oder abstellen (Rausch) ────────────────────

    public function retireOutlier(int $keywordId): void
    {
        $this->retireKeywords([$keywordId]);
        $this->spliceOutlier($keywordId);
    }

    public function assignOutlierToCompany(int $keywordId, string $domain): void
    {
        $this->assignKeywordsToCompany([$keywordId], $this->outlierLabel($keywordId), $domain);
        $this->spliceOutlier($keywordId);
    }

    /** Alle Ausreißer ohne Firmen-Bezug abstellen (offensichtlicher Rausch). */
    public function retireOutliersWithoutCompany(): void
    {
        $outliers = data_get($this->portfolio->semantic_map, 'outliers', []);
        $ids = [];
        foreach (is_array($outliers) ? $outliers : [] as $o) {
            if (empty($o['company'])) {
                $ids[] = (int) ($o['id'] ?? 0);
            }
        }
        $ids = array_values(array_filter($ids));
        if ($ids === []) {
            return;
        }

        $this->retireKeywords($ids);

        $map = $this->portfolio->semantic_map;
        $map['outliers'] = array_values(array_filter($map['outliers'] ?? [], fn ($o) => ! empty($o['company'])));
        $this->portfolio->update(['semantic_map' => $map]);
        $this->clusterFlash = count($ids) . ' Ausreißer ohne Firmen-Bezug abgestellt.';
    }

    protected function spliceOutlier(int $keywordId): void
    {
        $map = $this->portfolio->semantic_map;
        if (! is_array($map) || empty($map['outliers'])) {
            return;
        }
        $map['outliers'] = array_values(array_filter($map['outliers'], fn ($o) => (int) ($o['id'] ?? 0) !== $keywordId));
        $this->portfolio->update(['semantic_map' => $map]);
    }

    protected function outlierLabel(int $keywordId): string
    {
        foreach (data_get($this->portfolio->semantic_map, 'outliers', []) as $o) {
            if ((int) ($o['id'] ?? 0) === $keywordId) {
                return (string) ($o['keyword'] ?? 'Thema');
            }
        }

        return 'Thema';
    }

    /**
     * Integrieren = Keywords in ein BESTEHENDES Cluster einhängen (statt neues).
     * Nutzt die Zentroid-Verwandtschaft der Karte. Keine Doppelvergabe (nur unclustered).
     *
     * @param  int[]  $ids
     */
    protected function integrateKeywords(array $ids, int $clusterId): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }
        $cluster = SeoKeywordCluster::where('team_id', $this->portfolio->team_id)->find($clusterId);
        if (! $cluster) {
            return;
        }

        $n = SeoKeyword::where('team_id', $this->portfolio->team_id)
            ->whereIn('id', $ids)
            ->whereNull('cluster_id')
            ->update(['cluster_id' => $cluster->id]);

        if ($n > 0) {
            $cluster->increment('keyword_count', $n);
        }
        $this->clusterFlash = $n . ' Keywords in Cluster „' . $cluster->name . '" integriert.';
    }

    public function retireSimple(int $nbIndex): void
    {
        $ids = data_get($this->portfolio->semantic_map, "neighborhoods.{$nbIndex}.keyword_ids", []);
        $this->retireKeywords(is_array($ids) ? $ids : []);
        $this->spliceRoom($nbIndex, null);
    }

    /**
     * Merken = Kandidaten-Cluster ohne SERP (verlässt die Frontier). Die
     * Keywords bekommen cluster_id → fallen aus der Karte, tauchen als Cluster
     * (status=candidate) auf, „übernehmen" validiert später per SERP.
     *
     * @param  int[]  $ids
     */
    protected function rememberKeywords(array $ids, string $label): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        $cluster = SeoKeywordCluster::create([
            'team_id' => $this->portfolio->team_id,
            'name' => mb_substr(trim($label), 0, 120),
            'status' => SeoKeywordCluster::STATUS_CANDIDATE,
            'keyword_count' => count($ids),
        ]);

        SeoKeyword::where('team_id', $this->portfolio->team_id)
            ->whereIn('id', $ids)
            ->whereNull('cluster_id')
            ->update(['cluster_id' => $cluster->id]);

        $this->clusterFlash = count($ids) . ' Keywords gemerkt als Kandidaten-Cluster „' . $cluster->name . '" (ohne SERP).';
    }

    /**
     * Abstellen = Keywords stilllegen (Außenseiter/Rausch). retired_at gesetzt →
     * raus aus der Frontier. Umkehrbar.
     *
     * @param  int[]  $ids
     */
    protected function retireKeywords(array $ids): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return;
        }

        SeoKeyword::where('team_id', $this->portfolio->team_id)
            ->whereIn('id', $ids)
            ->update(['retired_at' => now()]);

        $this->clusterFlash = count($ids) . ' Keywords abgestellt (raus aus der Karte, umkehrbar).';
    }

    /**
     * Entfernt ein Zimmer/eine Nachbarschaft sofort aus der gespeicherten Karte
     * (visuelles Feedback ohne teuren Neubau).
     */
    protected function spliceRoom(int $nbIndex, ?int $roomIndex): void
    {
        $map = $this->portfolio->semantic_map;
        if (! is_array($map) || ! isset($map['neighborhoods'][$nbIndex])) {
            return;
        }

        if ($roomIndex === null) {
            unset($map['neighborhoods'][$nbIndex]);
            $map['neighborhoods'] = array_values($map['neighborhoods']);
        } elseif (isset($map['neighborhoods'][$nbIndex]['rooms'][$roomIndex])) {
            unset($map['neighborhoods'][$nbIndex]['rooms'][$roomIndex]);
            $map['neighborhoods'][$nbIndex]['rooms'] = array_values($map['neighborhoods'][$nbIndex]['rooms']);
        }

        $this->portfolio->update(['semantic_map' => $map]);
    }

    // ── Seiten-Gesundheit (Angebots-Achse): unfokussiert + Kannibalisierung ─────

    /**
     * @param  int[]  $ownUrlIds
     * @return array{unfocused: array, cannibalized: array}
     */
    protected function pageHealth(array $ownUrlIds): array
    {
        if (empty($ownUrlIds)) {
            return ['unfocused' => [], 'cannibalized' => []];
        }

        // Unfokussiert: eigene Seiten, die für Keywords aus VIELEN Clustern ranken.
        $unfocusedRows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $ownUrlIds)
            ->whereNotNull('uk.position')
            ->whereNotNull('k.cluster_id')
            ->groupBy('uk.url_id')
            ->havingRaw('COUNT(DISTINCT k.cluster_id) >= 3')
            ->select('uk.url_id', DB::raw('COUNT(DISTINCT k.cluster_id) as cluster_count'), DB::raw('COUNT(DISTINCT uk.keyword_id) as kw_count'))
            ->orderByDesc('cluster_count')
            ->limit(15)
            ->get();
        $urlsById = SeoUrl::whereIn('id', $unfocusedRows->pluck('url_id'))->get(['id', 'url', 'path', 'disposition'])->keyBy('id');
        $unfocused = $unfocusedRows->map(fn ($r) => [
            'url' => $urlsById->get($r->url_id),
            'cluster_count' => (int) $r->cluster_count,
            'kw_count' => (int) $r->kw_count,
        ])->filter(fn ($r) => $r['url'])->values()->all();

        // Kannibalisierung konkret: Keywords, für die ≥2 eigene Seiten ranken.
        $cannRows = DB::table('seo_url_keywords as uk')
            ->whereIn('uk.url_id', $ownUrlIds)
            ->whereNotNull('uk.position')
            ->groupBy('uk.keyword_id')
            ->havingRaw('COUNT(DISTINCT uk.url_id) >= 2')
            ->select('uk.keyword_id', DB::raw('COUNT(DISTINCT uk.url_id) as url_count'))
            ->orderByDesc('url_count')
            ->limit(15)
            ->get();

        $cannibalized = [];
        $cannKwIds = $cannRows->pluck('keyword_id')->all();
        if (! empty($cannKwIds)) {
            $kwById = SeoKeyword::whereIn('id', $cannKwIds)->get(['id', 'keyword', 'search_volume'])->keyBy('id');
            $pairs = DB::table('seo_url_keywords as uk')
                ->join('seo_urls as u', 'u.id', '=', 'uk.url_id')
                ->whereIn('uk.keyword_id', $cannKwIds)
                ->whereIn('uk.url_id', $ownUrlIds)
                ->whereNotNull('uk.position')
                ->select('uk.keyword_id', 'u.id as url_id', 'u.path', 'uk.position', 'u.disposition')
                ->orderBy('uk.position')
                ->get()
                ->groupBy('keyword_id');

            foreach ($cannRows as $r) {
                $kw = $kwById->get($r->keyword_id);
                if (! $kw) {
                    continue;
                }
                $urls = ($pairs->get($r->keyword_id) ?? collect())
                    ->map(fn ($p) => ['url_id' => (int) $p->url_id, 'path' => $p->path ?: '/', 'position' => (int) $p->position, 'disposition' => $p->disposition])
                    ->values()->all();
                $cannibalized[] = [
                    'keyword' => (string) $kw->keyword,
                    'volume' => (int) $kw->search_volume,
                    'urls' => $urls,
                ];
            }
        }

        return ['unfocused' => $unfocused, 'cannibalized' => $cannibalized];
    }

    /**
     * Lädt Keywords + rankende URLs eines Zimmers → zeigt sofort die Lage:
     * Weißraum (keine eigene Seite), Kannibalisierung (mehrere eigene) oder besetzt.
     *
     * @param  array  $group  Zimmer/Nachbarschaft aus der semantischen Karte
     */
    protected function loadRoomDetail(array $group, int $nbIndex, ?int $roomIndex): void
    {
        $ids = array_values(array_filter(array_map('intval', $group['keyword_ids'] ?? [])));
        if (empty($ids)) {
            return;
        }
        $teamId = $this->seoSettings->team_id;

        $kws = SeoKeyword::where('team_id', $teamId)->whereIn('id', $ids)
            ->orderByDesc('search_volume')->get(['id', 'keyword', 'search_volume', 'cluster_id']);

        // Rankende URLs (eigen + Wettbewerber) für die Keywords des Zimmers.
        $rank = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', 'u.id', '=', 'uk.url_id')
            ->whereIn('uk.keyword_id', $ids)
            ->whereNotNull('uk.position')
            ->get(['u.id as url_id', 'u.domain', 'u.path', 'u.is_own', 'uk.keyword_id', 'uk.position']);

        // Beste EIGENE Position je Keyword (IST).
        $ownPos = [];
        $urlAgg = [];
        foreach ($rank as $r) {
            $pos = (int) $r->position;
            if ($r->is_own) {
                $kid = (int) $r->keyword_id;
                $ownPos[$kid] = isset($ownPos[$kid]) ? min($ownPos[$kid], $pos) : $pos;
            }
            $uid = (int) $r->url_id;
            if (! isset($urlAgg[$uid])) {
                $urlAgg[$uid] = ['domain' => $r->domain, 'path' => $r->path, 'is_own' => (bool) $r->is_own, 'kw' => 0, 'best' => $pos];
            }
            $urlAgg[$uid]['kw']++;
            $urlAgg[$uid]['best'] = min($urlAgg[$uid]['best'], $pos);
        }

        $keywords = $kws->map(fn ($k) => [
            'keyword' => $k->keyword,
            'volume' => (int) ($k->search_volume ?? 0),
            'position' => $ownPos[$k->id] ?? null,
            'origin' => isset($ownPos[$k->id]) ? 'own' : 'competitor',
            'clustered' => $k->cluster_id !== null,
        ])->all();

        $urls = collect($urlAgg)->sortBy([['is_own', 'desc'], ['kw', 'desc']])->take(30)->values()->all();
        $ownRanking = collect($urlAgg)->where('is_own', true)->count();
        $situation = $ownRanking === 0 ? 'whitespace' : ($ownRanking >= 2 ? 'cannibalization' : 'single');

        $this->roomDetail = [
            'label' => $group['label'] ?? 'Zimmer',
            'size' => $group['size'] ?? count($ids),
            'potenzial' => $group['potenzial'] ?? 0,
            'ist' => $group['ist'] ?? 0,
            'gap' => $group['gap'] ?? 0,
            'nb_index' => $nbIndex,
            'room_index' => $roomIndex,
            'keywords' => $keywords,
            'urls' => $urls,
            'own_ranking' => $ownRanking,
            'situation' => $situation,
        ];
        $this->showRoomDetail = true;
    }

    /**
     * Zahl der clusterbaren Keywords: ungeclustert (cluster_id null), an einer
     * Mitglieds-URL, ab Volumen-Schwelle. Basis für Kostenschätzung + Guard.
     */
    protected function clusterableCount(array $memberIds, int $minVolume): int
    {
        if (empty($memberIds)) {
            return 0;
        }

        return (int) DB::table('seo_keywords as k')
            ->join('seo_url_keywords as uk', 'uk.keyword_id', '=', 'k.id')
            ->whereIn('uk.url_id', $memberIds)
            ->whereNull('k.cluster_id')
            ->where('k.search_volume', '>=', $minVolume)
            ->distinct()
            ->count('k.id');
    }

    /**
     * Verbund-Entwicklung über Zeit: Sichtbarkeit des Wirkungsraums je
     * Snapshot-Tag (Summe über die Mitglieds-URLs). Ehrlich sparse — jeder
     * Punkt ist eine echte Messung, keine Kalender-Interpolation. Die Frequenz
     * folgt der Snapshot-Kadenz (siehe seo-snapshot-cadence). Empty-State-first:
     * mit 0/1 Punkt zeigt die UI den Rahmen, nicht eine erfundene Kurve.
     */
    protected function trend(array $memberIds): array
    {
        $empty = ['points' => [], 'count' => 0, 'since' => null, 'current' => null, 'delta' => null];
        if (empty($memberIds)) {
            return $empty;
        }

        $rows = SeoUrlSnapshot::whereIn('url_id', $memberIds)
            ->where('snapshot_date', '>=', now()->subDays(90))
            ->selectRaw('snapshot_date, SUM(visibility_score) as total_visibility')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $points = $rows->map(fn ($r) => [
            'date' => \Illuminate\Support\Carbon::parse($r->snapshot_date)->format('Y-m-d'),
            'visibility' => (float) $r->total_visibility,
        ])->values()->all();

        $count = count($points);
        $current = $points[$count - 1]['visibility'];
        $first = $points[0]['visibility'];

        // Sparkline-Geometrie (Polyline + Flächen-Pfad) fürs Dashboard vorberechnen.
        $spark = null;
        if ($count >= 2) {
            $w = 240;
            $h = 40;
            $vals = array_column($points, 'visibility');
            $mn = min($vals);
            $rng = (max($vals) - $mn) ?: 1;
            $line = [];
            foreach ($points as $i => $p) {
                $x = round($i / ($count - 1) * $w, 1);
                $y = round($h - (($p['visibility'] - $mn) / $rng) * ($h - 4) - 2, 1);
                $line[] = $x.','.$y;
            }
            $poly = implode(' ', $line);
            $spark = ['w' => $w, 'h' => $h, 'line' => $poly, 'area' => '0,'.$h.' '.$poly.' '.$w.','.$h];
        }

        return [
            'points' => $points,
            'count' => $count,
            'since' => $points[0]['date'],
            'current' => $current,
            'delta' => $count >= 2 ? $current - $first : null,
            'spark' => $spark,
        ];
    }

    /**
     * Daten-Station — Inline-Aktivierung je Mitglieds-URL (Matrix URL×Quelle).
     * Alle team-gescopt. GSC/Plausible = Opt-in + site-id/property; Rankings/
     * On-Page/Backlinks = Profil (Tiefe/Takt/Kosten).
     */
    public function toggleUrlGsc(int $urlId): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if ($u) {
            $u->update(['gsc_enabled' => ! $u->gsc_enabled]);
        }
    }

    public function saveUrlGscProperty(int $urlId, string $value): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if ($u) {
            $value = trim($value);
            $u->update(['gsc_property' => $value !== '' ? $value : null]);
        }
    }

    public function toggleUrlPlausible(int $urlId): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if ($u) {
            $u->update(['plausible_enabled' => ! $u->plausible_enabled]);
        }
    }

    public function saveUrlPlausibleSiteId(int $urlId, string $value): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if ($u) {
            $value = trim($value);
            $u->update(['plausible_site_id' => $value !== '' ? $value : null]);
        }
    }

    public function setUrlProfile(int $urlId, string $profile): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if (! $u) {
            return;
        }
        $svc = app(\Platform\Seo\Services\SeoDataProfileService::class);
        if ($svc->isValidProfile((bool) $u->is_own, $profile)) {
            $u->update(['data_profile' => $profile]);
        }
    }

    /**
     * Föderations-Rolle einer Property setzen (Fundament der Orchestrierung).
     * Leer = zurücksetzen. Gegen config('seo.federation_roles') validiert.
     */
    public function setUrlRole(int $urlId, string $role): void
    {
        $u = SeoUrl::where('team_id', $this->seoSettings->team_id)->find($urlId);
        if (! $u) {
            return;
        }
        $valid = array_keys((array) config('seo.federation_roles', []));
        $u->update(['federation_role' => in_array($role, $valid, true) ? $role : null]);
    }

    /**
     * Cluster-Owner (pillar_url_id) küren — ein Thema = eine Seite. Owner muss
     * eine eigene URL des Teams sein; leer = Owner entfernen.
     */
    public function setClusterOwner(int $clusterId, $urlId): void
    {
        $c = SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->find($clusterId);
        if (! $c) {
            return;
        }
        $uid = (int) $urlId ?: null;
        if ($uid !== null) {
            $ok = SeoUrl::where('team_id', $this->seoSettings->team_id)
                ->where('id', $uid)->where('is_own', true)->exists();
            if (! $ok) {
                return;
            }
        }
        $c->update(['pillar_url_id' => $uid]);
    }

    /**
     * Maßnahmen aus den Signalen erzeugen (Posteingang füllen). Idempotent —
     * bereits entschiedene (auch abgelehnte) werden nicht neu vorgeschlagen.
     */
    public function generateMeasures(): void
    {
        $pv = $this->propertyView();
        $memberIds = $pv['members']->pluck('id')->all();
        $board = $this->orchestrationBoard($pv['members']);
        $entities = $this->wirkungsraumEntities($pv['members']);

        $gen = app(\Platform\Seo\Services\SeoMeasureGenerator::class);
        $n = $gen->fromBoard($this->portfolio, $board['rows']);       // v1: Kannibalisierung/Pillar
        $n += $gen->fromV2($this->portfolio, $memberIds);             // v2: veraltet/GEO-Lücke

        // KI-Anreicherung: Zustand rein, typisierte Maßnahmen raus.
        $ai = app(\Platform\Seo\Services\SeoMeasureAiAdvisor::class)
            ->propose($this->portfolio, ['board' => $board['rows'], 'entities' => $entities]);
        $n += $gen->fromAi($this->portfolio, $ai);

        $this->measureFlash = $n === 0
            ? 'Keine neuen Maßnahmen — alles bereits im Posteingang oder entschieden.'
            : $n.' neue '.($n === 1 ? 'Maßnahme' : 'Maßnahmen').' im Posteingang (Signale + KI).';
    }

    /** Maßnahme annehmen → in die Prioritäts-Queue (wartet aufs Tages-Ventil). */
    public function acceptMeasure(int $id): void
    {
        $m = SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->find($id);
        if ($m && $m->status === SeoPortfolioMeasure::STATUS_PROPOSED) {
            $m->update(['status' => SeoPortfolioMeasure::STATUS_ACCEPTED, 'decided_at' => now()]);
        }
    }

    /** Maßnahme begründet ablehnen → bleibt als Wirkungsraum-Kontext erhalten. */
    public function rejectMeasure(int $id, string $reason = ''): void
    {
        $m = SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->find($id);
        if ($m && $m->status === SeoPortfolioMeasure::STATUS_PROPOSED) {
            $m->update([
                'status' => SeoPortfolioMeasure::STATUS_REJECTED,
                'reject_reason' => trim($reason) ?: null,
                'decided_at' => now(),
            ]);
        }
    }

    /**
     * v2-Sicht „Entitäten": die besessenen Entitäten des Wirkungsraums (aus den
     * Antwort-Einheiten der Mitglieder), je Entität die letzte Präsenz je Surface
     * + Wirkungsraum-weiter „Share of Answer" (Anteil präsenter Entitäten).
     */
    protected function wirkungsraumEntities($members): array
    {
        $memberIds = $members->pluck('id')->all();
        $empty = ['rows' => [], 'total' => 0, 'present' => 0, 'answered' => 0, 'share' => null];
        if (empty($memberIds)) {
            return $empty;
        }

        // Angebot: Entitäten über die Antwort-Einheiten der Mitglieder.
        $units = SeoAnswerUnit::whereIn('url_id', $memberIds)->get(['id', 'entity_id']);
        $unitsByEntity = $units->groupBy('entity_id');

        $presence = [];
        if ($units->isNotEmpty()) {
            foreach (SeoAnswerPresence::whereIn('answer_unit_id', $units->pluck('id'))
                ->orderByDesc('checked_at')->get() as $p) {
                $presence[$p->answer_unit_id][$p->surface] ??= $p;
            }
        }

        // Nachfrage: Entitäten, die an die Cluster des Wirkungsraums geknüpft sind.
        $clusterIds = $this->wirkungsraumClusterIds($memberIds);
        $demandEntityIds = empty($clusterIds) ? []
            : SeoEntity::where('team_id', $this->seoSettings->team_id)
                ->whereIn('cluster_id', $clusterIds)->pluck('id')->all();

        $allEntityIds = collect($unitsByEntity->keys())->merge($demandEntityIds)
            ->map(fn ($i) => (int) $i)->unique()->values();
        if ($allEntityIds->isEmpty()) {
            return $empty;
        }
        $entities = SeoEntity::whereIn('id', $allEntityIds)->get()->keyBy('id');

        $rows = [];
        $present = 0;
        $answered = 0;
        foreach ($allEntityIds as $eid) {
            $entity = $entities[$eid] ?? null;
            if (! $entity) {
                continue;
            }
            $eunits = $unitsByEntity[$eid] ?? collect();
            $serp = false;
            $serpPos = null;
            $ai = false;
            foreach ($eunits as $u) {
                $ps = $presence[$u->id]['serp'] ?? null;
                if ($ps && $ps->present) {
                    $serp = true;
                    if ($ps->position !== null) {
                        $serpPos = $serpPos === null ? (int) $ps->position : min($serpPos, (int) $ps->position);
                    }
                }
                foreach (['ai_overview', 'chatgpt'] as $s) {
                    $pa = $presence[$u->id][$s] ?? null;
                    if ($pa && $pa->cited) {
                        $ai = true;
                    }
                }
            }
            $isAnswered = $eunits->count() > 0;
            if ($isAnswered) {
                $answered++;
            }
            if ($serp || $ai) {
                $present++;
            }
            $rows[] = [
                'entity_id' => (int) $eid,
                'name' => $entity->name ?? '—',
                'type' => $entity->entity_type ?? null,
                'demand' => (int) $entity->search_volume,
                'units' => $eunits->count(),
                'answered' => $isAnswered,
                'serp' => $serp,
                'serp_pos' => $serpPos,
                'ai' => $ai,
            ];
        }
        // Lücken mit Nachfrage zuerst (baubar), dann beantwortete nach Präsenz.
        usort($rows, fn ($a, $b) => ($b['demand'] <=> $a['demand']) ?: ($b['units'] <=> $a['units']));
        $total = count($rows);

        return [
            'rows' => $rows,
            'total' => $total,
            'present' => $present,
            'answered' => $answered,
            'share' => $total ? (int) round($present / $total * 100) : null,
        ];
    }

    /** Ein Experiment für eine Entität starten — sichert die Baseline-Präsenz. */
    public function startExperiment(int $entityId): void
    {
        $entity = SeoEntity::where('team_id', $this->seoSettings->team_id)->find($entityId);
        if (! $entity) {
            return;
        }
        $unitIds = SeoAnswerUnit::where('entity_id', $entityId)->pluck('id');
        $baseline = [];
        foreach (SeoAnswerPresence::whereIn('answer_unit_id', $unitIds)->orderByDesc('checked_at')->get() as $p) {
            $baseline[$p->surface] ??= ['present' => (bool) $p->present, 'position' => $p->position, 'cited' => (bool) $p->cited];
        }
        SeoAnswerExperiment::create([
            'team_id' => $this->seoSettings->team_id,
            'portfolio_id' => $this->portfolio->id,
            'entity_id' => $entityId,
            'hypothesis' => 'Antwort für „'.$entity->name.'" stärken → Präsenz heben.',
            'status' => SeoAnswerExperiment::STATUS_PLANNED,
            'baseline' => $baseline,
            'applied_at' => now(),
        ]);
        $this->entityFlash = 'Experiment für „'.$entity->name.'" gestartet (Baseline gesichert).';
    }

    /** Antwort-Einheiten für ALLE Mitglieder extrahieren (im Hintergrund, hash-gegatet). */
    public function extractAllAnswerUnits(): void
    {
        $members = $this->propertyView()['members'];
        $n = 0;
        foreach ($members as $u) {
            \Platform\Seo\Jobs\ExtractAnswerUnitsJob::dispatch($u->id, $this->portfolio->id);
            $n++;
        }
        $this->entityFlash = $n.' Extraktion(en) im Hintergrund gestartet — Entitäten erscheinen nach und nach (aktualisieren).';
    }

    /** Nachfrage-Seite laden: aus den Clustern des Wirkungsraums Entitäten ableiten. */
    public function syncDemandEntities(): void
    {
        $memberIds = $this->propertyView()['members']->pluck('id')->all();
        $clusterIds = $this->wirkungsraumClusterIds($memberIds);
        if (empty($clusterIds)) {
            $this->entityFlash = 'Keine Cluster im Wirkungsraum — erst in „Ordnen" Themen bauen.';

            return;
        }

        $clusters = SeoKeywordCluster::whereIn('id', $clusterIds)->get();
        $demand = SeoKeyword::whereIn('cluster_id', $clusterIds)
            ->selectRaw('cluster_id, SUM(search_volume) as vol')
            ->groupBy('cluster_id')->pluck('vol', 'cluster_id');

        $n = 0;
        foreach ($clusters as $c) {
            $e = SeoEntity::firstOrNew(['team_id' => $this->seoSettings->team_id, 'cluster_id' => $c->id]);
            $wasNew = ! $e->exists;
            $e->fill([
                'name' => $c->name,
                'entity_type' => 'concept',
                'search_volume' => (int) ($demand[$c->id] ?? 0),
            ])->save();
            if ($wasNew) {
                $n++;
            }
        }
        $this->entityFlash = $n.' Nachfrage-Entität(en) aus Clustern geladen ('.count($clusters).' Themen abgeglichen).';
    }

    /** Entitäten des Wirkungsraums semantisch zusammenführen (Angebot ↔ Nachfrage kanonisieren). */
    public function mergeEntities(): void
    {
        $memberIds = $this->propertyView()['members']->pluck('id')->all();
        $ids = $this->wirkungsraumEntityIds($memberIds);
        if (count($ids) < 2) {
            $this->entityFlash = 'Zu wenige Entitäten zum Zusammenführen.';

            return;
        }
        $res = app(\Platform\Seo\Services\SeoEntityMerger::class)->mergeForEntityIds($ids);
        $this->entityFlash = ($res['merged'] ?? 0) === 0
            ? 'Nichts zusammenzuführen — Entitäten sind bereits kanonisch.'
            : ($res['merged'] ?? 0).' Entität(en) semantisch zusammengeführt (Angebot ↔ Nachfrage kanonisiert).';
    }

    /** Alle Entity-IDs des Wirkungsraums (Angebot aus AnswerUnits + Nachfrage aus Clustern). @return int[] */
    protected function wirkungsraumEntityIds(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }
        $supply = SeoAnswerUnit::whereIn('url_id', $memberIds)->distinct()->pluck('entity_id')->all();
        $clusterIds = $this->wirkungsraumClusterIds($memberIds);
        $demand = empty($clusterIds) ? []
            : SeoEntity::where('team_id', $this->seoSettings->team_id)->whereIn('cluster_id', $clusterIds)->pluck('id')->all();

        return collect($supply)->merge($demand)->map(fn ($i) => (int) $i)->filter()->unique()->values()->all();
    }

    /** Cluster-IDs des Wirkungsraums (Themen, für die Mitglieder ranken). @return int[] */
    protected function wirkungsraumClusterIds(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }

        return DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $memberIds)
            ->whereNotNull('k.cluster_id')
            ->distinct()->pluck('k.cluster_id')->map(fn ($i) => (int) $i)->all();
    }

    /** Aktives AI-Zitat-Probing für eine Entität (Modell-Wissen, kein Live-Web). */
    public function probeEntityAi(int $entityId): void
    {
        $entity = SeoEntity::where('team_id', $this->seoSettings->team_id)->find($entityId);
        if (! $entity) {
            return;
        }
        $domains = $this->propertyView()['members']->pluck('domain')->filter()->unique()->values()->all();
        $res = app(\Platform\Seo\Services\SeoPresenceProbe::class)->probeAiCitation($entity, $domains);
        $this->entityFlash = ! empty($res['error'])
            ? 'Fehler: '.$res['error']
            : ($res['cited'] ? '✓ In der KI-Antwort erwähnt — Präsenz notiert.' : '— (noch) nicht in der KI-Antwort erwähnt.');
    }

    /**
     * Orchestrierungs-Board (Thema × Property): je Cluster im Verbund die eigenen
     * Properties, die dafür ranken (Kandidaten, beste Position), der gekürte Owner
     * (pillar_url_id), Kannibalisierungs-Konflikt (≥2 ohne Owner) und ein
     * heuristischer Pillar-Kandidat (hohe Nachfrage, kein Owner, fragmentiert).
     *
     * @return array{rows: array<int, array>}
     */
    protected function orchestrationBoard($members): array
    {
        $memberIds = $members->pluck('id')->all();
        if (empty($memberIds)) {
            return ['rows' => []];
        }

        // Kandidaten: welche Property rankt für welchen Cluster (beste Position).
        $cand = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $memberIds)
            ->whereNotNull('k.cluster_id')
            ->whereNotNull('uk.position')
            ->groupBy('k.cluster_id', 'uk.url_id')
            ->selectRaw('k.cluster_id as cluster_id, uk.url_id as url_id, MIN(uk.position) as pos')
            ->get()
            ->groupBy('cluster_id');

        if ($cand->isEmpty()) {
            return ['rows' => []];
        }

        $clusterIds = $cand->keys()->map(fn ($k) => (int) $k)->all();
        $clusters = SeoKeywordCluster::whereIn('id', $clusterIds)->get()->keyBy('id');
        $demand = SeoKeyword::whereIn('cluster_id', $clusterIds)
            ->selectRaw('cluster_id, SUM(search_volume) as vol')
            ->groupBy('cluster_id')->pluck('vol', 'cluster_id');

        $membersById = $members->keyBy('id');
        $pillarMin = (int) config('seo.pillar_candidate_min_volume', 2000);
        $rows = [];

        foreach ($clusterIds as $cid) {
            $c = $clusters[$cid] ?? null;
            if (! $c) {
                continue;
            }
            $candidates = collect($cand[$cid])->map(fn ($r) => [
                'url_id' => (int) $r->url_id,
                'label' => $membersById[$r->url_id]->display_label ?? ('#'.$r->url_id),
                'pos' => (int) $r->pos,
            ])->sortBy('pos')->values();

            $ownerId = $c->pillar_url_id ? (int) $c->pillar_url_id : null;
            $count = $candidates->count();
            $vol = (int) ($demand[$cid] ?? 0);

            $rows[] = [
                'cluster_id' => $cid,
                'name' => $c->name,
                'demand' => $vol,
                'candidates' => $candidates->all(),
                'candidate_count' => $count,
                'owner_id' => $ownerId,
                'owner_label' => $ownerId && isset($membersById[$ownerId]) ? $membersById[$ownerId]->display_label : ($ownerId ? '#'.$ownerId : null),
                'conflict' => $count >= 2 && $ownerId === null,
                'owner_not_ranking' => $ownerId !== null && ! $candidates->contains('url_id', $ownerId),
                'pillar_candidate' => $ownerId === null && $vol >= $pillarMin && $count >= 3,
            ];
        }

        usort($rows, fn ($a, $b) => $b['demand'] <=> $a['demand']);

        return ['rows' => $rows];
    }

    /**
     * Bestand-Sicht „Keywords": alle Keywords, für die Mitglieder dieses
     * Wirkungsraums (inkl. Unterseiten) ranken — nach Position sortiert.
     */
    protected function bestandKeywords(array $effectiveIds): \Illuminate\Support\Collection
    {
        if (empty($effectiveIds)) {
            return collect();
        }

        return SeoKeyword::whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $effectiveIds))
            ->with(['urls' => fn ($q) => $q->whereIn('seo_url_keywords.url_id', $effectiveIds), 'cluster'])
            ->get()
            ->sortBy(fn ($kw) => $kw->urls->min('pivot.position') ?? 999)
            ->take(200)
            ->values();
    }

    public function render()
    {
        $pv = $this->propertyView();
        $effectiveIds = $pv['effectiveIds'];
        $scope = app(SeoScopeMetrics::class)->forUrlIds($this->seoSettings->team_id, $effectiveIds);

        // Add-Modal: nur EIGENE, noch nicht zugeordnete URLs.
        $availableUrls = collect();
        if ($this->showAddUrls) {
            $existing = $this->portfolio->urls()->pluck('seo_urls.id');
            $q = SeoUrl::where('team_id', $this->seoSettings->team_id)
                ->where('is_own', true)
                ->whereNotIn('id', $existing);
            if ($this->urlSearch !== '') {
                $q->where('url', 'like', "%{$this->urlSearch}%");
            }
            $availableUrls = $q->orderBy('domain')->orderBy('path')->limit(50)->get();
        }

        $clusterable = $this->clusterableCount($effectiveIds, $this->clusterMinVolume);

        // Reifegrad + aktive (angezeigte) Phase — gated Werkbank: nur das
        // Werkzeug einer Phase zeigen, Default = aktuelles Gate.
        $health = app(SeoPortfolioHealth::class)->evaluate($this->portfolio);
        // $view steuert die innere Navigation. Für eine Station = das Gate;
        // für Dashboard/Bestand ein Nicht-Phasen-Wert, damit die Phasen-Gates
        // (@if in_array($activePhase, [...])) leer laufen und nur die jeweilige
        // Sicht zeigt.
        $station = in_array($this->view, self::PHASES, true) ? $this->view : null;
        $activePhase = $station ?? 'dashboard';
        $activePhaseLabel = collect($health['phases'])->firstWhere('key', $activePhase)['label'] ?? $activePhase;

        return view('seo::livewire.seo-portfolio-detail', [
            'health' => $health,
            'view' => $this->view,
            'station' => $station,
            'activePhase' => $activePhase,
            'activePhaseLabel' => $activePhaseLabel,
            'bestandKeywords' => $this->view === 'keywords' ? $this->bestandKeywords($effectiveIds) : collect(),
            'pageHealth' => $activePhase === 'verteilen'
                ? $this->pageHealth($effectiveIds)
                : ['unfocused' => [], 'cannibalized' => []],
            'members' => $pv['members'],
            'memberTotals' => $pv['memberTotals'],
            'availableProfiles' => $this->view === 'messen'
                ? app(\Platform\Seo\Services\SeoDataProfileService::class)->availableProfiles(true)
                : [],
            'board' => $this->view === 'verteilen' ? $this->orchestrationBoard($pv['members']) : ['rows' => []],
            'entities' => $this->view === 'entities' ? $this->wirkungsraumEntities($pv['members']) : ['rows' => [], 'total' => 0, 'present' => 0, 'share' => null],
            'measures' => $this->view === 'vertiefen'
                ? SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)
                    ->with(['targetUrl', 'targetCluster'])
                    ->orderByRaw("FIELD(status,'proposed','accepted','released','done','rejected')")
                    ->orderByDesc('score')->orderByDesc('created_at')->get()
                : collect(),
            'measureInbox' => [
                'proposed' => SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->where('status', 'proposed')->count(),
                'accepted' => SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->where('status', 'accepted')->count(),
                'top' => SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->where('status', 'proposed')->orderByDesc('score')->first(),
            ],
            'agg' => $pv['agg'],
            'availableUrls' => $availableUrls,
            'penetration' => ['clusters' => $scope['clusters'], 'unclustered' => $scope['unclustered']],
            'competitors' => $scope['competitors'],
            'coverage' => $scope['coverage'],
            'clusterable' => $clusterable,
            'clusterCostCents' => $clusterable * (int) config('seo.cost_estimates.serp', 10),
            'trend' => $this->trend($effectiveIds),
            'verbundWirkung' => $this->verbundWirkung($pv['members']),
            'verbundReferrals' => $this->verbundReferrals($pv['members']),
            'conversionTrend' => $this->conversionTrend($pv['members']->pluck('id')->all()),
            'semantic' => [
                'status' => $this->portfolio->semantic_status,
                'map' => $this->portfolio->semantic_map,
                'built_at' => $this->portfolio->semantic_built_at,
            ],
        ])->layout('platform::layouts.app');
    }
}
