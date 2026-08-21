<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoClusterSnapshot;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Services\SeoClusterMetricsService;

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
        'keywords' => 'keywords_count',
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
            ->withCount([
                'keywords',
                'contentBriefs',
                'contentBriefs as published_briefs_count' => fn ($q) => $q->where('status', 'published'),
            ])
            ->orderByDesc($column)
            ->orderBy('name')
            ->take($this->limit + 1)
            ->get();

        $hasMore = $all->count() > $this->limit;
        $clusters = $all->take($this->limit);

        // Ehrliche Durchdringung je Cluster — LIVE (nicht das stale, binäre
        // coverage_pct): positionsgewichtet (Pos 1 zählt voll, tiefe kaum) und
        // OHNE ignorierte Keywords (retired_at). SOLL = nicht-ignorierte Keywords,
        // IST = wie viele davon eine eigene Position haben.
        $svc = app(SeoClusterMetricsService::class);
        $ctr1 = $svc->ctr(1);
        $metrics = [];
        if ($clusters->isNotEmpty()) {
            $rows = DB::table('seo_keywords as k')
                ->leftJoin('seo_url_keywords as uk', 'uk.keyword_id', '=', 'k.id')
                ->leftJoin('seo_urls as u', function ($j) {
                    $j->on('u.id', '=', 'uk.url_id')->where('u.is_own', true)->whereNull('u.deleted_at');
                })
                ->whereIn('k.cluster_id', $clusters->pluck('id'))
                ->whereNull('k.retired_at')
                ->groupBy('k.cluster_id', 'k.id')
                ->selectRaw('k.cluster_id, k.id, MIN(CASE WHEN u.id IS NOT NULL AND uk.position IS NOT NULL THEN uk.position END) as best_pos')
                ->get();

            foreach ($rows->groupBy('cluster_id') as $cid => $kws) {
                $soll = $kws->count();
                $ist = 0;
                $weighted = 0.0;
                foreach ($kws as $r) {
                    if ($r->best_pos !== null) {
                        $ist++;
                        $weighted += $svc->ctr((int) $r->best_pos) / $ctr1;
                    }
                }
                $metrics[(int) $cid] = [
                    'soll' => $soll,
                    'ist' => $ist,
                    'pen' => $soll > 0 ? (int) round($weighted / $soll * 100) : 0,
                ];
            }
        }

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
            'metrics' => $metrics,
            'trajectories' => $trajectories,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
