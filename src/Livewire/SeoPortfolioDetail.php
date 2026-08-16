<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Jobs\ClusterPortfolioRestJob;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
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

        return [
            'points' => $points,
            'count' => $count,
            'since' => $points[0]['date'],
            'current' => $current,
            'delta' => $count >= 2 ? $current - $first : null,
        ];
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

        return view('seo::livewire.seo-portfolio-detail', [
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
            'members' => $pv['members'],
            'memberTotals' => $pv['memberTotals'],
            'agg' => $pv['agg'],
            'availableUrls' => $availableUrls,
            'penetration' => ['clusters' => $scope['clusters'], 'unclustered' => $scope['unclustered']],
            'competitors' => $scope['competitors'],
            'coverage' => $scope['coverage'],
            'clusterable' => $clusterable,
            'clusterCostCents' => $clusterable * (int) config('seo.cost_estimates.serp', 10),
            'trend' => $this->trend($effectiveIds),
        ])->layout('platform::layouts.app');
    }
}
