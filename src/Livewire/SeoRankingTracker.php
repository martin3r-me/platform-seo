<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoAnalysisService;

class SeoRankingTracker extends Component
{
    public SeoProject $seoProject;

    public int $periodDays = 30;

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function setPeriod(int $days)
    {
        $this->periodDays = $days;
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $trends = $analysisService->getRankingTrends($this->seoProject, $this->periodDays);

        return view('seo::livewire.seo-ranking-tracker', [
            'trends' => $trends,
        ])->layout('platform::layouts.app');
    }
}
