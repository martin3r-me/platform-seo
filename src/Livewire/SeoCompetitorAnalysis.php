<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoAnalysisService;

class SeoCompetitorAnalysis extends Component
{
    use WithPagination;

    public SeoProject $seoProject;
    public ?string $filterDomain = null;

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function setDomainFilter(?string $domain)
    {
        $this->filterDomain = $domain;
        $this->resetPage();
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $gaps = $analysisService->getCompetitorGapsForProject($this->seoProject);

        // Domain overview: group competitor URLs by domain
        $competitorDomains = $this->seoProject->urls()
            ->where('is_own', false)
            ->selectRaw('domain, COUNT(*) as url_count, AVG(visibility_score) as avg_visibility, SUM(keyword_count) as total_keywords')
            ->groupBy('domain')
            ->orderByDesc('url_count')
            ->get();

        // Competitor URLs table (filterable by domain)
        $competitorUrlQuery = $this->seoProject->urls()
            ->where('is_own', false)
            ->orderByDesc('visibility_score');

        if ($this->filterDomain) {
            $competitorUrlQuery->where('domain', $this->filterDomain);
        }

        $competitorUrls = $competitorUrlQuery->paginate(30);

        return view('seo::livewire.seo-competitor-analysis', [
            'gaps' => $gaps,
            'competitorDomains' => $competitorDomains,
            'competitorUrls' => $competitorUrls,
        ])->layout('platform::layouts.app');
    }
}
