<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoClusterSnapshot;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
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

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $keywords = SeoKeyword::where('cluster_id', $this->cluster->id)
            ->orderByDesc('search_volume')
            ->limit(200)
            ->get();

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
        ])->layout('platform::layouts.app');
    }
}
