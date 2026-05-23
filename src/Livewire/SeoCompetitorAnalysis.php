<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoAnalysisService;

class SeoCompetitorAnalysis extends Component
{
    public SeoProject $seoProject;

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $gaps = $analysisService->getCompetitorGapsForProject($this->seoProject);

        return view('seo::livewire.seo-competitor-analysis', [
            'gaps' => $gaps,
        ])->layout('platform::layouts.app');
    }
}
