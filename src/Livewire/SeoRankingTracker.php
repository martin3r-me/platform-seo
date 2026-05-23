<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoUrlService;

class SeoRankingTracker extends Component
{
    use WithPagination;
    use ResolvesTeamSettings;

    public int $periodDays = 30;
    public string $filterType = 'all';

    public function mount()
    {
        $this->resolveSettings();
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
        $teamId = $this->seoSettings->team_id;
        $trends = $analysisService->getRankingTrendsForTeam($teamId, $this->periodDays);

        $urlService = app(SeoUrlService::class);
        $positionDistribution = $urlService->getVisibilitySummary(
            $teamId,
            $this->seoSettings->domain
        )['position_distribution'] ?? [];

        $ownUrlIds = SeoUrl::where('team_id', $teamId)->where('is_own', true)->pluck('id');

        $query = SeoRankingHistory::whereIn('url_id', $ownUrlIds)
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
