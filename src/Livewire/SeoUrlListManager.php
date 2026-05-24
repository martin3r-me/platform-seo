<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;

class SeoUrlListManager extends Component
{
    public ?int $activeListId = null;

    // Create/Edit modal
    public bool $showListModal = false;
    public ?int $editingListId = null;
    public string $listName = '';
    public string $listDescription = '';

    // Add URLs modal
    public bool $showAddUrlsModal = false;
    public string $urlSearch = '';
    public array $selectedUrlIds = [];

    public function selectList(int $id): void
    {
        $this->activeListId = $id;
    }

    // -------------------------------------------------------------------------
    // List CRUD
    // -------------------------------------------------------------------------

    public function openCreateModal(): void
    {
        $this->editingListId = null;
        $this->listName = '';
        $this->listDescription = '';
        $this->showListModal = true;
    }

    public function openEditModal(int $id): void
    {
        $list = SeoUrlList::findOrFail($id);
        $this->editingListId = $list->id;
        $this->listName = $list->name;
        $this->listDescription = $list->description ?? '';
        $this->showListModal = true;
    }

    public function saveList(): void
    {
        $this->validate([
            'listName' => 'required|string|max:255',
            'listDescription' => 'nullable|string|max:1000',
        ]);

        if ($this->editingListId) {
            $list = SeoUrlList::findOrFail($this->editingListId);
            $list->update([
                'name' => $this->listName,
                'slug' => Str::slug($this->listName),
                'description' => $this->listDescription ?: null,
            ]);
        } else {
            $list = SeoUrlList::create([
                'name' => $this->listName,
                'slug' => Str::slug($this->listName),
                'description' => $this->listDescription ?: null,
                'created_by' => auth()->id(),
            ]);
            $this->activeListId = $list->id;
        }

        $this->showListModal = false;
        $this->listName = '';
        $this->listDescription = '';
        $this->editingListId = null;
    }

    public function deleteList(int $id): void
    {
        SeoUrlList::findOrFail($id)->delete();

        if ($this->activeListId === $id) {
            $this->activeListId = null;
        }
    }

    // -------------------------------------------------------------------------
    // URL management
    // -------------------------------------------------------------------------

    public function openAddUrlsModal(): void
    {
        $this->urlSearch = '';
        $this->selectedUrlIds = [];
        $this->showAddUrlsModal = true;
    }

    public function addUrlsToList(): void
    {
        if (! $this->activeListId || empty($this->selectedUrlIds)) {
            return;
        }

        $list = SeoUrlList::findOrFail($this->activeListId);
        $list->urls()->syncWithoutDetaching(
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
        if (! $this->activeListId) {
            return;
        }

        $list = SeoUrlList::findOrFail($this->activeListId);
        $list->urls()->detach($urlId);
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        $lists = SeoUrlList::withCount('urls')->orderBy('name')->get();

        $activeList = null;
        $listUrls = collect();
        $availableUrls = collect();
        $aggregated = ['visibility_score' => 0, 'keyword_count' => 0, 'total_search_volume' => 0, 'backlink_count' => 0];

        if ($this->activeListId) {
            $activeList = SeoUrlList::with('urls')->find($this->activeListId);

            if ($activeList) {
                // Root URLs in this list + their children metrics
                $rootUrls = $activeList->urls;
                $childIds = SeoUrlRelationship::where('type', 'parent_child')
                    ->whereIn('source_url_id', $rootUrls->pluck('id'))
                    ->pluck('target_url_id');

                $allRelatedUrls = SeoUrl::whereIn('id', $rootUrls->pluck('id')->merge($childIds))->get();

                foreach ($allRelatedUrls as $url) {
                    $aggregated['visibility_score'] += (float) $url->visibility_score;
                    $aggregated['keyword_count'] += $url->keyword_count;
                    $aggregated['total_search_volume'] += $url->total_search_volume;
                    $aggregated['backlink_count'] += $url->backlink_count;
                }

                // Per root-URL: aggregate children
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
            }
        }

        // Available root URLs for the "add" modal
        if ($this->showAddUrlsModal && $this->activeListId) {
            $rootUrlIds = SeoUrlRelationship::where('type', 'parent_child')
                ->select('target_url_id');

            $query = SeoUrl::whereNotIn('id', $rootUrlIds);

            if ($this->activeListId) {
                $existingIds = SeoUrlList::find($this->activeListId)?->urls()->pluck('seo_urls.id') ?? collect();
                if ($existingIds->isNotEmpty()) {
                    $query->whereNotIn('id', $existingIds);
                }
            }

            if ($this->urlSearch) {
                $query->where('url', 'like', "%{$this->urlSearch}%");
            }

            $availableUrls = $query->orderBy('domain')->orderBy('path')->limit(50)->get();
        }

        return view('seo::livewire.seo-url-list-manager', [
            'lists' => $lists,
            'activeList' => $activeList,
            'listUrls' => $listUrls,
            'availableUrls' => $availableUrls,
            'aggregated' => $aggregated,
        ])->layout('platform::layouts.app');
    }
}
