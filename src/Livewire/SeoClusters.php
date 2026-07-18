<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoClusterSnapshot;
use Platform\Seo\Models\SeoKeywordCluster;

/**
 * Cluster-Erfolgssicht — macht die KPI der strategischen Einheit (P2) sichtbar:
 * Abdeckung, Health, Sichtbarkeit, Traffic und die Trajektorie über die Zeit.
 */
class SeoClusters extends Component
{
    use ResolvesTeamSettings;

    public string $sort = 'health';   // health | coverage | visibility | keywords
    public int $limit = 25;

    private const SORT_COLUMNS = [
        'health' => 'health_score',
        'coverage' => 'coverage_pct',
        'visibility' => 'visibility',
        'keywords' => 'keyword_count',
    ];

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function setSort(string $sort): void
    {
        $this->sort = $sort;
        $this->limit = 25;
    }

    public function loadMore(): void
    {
        $this->limit += 25;
    }

    public function render()
    {
        $teamId = $this->seoSettings->team_id;
        $column = self::SORT_COLUMNS[$this->sort] ?? 'health_score';

        $all = SeoKeywordCluster::where('team_id', $teamId)
            ->orderByDesc($column)
            ->orderBy('name')
            ->take($this->limit + 1)
            ->get();

        $hasMore = $all->count() > $this->limit;
        $clusters = $all->take($this->limit);

        // Trajektorie (Sichtbarkeit über die Zeit) je Cluster — ein Query, gruppiert.
        $trajectories = SeoClusterSnapshot::whereIn('cluster_id', $clusters->pluck('id'))
            ->where('snapshot_date', '>=', now()->subDays(90)->toDateString())
            ->orderBy('snapshot_date')
            ->get(['cluster_id', 'visibility'])
            ->groupBy('cluster_id')
            ->map(fn ($snaps) => $snaps->pluck('visibility')->map(fn ($v) => (float) $v)->all())
            ->all();

        return view('seo::livewire.seo-clusters', [
            'clusters' => $clusters,
            'trajectories' => $trajectories,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
