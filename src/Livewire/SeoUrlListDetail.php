<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;

class SeoUrlListDetail extends Component
{
    public SeoUrlList $seoUrlList;

    // Add URLs modal
    public bool $showAddUrlsModal = false;
    public string $urlSearch = '';
    public array $selectedUrlIds = [];

    // Selected URL for detail panel
    public ?int $selectedUrlId = null;

    public function mount(SeoUrlList $seoUrlList): void
    {
        $this->seoUrlList = $seoUrlList;
    }

    public function selectUrl(int $urlId): void
    {
        $this->selectedUrlId = $this->selectedUrlId === $urlId ? null : $urlId;
    }

    public function openAddUrlsModal(): void
    {
        $this->urlSearch = '';
        $this->selectedUrlIds = [];
        $this->showAddUrlsModal = true;
    }

    public function addUrlsToList(): void
    {
        if (empty($this->selectedUrlIds)) {
            return;
        }

        $this->seoUrlList->urls()->syncWithoutDetaching(
            collect($this->selectedUrlIds)->mapWithKeys(fn ($id) => [
                $id => ['added_at' => now()],
            ])->all()
        );

        $this->showAddUrlsModal = false;
        $this->selectedUrlIds = [];
        $this->urlSearch = '';
    }

    public function removeUrlFromList(int $urlId): void
    {
        $this->seoUrlList->urls()->detach($urlId);
        if ($this->selectedUrlId === $urlId) {
            $this->selectedUrlId = null;
        }
    }

    private function getUrlWithChildIds(int $urlId): array
    {
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->where('source_url_id', $urlId)
            ->pluck('target_url_id');

        return collect([$urlId])->merge($childIds)->all();
    }

    #[Computed]
    public function selectedUrl()
    {
        if (! $this->selectedUrlId) {
            return null;
        }

        return SeoUrl::find($this->selectedUrlId);
    }

    #[Computed]
    public function selectedUrlKeywords()
    {
        if (! $this->selectedUrlId) {
            return collect();
        }

        $allUrlIds = $this->getUrlWithChildIds($this->selectedUrlId);

        return SeoKeyword::whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
            ->with(['urls' => fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds)])
            ->orderByDesc('search_volume')
            ->limit(50)
            ->get();
    }

    public function render()
    {
        $rootUrls = $this->seoUrlList->urls;
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $rootUrls->pluck('id'))
            ->pluck('target_url_id');

        $allRelatedUrls = SeoUrl::whereIn('id', $rootUrls->pluck('id')->merge($childIds))->get();

        $aggregated = ['visibility_score' => 0, 'keyword_count' => 0, 'total_search_volume' => 0, 'backlink_count' => 0];
        foreach ($allRelatedUrls as $url) {
            $aggregated['visibility_score'] += (float) $url->visibility_score;
            $aggregated['keyword_count'] += $url->keyword_count;
            $aggregated['total_search_volume'] += $url->total_search_volume;
            $aggregated['backlink_count'] += $url->backlink_count;
        }

        $listUrls = $rootUrls->map(function (SeoUrl $url) {
            $childIds = SeoUrlRelationship::where('type', 'parent_child')
                ->where('source_url_id', $url->id)
                ->pluck('target_url_id');
            $children = $childIds->isNotEmpty() ? SeoUrl::whereIn('id', $childIds)->get() : collect();

            $url->agg_visibility = (float) $url->visibility_score + $children->sum('visibility_score');
            $url->agg_keywords = $url->keyword_count + $children->sum('keyword_count');
            $url->agg_search_volume = $url->total_search_volume + $children->sum('total_search_volume');
            $url->agg_backlinks = $url->backlink_count + $children->sum('backlink_count');
            $url->child_count = $children->count();

            return $url;
        });

        $availableUrls = collect();
        if ($this->showAddUrlsModal) {
            $rootUrlIds = SeoUrlRelationship::where('type', 'parent_child')
                ->select('target_url_id');

            $query = SeoUrl::whereNotIn('id', $rootUrlIds);

            $existingIds = $this->seoUrlList->urls()->pluck('seo_urls.id');
            if ($existingIds->isNotEmpty()) {
                $query->whereNotIn('id', $existingIds);
            }

            if ($this->urlSearch) {
                $query->where('url', 'like', "%{$this->urlSearch}%");
            }

            $availableUrls = $query->orderBy('domain')->orderBy('path')->limit(50)->get();
        }

        return view('seo::livewire.seo-url-list-detail', [
            'listUrls' => $listUrls,
            'availableUrls' => $availableUrls,
            'aggregated' => $aggregated,
        ])->layout('platform::layouts.app');
    }
}
