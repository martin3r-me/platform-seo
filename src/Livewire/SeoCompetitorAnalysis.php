<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoAnalysisService;

class SeoCompetitorAnalysis extends Component
{
    use WithPagination;
    use ResolvesTeamSettings;

    public ?string $filterDomain = null;

    public function mount()
    {
        $this->resolveSettings();
    }

    public function setDomainFilter(?string $domain)
    {
        $this->filterDomain = $domain;
        $this->resetPage();
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $teamId = $this->seoSettings->team_id;
        $gaps = $analysisService->getCompetitorGapsForTeam($teamId);

        $competitorDomains = SeoUrl::where('team_id', $teamId)
            ->where('is_own', false)
            ->selectRaw('domain, COUNT(*) as url_count, AVG(visibility_score) as avg_visibility, SUM(keyword_count) as total_keywords')
            ->groupBy('domain')
            ->orderByDesc('url_count')
            ->get();

        $competitorUrlQuery = SeoUrl::where('team_id', $teamId)
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
