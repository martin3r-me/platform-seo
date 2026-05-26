<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoUrlService;

class SeoRankingTracker extends Component
{
    use ResolvesTeamSettings;

    public SeoUrl $seoUrl;

    public int $periodDays = 30;
    public string $filterType = 'all';
    public int $limit = 50;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function setPeriod(int $days)
    {
        $this->periodDays = $days;
        $this->limit = 50;
    }

    public function setFilterType(string $type)
    {
        $this->filterType = $type;
        $this->limit = 50;
    }

    public function loadMore(): void
    {
        $this->limit += 50;
    }

    private function getAllUrlIds(): array
    {
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->where('source_url_id', $this->seoUrl->id)
            ->pluck('target_url_id');

        return collect([$this->seoUrl->id])->merge($childIds)->all();
    }

    public function render()
    {
        $allUrlIds = $this->getAllUrlIds();

        $analysisService = app(SeoAnalysisService::class);
        $trends = $analysisService->getRankingTrendsForTeam($this->seoSettings->team_id, $this->periodDays);

        $urlService = app(SeoUrlService::class);
        $positionDistribution = $urlService->getVisibilitySummary(
            $this->seoSettings->team_id,
            $this->seoSettings->domain
        )['position_distribution'] ?? [];

        $query = SeoRankingHistory::whereIn('url_id', $allUrlIds)
            ->with(['url', 'keyword'])
            ->where('tracked_at', '>=', now()->subDays($this->periodDays))
            ->orderByDesc('tracked_at');

        if ($this->filterType === 'winners') {
            $query->whereColumn('previous_position', '>', 'position')
                ->whereNotNull('previous_position');
        } elseif ($this->filterType === 'losers') {
            $query->where(function ($q) {
                $q->whereColumn('previous_position', '<', 'position')
                    ->whereNotNull('previous_position');
            });
        }

        $allRankings = $query->take($this->limit + 1)->get();
        $hasMore = $allRankings->count() > $this->limit;
        $rankings = $allRankings->take($this->limit);

        return view('seo::livewire.seo-ranking-tracker', [
            'trends' => $trends,
            'positionDistribution' => $positionDistribution,
            'rankings' => $rankings,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
