<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\Computed;
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

    public string $activeTab = 'keywords';

    public ?int $selectedKeywordId = null;

    // Infinite scroll limits
    public int $keywordLimit = 50;
    public int $rankingLimit = 50;
    public int $backlinkLimit = 50;
    public int $gscLimit = 50;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->selectedKeywordId = null;
    }

    public function selectKeyword(int $keywordId)
    {
        $this->selectedKeywordId = $this->selectedKeywordId === $keywordId ? null : $keywordId;
    }

    public function setCompetitorDepth(int $keywordId, ?int $depth): void
    {
        $keyword = SeoKeyword::findOrFail($keywordId);
        $keyword->update([
            'competitor_tracking_depth' => $depth ?: null,
        ]);
    }

    public function loadMore(): void
    {
        match ($this->activeTab) {
            'keywords' => $this->keywordLimit += 50,
            'rankings' => $this->rankingLimit += 50,
            'backlinks' => $this->backlinkLimit += 50,
            'gsc' => $this->gscLimit += 50,
            default => null,
        };
    }

    private function getAllUrlIds(): array
    {
        return $this->getChildData()['allUrlIds'];
    }

    public function assignToNode(int $entityId): void
    {
        app(\Platform\Seo\Services\SeoOrganizationLinker::class)
            ->addNode(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, $this->seoUrl->id, $entityId);
    }

    public function removeFromNode(int $entityId): void
    {
        app(\Platform\Seo\Services\SeoOrganizationLinker::class)
            ->unlink(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, $this->seoUrl->id, $entityId);
    }

    #[Computed(persist: true)]
    public function childData(): array
    {
        $childRelations = SeoUrlRelationship::where('source_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('targetUrl')
            ->get();

        $childUrls = $childRelations->map(fn ($rel) => $rel->targetUrl)->filter();
        $childIds = $childUrls->pluck('id')->all();
        $allUrlIds = array_merge([$this->seoUrl->id], $childIds);

        return [
            'childUrls' => $childUrls,
            'allUrlIds' => $allUrlIds,
        ];
    }

    private function getChildData(): array
    {
        return $this->childData;
    }

    #[Computed]
    public function selectedKeyword()
    {
        if (! $this->selectedKeywordId) {
            return null;
        }

        return SeoKeyword::with([
            'cluster',
            'competitors' => fn ($q) => $q->orderBy('position')->limit(10),
            'positions' => fn ($q) => $q->latest('tracked_at')->limit(1),
        ])->find($this->selectedKeywordId);
    }

    #[Computed]
    public function selectedKeywordUrls()
    {
        if (! $this->selectedKeywordId) {
            return collect();
        }

        $allUrlIds = $this->getAllUrlIds();

        return SeoUrl::whereIn('id', $allUrlIds)
            ->whereHas('keywords', fn ($q) => $q->where('seo_keywords.id', $this->selectedKeywordId))
            ->with(['keywords' => fn ($q) => $q->where('seo_keywords.id', $this->selectedKeywordId)])
            ->get();
    }

    #[Computed]
    public function selectedKeywordHistory()
    {
        if (! $this->selectedKeywordId) {
            return collect();
        }

        return SeoRankingHistory::where('keyword_id', $this->selectedKeywordId)
            ->orderBy('tracked_at', 'desc')
            ->limit(30)
            ->get()
            ->reverse()
            ->values();
    }

    public function render()
    {
        $data = $this->getChildData();
        $childUrls = $data['childUrls'];
        $allUrlIds = $data['allUrlIds'];

        // Always: aggregate stats (cheap — uses cached fields on SeoUrl)
        $aggKeywordCount = $this->seoUrl->keyword_count + $childUrls->sum('keyword_count');
        $aggSearchVolume = $this->seoUrl->total_search_volume + $childUrls->sum('total_search_volume');
        $aggVisibility = (float) $this->seoUrl->visibility_score + (float) $childUrls->sum('visibility_score');
        $aggBacklinks = $this->seoUrl->backlink_count + $childUrls->sum('backlink_count');

        // Always: on-page score for stats bar (just the score, not full data)
        $onPageScore = $this->seoUrl->onPage?->overall_score;

        // Always: parent URL for breadcrumb
        $parentRelation = SeoUrlRelationship::where('target_url_id', $this->seoUrl->id)
            ->where('type', 'parent_child')
            ->with('sourceUrl')
            ->first();
        $parentUrl = $parentRelation?->sourceUrl;

        // Tab-specific data
        $keywords = collect();
        $rankingHistory = collect();
        $backlinks = collect();
        $onPage = null;
        $gscData = collect();
        $relationships = collect();
        $hasMore = false;

        switch ($this->activeTab) {
            case 'keywords':
                $keywords = SeoKeyword::whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
                    ->with(['urls' => fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds), 'competitors'])
                    ->get()
                    ->sortBy(fn ($kw) => $kw->urls->min('pivot.position') ?? 999)
                    ->values();
                $hasMore = $keywords->count() > $this->keywordLimit;
                $keywords = $keywords->take($this->keywordLimit);
                break;

            case 'rankings':
                $rankingHistory = SeoRankingHistory::whereIn('url_id', $allUrlIds)
                    ->with(['keyword', 'url'])
                    ->orderByDesc('tracked_at')
                    ->take($this->rankingLimit + 1)
                    ->get();
                $hasMore = $rankingHistory->count() > $this->rankingLimit;
                $rankingHistory = $rankingHistory->take($this->rankingLimit);
                break;

            case 'backlinks':
                $backlinks = SeoUrlBacklink::whereIn('url_id', $allUrlIds)
                    ->where('is_active', true)
                    ->orderByDesc('source_domain_authority')
                    ->take($this->backlinkLimit + 1)
                    ->get();
                $hasMore = $backlinks->count() > $this->backlinkLimit;
                $backlinks = $backlinks->take($this->backlinkLimit);
                break;

            case 'onpage':
                $onPage = $this->seoUrl->onPage;
                break;

            case 'gsc':
                $gscData = $this->seoUrl->gscData()
                    ->with('keyword')
                    ->orderByDesc('date')
                    ->take($this->gscLimit + 1)
                    ->get();
                $hasMore = $gscData->count() > $this->gscLimit;
                $gscData = $gscData->take($this->gscLimit);
                break;

            case 'relationships':
                $relationships = $this->seoUrl->sourceRelationships()
                    ->with('targetUrl')
                    ->get()
                    ->merge(
                        $this->seoUrl->targetRelationships()
                            ->with('sourceUrl')
                            ->get()
                    );
                break;
        }

        // Organisations-Knoten: aktuell verlinkte + verfügbare (lose gekoppelt, guarded).
        $linker = app(\Platform\Seo\Services\SeoOrganizationLinker::class);
        $contextNodes = $linker->nodesForMany(\Platform\Seo\Services\SeoOrganizationLinker::ALIAS_URL, [$this->seoUrl->id])[$this->seoUrl->id] ?? [];
        $availableNodes = $linker->availableNodes((int) $this->seoUrl->team_id);

        return view('seo::livewire.seo-url-detail', [
            'contextNodes' => $contextNodes,
            'availableNodes' => $availableNodes,
            'parentUrl' => $parentUrl,
            'keywords' => $keywords,
            'rankingHistory' => $rankingHistory,
            'backlinks' => $backlinks,
            'onPage' => $onPage,
            'onPageScore' => $onPageScore,
            'gscData' => $gscData,
            'relationships' => $relationships,
            'childUrls' => $childUrls,
            'aggKeywordCount' => $aggKeywordCount,
            'aggSearchVolume' => $aggSearchVolume,
            'aggVisibility' => $aggVisibility,
            'aggBacklinks' => $aggBacklinks,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
