<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoClusterSnapshot;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Cluster-Detailseite (U3) — der Drill-down der strategischen Einheit.
 *
 * Zeigt KPIs + Trajektorie, die Keywords (mit bester eigener Position), die
 * verknüpften Content-Briefs und die Kontext-Zuordnung des Clusters (seo_cluster
 * an Org-Knoten — „Cluster machen nur im Kontext Sinn").
 */
class SeoClusterDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoKeywordCluster $cluster;

    public function mount(SeoKeywordCluster $cluster): void
    {
        $this->resolveSettings();
        $this->cluster = $cluster;
    }

    public function assignToNode(int $entityId): void
    {
        app(SeoOrganizationLinker::class)
            ->addNode(SeoOrganizationLinker::ALIAS_CLUSTER, $this->cluster->id, $entityId);
    }

    public function removeFromNode(int $entityId): void
    {
        app(SeoOrganizationLinker::class)
            ->unlink(SeoOrganizationLinker::ALIAS_CLUSTER, $this->cluster->id, $entityId);
    }

    /**
     * Pillar-URL setzen — die eine Seite, die dieses Thema besitzt.
     * Nur eigene, team-eigene URLs sind zulässig.
     */
    public function setPillarUrl(int $urlId): void
    {
        $url = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', true)
            ->find($urlId);

        if ($url) {
            $this->cluster->update(['pillar_url_id' => $url->id]);
            $this->cluster->refresh();
        }
    }

    public function clearPillarUrl(): void
    {
        $this->cluster->update(['pillar_url_id' => null]);
        $this->cluster->refresh();
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $keywords = SeoKeyword::where('cluster_id', $this->cluster->id)
            ->orderByDesc('search_volume')
            ->limit(200)
            ->get();

        // Alle Cluster-Keywords (nicht nur die 200 angezeigten) für die Aggregate.
        $allClusterKw = SeoKeyword::where('cluster_id', $this->cluster->id)->get(['id', 'search_volume']);
        $allKwIds = $allClusterKw->pluck('id');
        $clusterVolume = (int) $allClusterKw->sum('search_volume');

        // GSC-IST — echte Google-Zahlen für die Cluster-Keywords (letzte 30 Tage).
        // Getrennt von den DataForSeo/SERP-basierten KPIs oben: das ist die Realität.
        $gscRow = DB::table('seo_url_gsc_data')
            ->whereIn('keyword_id', $allKwIds)
            ->where('date', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('COUNT(DISTINCT keyword_id) as kw_ranked, SUM(clicks) as clicks, SUM(impressions) as impressions, SUM(avg_position * impressions) as wpos')
            ->first();

        $gscClicks = (int) ($gscRow->clicks ?? 0);
        $gscImpressions = (int) ($gscRow->impressions ?? 0);
        $gscPotential = (int) round($clusterVolume * 0.30); // Top-Position-CTR als Deckel
        $gscIst = [
            'kw_ranked' => (int) ($gscRow->kw_ranked ?? 0),
            'kw_total' => $allKwIds->count(),
            'clicks' => $gscClicks,
            'impressions' => $gscImpressions,
            'avg_position' => $gscImpressions > 0 ? round(((float) $gscRow->wpos) / $gscImpressions, 1) : null,
            'potential' => $gscPotential,
            'gap' => max(0, $gscPotential - $gscClicks),
        ];

        // Pillar-Kandidaten — eigene URLs, die für Cluster-Keywords ranken,
        // sortiert nach abgedeckten Keywords (die natürliche Besitzer-Reihenfolge).
        $pillarCandidates = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', function ($join) use ($teamId) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at')
                    ->where('u.team_id', $teamId);
            })
            ->whereIn('uk.keyword_id', $allKwIds)
            ->groupBy('u.id', 'u.url', 'u.path')
            ->selectRaw('u.id, u.url, u.path, COUNT(DISTINCT uk.keyword_id) as kw_covered')
            ->orderByDesc('kw_covered')
            ->limit(30)
            ->get();

        // Fallback-Pool: übrige eigene Seiten (für Whitespace-Cluster, die noch
        // für nichts ranken → keine Kandidaten, aber genau dort will man eine
        // Pillar bestimmen/bauen). Ohne die bereits gelisteten Kandidaten.
        $candidateIds = $pillarCandidates->pluck('id')->all();
        $otherOwnUrls = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->whereNotIn('id', $candidateIds ?: [0])
            ->orderBy('path')
            ->limit(200)
            ->get(['id', 'url', 'path']);

        // Beste eigene Position je Keyword.
        $bestPosition = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', function ($join) use ($teamId) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at')
                    ->where('u.team_id', $teamId);
            })
            ->whereIn('uk.keyword_id', $keywords->pluck('id'))
            ->whereNotNull('uk.position')
            ->groupBy('uk.keyword_id')
            ->select('uk.keyword_id', DB::raw('MIN(uk.position) as best'))
            ->pluck('best', 'uk.keyword_id');

        $trajectory = SeoClusterSnapshot::where('cluster_id', $this->cluster->id)
            ->where('snapshot_date', '>=', now()->subDays(90)->toDateString())
            ->orderBy('snapshot_date')
            ->pluck('visibility')
            ->map(fn ($v) => (float) $v)
            ->all();

        $contentBriefs = $this->cluster->contentBriefs()->get();

        $linker = app(SeoOrganizationLinker::class);
        $contextNodes = $linker->nodesForMany(SeoOrganizationLinker::ALIAS_CLUSTER, [$this->cluster->id])[$this->cluster->id] ?? [];
        $availableNodes = $linker->availableNodes($teamId);

        return view('seo::livewire.seo-cluster-detail', [
            'keywords' => $keywords,
            'bestPosition' => $bestPosition,
            'trajectory' => $trajectory,
            'contentBriefs' => $contentBriefs,
            'contextNodes' => $contextNodes,
            'availableNodes' => $availableNodes,
            'gscIst' => $gscIst,
            'pillarCandidates' => $pillarCandidates,
            'otherOwnUrls' => $otherOwnUrls,
            'pillarUrl' => $this->cluster->pillarUrl,
        ])->layout('platform::layouts.app');
    }
}
