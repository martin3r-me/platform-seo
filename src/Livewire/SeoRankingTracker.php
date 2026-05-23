<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoUrlService;

class SeoRankingTracker extends Component
{
    use WithPagination;

    public SeoProject $seoProject;

    public int $periodDays = 30;
    public string $filterType = 'all'; // all, winners, losers

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function setPeriod(int $days)
    {
        $this->periodDays = $days;
        $this->resetPage();
    }

    public function setFilterType(string $type)
    {
        $this->filterType = $type;
        $this->resetPage();
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $trends = $analysisService->getRankingTrendsForProject($this->seoProject, $this->periodDays);

        // Position distribution for chart
        $urlService = app(SeoUrlService::class);
        $positionDistribution = $urlService->getVisibilitySummary(
            $this->seoProject->team_id,
            $this->seoProject->domain
        )['position_distribution'] ?? [];

        // Ranking history table with URL+Keyword
        $query = SeoRankingHistory::whereHas('url', fn ($q) => $q->where('project_id', $this->seoProject->id)->where('is_own', true))
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

        $rankings = $query->paginate(50);

        return view('seo::livewire.seo-ranking-tracker', [
            'trends' => $trends,
            'positionDistribution' => $positionDistribution,
            'rankings' => $rankings,
        ])->layout('platform::layouts.app');
    }
}
