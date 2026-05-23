<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamProject;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoScoringService;

class SeoUrlDetail extends Component
{
    use ResolvesTeamProject;

    public SeoUrl $seoUrl;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveProject();
        $this->seoUrl = $seoUrl;
    }

    public function render()
    {
        $scoringService = app(SeoScoringService::class);
        $urlVisibility = $scoringService->getUrlVisibilityScore($this->seoUrl);

        $keywords = $this->seoUrl->keywords()
            ->orderByPivot('position', 'asc')
            ->get();

        $rankingHistory = $this->seoUrl->rankingHistory()
            ->with('keyword')
            ->orderByDesc('tracked_at')
            ->take(50)
            ->get();

        $backlinks = $this->seoUrl->backlinks()
            ->where('is_active', true)
            ->orderByDesc('source_domain_authority')
            ->take(50)
            ->get();

        $onPage = $this->seoUrl->onPage;

        $gscData = $this->seoUrl->gscData()
            ->with('keyword')
            ->orderByDesc('date')
            ->take(50)
            ->get();

        $registrations = $this->seoUrl->registrations;

        $relationships = $this->seoUrl->sourceRelationships()
            ->with('targetUrl')
            ->get()
            ->merge(
                $this->seoUrl->targetRelationships()
                    ->with('sourceUrl')
                    ->get()
            );

        return view('seo::livewire.seo-url-detail', [
            'urlVisibility' => $urlVisibility,
            'keywords' => $keywords,
            'rankingHistory' => $rankingHistory,
            'backlinks' => $backlinks,
            'onPage' => $onPage,
            'gscData' => $gscData,
            'registrations' => $registrations,
            'relationships' => $relationships,
        ])->layout('platform::layouts.app');
    }
}
