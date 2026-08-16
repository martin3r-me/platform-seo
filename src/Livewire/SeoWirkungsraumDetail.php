<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Jobs\ClusterWirkungsraumRestJob;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoWirkungsraum;
use Platform\Seo\Services\SeoWirkungsraumAdvisor;

/**
 * Wirkungsraum-Detail — der Arbeitsraum (Slice 2: Listen-Niveau + Mitglieder-
 * Management). Aggregat-KPIs, Mitglieder-Tabelle, URLs zufügen/lösen. Steuer-
 * Invariante: nur eigene (kontrollierte) URLs. Steuer-Facetten (Durchdringung,
 * ungeclusterter Rest, Wettbewerber, KI) folgen in Slice 3/4.
 */
class SeoWirkungsraumDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoWirkungsraum $wirkungsraum;

    public bool $showAddUrls = false;
    public string $urlSearch = '';
    public array $selectedUrlIds = [];

    /** KI-Verteilungs-Vorschlag: ['text' => md] | ['error' => msg] | null. */
    public ?array $advice = null;

    /** Nur Keywords ab diesem Suchvolumen nach-clustern (spart Budget/Rauschen). */
    public int $clusterMinVolume = 10;

    public ?string $clusterFlash = null;

    public function mount(SeoWirkungsraum $seoWirkungsraum): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoWirkungsraum->team_id === (int) $this->seoSettings->team_id, 404);
        $this->wirkungsraum = $seoWirkungsraum;
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

        $this->wirkungsraum->urls()->syncWithoutDetaching(
            $ownIds->mapWithKeys(fn ($id) => [$id => ['added_at' => now()]])->all()
        );

        $this->reset('showAddUrls', 'selectedUrlIds', 'urlSearch');
    }

    public function removeUrl(int $urlId): void
    {
        $this->wirkungsraum->urls()->detach($urlId);
    }

    /**
     * KI-Verteilung: die vier Facetten in einen Aussteuerungs-Vorschlag gießen.
     */
    public function analyze(): void
    {
        $members = $this->wirkungsraum->urls()->orderByDesc('visibility_score')->get();
        $memberIds = $members->pluck('id')->all();
        $pen = $this->penetration($memberIds);
        $comp = $this->competitors($memberIds);

        $facets = [
            'members' => $members->map(fn ($u) => [
                'url' => $u->domain . ($u->path !== '/' ? $u->path : ''),
                'keywords' => (int) $u->keyword_count,
                'visibility' => (int) round((float) $u->visibility_score),
            ])->all(),
            'penetration' => $pen['clusters']->map(fn ($c) => [
                'name' => $c['name'], 'soll' => $c['soll'], 'ist' => $c['ist'], 'pct' => $c['pct'], 'volume' => $c['volume'],
            ])->all(),
            'unclustered' => $pen['unclustered'],
            'competitors' => $comp->map(fn ($c) => [
                'domain' => $c->domain, 'shared' => (int) $c->shared_keywords, 'visibility' => (int) round((float) $c->visibility),
            ])->all(),
            'own_visibility' => (int) round((float) $members->sum('visibility_score')),
        ];

        $this->advice = app(SeoWirkungsraumAdvisor::class)->advise($this->wirkungsraum, $facets);
    }

    /**
     * Nach-Clustern: den ungeclusterten Rest (wild rankende Keywords der
     * Mitglieder) zu Themen bündeln — abgegrenzt von bereits geclusterten
     * (der Service fasst nur cluster_id=null an). Läuft im Hintergrund (SERP).
     */
    public function clusterRest(): void
    {
        if (($this->wirkungsraum->clustering_status ?? null) === 'running') {
            return;
        }

        $memberIds = $this->wirkungsraum->urls()->where('is_own', true)->pluck('seo_urls.id')->all();
        $count = $this->clusterableCount($memberIds, $this->clusterMinVolume);

        if ($count < 2) {
            $this->clusterFlash = 'Nichts zu clustern — kein ungeclusterter Rest über der Volumen-Schwelle.';

            return;
        }

        $this->wirkungsraum->markClustering('running');
        ClusterWirkungsraumRestJob::dispatch($this->wirkungsraum->id, 3, $this->clusterMinVolume);

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
     * Durchdringung je Cluster: SOLL (Ziel-Keywords, an Mitglieds-URLs gehängt)
     * vs. IST (davon rankend = Pivot-Position gesetzt). Plus ungeclusterter Rest
     * (wild rankend). Der Kern-Steuer-Fakt des Wirkungsraums.
     */
    protected function penetration(array $memberIds): array
    {
        if (empty($memberIds)) {
            return ['clusters' => collect(), 'unclustered' => null];
        }

        // Je Keyword: beste Position über die Mitglieds-URLs (null = rankt nirgends = nur SOLL).
        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $memberIds)
            ->groupBy('k.id', 'k.cluster_id', 'k.search_volume')
            ->select('k.id', 'k.cluster_id', 'k.search_volume', DB::raw('MIN(uk.position) as best_position'))
            ->get();

        $groups = $rows->groupBy(fn ($r) => $r->cluster_id ?? 0);
        $build = fn ($kws) => [
            'soll' => $kws->count(),
            'ist' => $kws->filter(fn ($r) => $r->best_position !== null)->count(),
            'volume' => (int) $kws->sum('search_volume'),
        ];

        $unclusteredKws = $groups->get(0);
        $unclustered = $unclusteredKws ? $build($unclusteredKws) : null;

        $names = SeoKeywordCluster::whereIn('id', $groups->keys()->filter(fn ($k) => $k > 0))->pluck('name', 'id');

        $clusters = $groups->except([0])->map(function ($kws, $cid) use ($build, $names) {
            $b = $build($kws);
            $b['cluster_id'] = (int) $cid;
            $b['name'] = $names[(int) $cid] ?? ('#' . $cid);
            $b['pct'] = $b['soll'] > 0 ? (int) round($b['ist'] / $b['soll'] * 100) : 0;

            return $b;
        })->sortByDesc('volume')->values();

        return ['clusters' => $clusters, 'unclustered' => $unclustered];
    }

    /**
     * Wettbewerber-Benchmark: Domains, die sich mit dem Verbund um dieselben
     * Keywords balgen (Überlapp mit unseren Mitglieds-Keywords) + ihre Stärke.
     * Nicht Mitglied — der Markt drumherum, gegen den wir messen.
     */
    protected function competitors(array $memberIds): \Illuminate\Support\Collection
    {
        if (empty($memberIds)) {
            return collect();
        }

        return DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->join('seo_url_keywords as cuk', 'cuk.keyword_id', '=', 'k.id')
            ->join('seo_urls as cu', 'cu.id', '=', 'cuk.url_id')
            ->whereIn('uk.url_id', $memberIds)
            ->where('cu.is_own', false)
            ->where('cu.team_id', $this->seoSettings->team_id)
            ->groupBy('cu.domain')
            ->select('cu.domain', DB::raw('COUNT(DISTINCT k.id) as shared_keywords'), DB::raw('MAX(cu.visibility_score) as visibility'))
            ->orderByDesc('shared_keywords')
            ->limit(12)
            ->get();
    }

    public function render()
    {
        $members = $this->wirkungsraum->urls()
            ->orderByDesc('visibility_score')
            ->get();

        $agg = [
            'visibility' => (float) $members->sum('visibility_score'),
            'keywords' => (int) $members->sum('keyword_count'),
            'search_volume' => (int) $members->sum('total_search_volume'),
            'urls' => $members->count(),
        ];

        // Add-Modal: nur EIGENE, noch nicht zugeordnete URLs.
        $availableUrls = collect();
        if ($this->showAddUrls) {
            $existing = $this->wirkungsraum->urls()->pluck('seo_urls.id');
            $q = SeoUrl::where('team_id', $this->seoSettings->team_id)
                ->where('is_own', true)
                ->whereNotIn('id', $existing);
            if ($this->urlSearch !== '') {
                $q->where('url', 'like', "%{$this->urlSearch}%");
            }
            $availableUrls = $q->orderBy('domain')->orderBy('path')->limit(50)->get();
        }

        $memberIds = $members->pluck('id')->all();
        $clusterable = $this->clusterableCount($memberIds, $this->clusterMinVolume);

        return view('seo::livewire.seo-wirkungsraum-detail', [
            'members' => $members,
            'agg' => $agg,
            'availableUrls' => $availableUrls,
            'penetration' => $this->penetration($memberIds),
            'competitors' => $this->competitors($memberIds),
            'clusterable' => $clusterable,
            'clusterCostCents' => $clusterable * (int) config('seo.cost_estimates.serp', 10),
        ])->layout('platform::layouts.app');
    }
}
