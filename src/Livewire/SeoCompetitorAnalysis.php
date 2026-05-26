<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoAnalysisService;

class SeoCompetitorAnalysis extends Component
{
    use ResolvesTeamSettings;

    public SeoUrlList $seoUrlList;
    public ?string $filterDomain = null;
    public int $limit = 30;

    public function mount(SeoUrlList $seoUrlList)
    {
        $this->resolveSettings();
        $this->seoUrlList = $seoUrlList;
    }

    public function setDomainFilter(?string $domain)
    {
        $this->filterDomain = $domain;
        $this->limit = 30;
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    public function render()
    {
        $listUrlIds = $this->getListUrlIds();

        $analysisService = app(SeoAnalysisService::class);
        $gaps = $analysisService->getCompetitorGapsForTeam($this->seoSettings->team_id);

        $competitorDomains = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', false)
            ->whereHas('keywords', fn ($q) => $q->whereHas('urls', fn ($q2) => $q2->whereIn('seo_url_keywords.url_id', $listUrlIds)))
            ->selectRaw('domain, COUNT(*) as url_count, AVG(visibility_score) as avg_visibility, SUM(keyword_count) as total_keywords')
            ->groupBy('domain')
            ->orderByDesc('url_count')
            ->get();

        $competitorUrlQuery = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', false)
            ->whereHas('keywords', fn ($q) => $q->whereHas('urls', fn ($q2) => $q2->whereIn('seo_url_keywords.url_id', $listUrlIds)))
            ->orderByDesc('visibility_score');

        if ($this->filterDomain) {
            $competitorUrlQuery->where('domain', $this->filterDomain);
        }

        $allCompetitorUrls = $competitorUrlQuery->take($this->limit + 1)->get();
        $hasMore = $allCompetitorUrls->count() > $this->limit;
        $competitorUrls = $allCompetitorUrls->take($this->limit);

        return view('seo::livewire.seo-competitor-analysis', [
            'gaps' => $gaps,
            'competitorDomains' => $competitorDomains,
            'competitorUrls' => $competitorUrls,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }

    private function getListUrlIds(): array
    {
        $rootIds = $this->seoUrlList->urls()->pluck('seo_urls.id');
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $rootIds)
            ->pluck('target_url_id');

        return $rootIds->merge($childIds)->all();
    }
}
