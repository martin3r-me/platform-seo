<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlBacklink;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoScoringService;

class SeoUrlDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoUrl $seoUrl;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function render()
    {
        $scoringService = app(SeoScoringService::class);
        $urlVisibility = $scoringService->getUrlVisibilityScore($this->seoUrl);

        // Load child URLs (this URL is parent)
        $childRelations = SeoUrlRelationship::where('source_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('targetUrl')
            ->get();

        $childUrls = $childRelations->map(fn ($rel) => $rel->targetUrl)->filter();
        $childIds = $childUrls->pluck('id')->all();
        $allUrlIds = array_merge([$this->seoUrl->id], $childIds);

        // Keywords across root + children
        $keywords = SeoKeyword::whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
            ->with(['urls' => fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds)])
            ->get()
            ->sortBy(fn ($kw) => $kw->urls->min('pivot.position') ?? 999);

        // Rankings across root + children
        $rankingHistory = SeoRankingHistory::whereIn('url_id', $allUrlIds)
            ->with(['keyword', 'url'])
            ->orderByDesc('tracked_at')
            ->take(50)
            ->get();

        // Backlinks across root + children
        $backlinks = SeoUrlBacklink::whereIn('url_id', $allUrlIds)
            ->where('is_active', true)
            ->orderByDesc('source_domain_authority')
            ->take(50)
            ->get();

        // On-Page: only root URL
        $onPage = $this->seoUrl->onPage;

        // GSC data: root only
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

        // Parent URL (if this is a child)
        $parentRelation = SeoUrlRelationship::where('target_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('sourceUrl')
            ->first();
        $parentUrl = $parentRelation?->sourceUrl;

        // Aggregated stats
        $aggKeywordCount = $this->seoUrl->keyword_count + $childUrls->sum('keyword_count');
        $aggSearchVolume = $this->seoUrl->total_search_volume + $childUrls->sum('total_search_volume');
        $aggVisibility = (float) $this->seoUrl->visibility_score + (float) $childUrls->sum('visibility_score');
        $aggBacklinks = $this->seoUrl->backlink_count + $childUrls->sum('backlink_count');

        return view('seo::livewire.seo-url-detail', [
            'parentUrl' => $parentUrl,
            'urlVisibility' => $urlVisibility,
            'keywords' => $keywords,
            'rankingHistory' => $rankingHistory,
            'backlinks' => $backlinks,
            'onPage' => $onPage,
            'gscData' => $gscData,
            'registrations' => $registrations,
            'relationships' => $relationships,
            'childUrls' => $childUrls,
            'aggKeywordCount' => $aggKeywordCount,
            'aggSearchVolume' => $aggSearchVolume,
            'aggVisibility' => $aggVisibility,
            'aggBacklinks' => $aggBacklinks,
        ])->layout('platform::layouts.app');
    }
}
