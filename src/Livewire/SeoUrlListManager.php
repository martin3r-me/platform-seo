<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;

class SeoUrlListManager extends Component
{
    // Create/Edit modal
    public bool $showListModal = false;
    public ?int $editingListId = null;
    public string $listName = '';
    public string $listDescription = '';

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
            SeoUrlList::create([
                'name' => $this->listName,
                'slug' => Str::slug($this->listName),
                'description' => $this->listDescription ?: null,
                'created_by' => auth()->id(),
            ]);
        }

        $this->showListModal = false;
        $this->listName = '';
        $this->listDescription = '';
        $this->editingListId = null;
    }

    public function deleteList(int $id): void
    {
        SeoUrlList::findOrFail($id)->delete();
    }

    // -------------------------------------------------------------------------
    // Render
    // -------------------------------------------------------------------------

    public function render()
    {
        $lists = SeoUrlList::withCount('urls')->orderBy('name')->get();

        // Compute aggregate stats per list
        foreach ($lists as $list) {
            $rootUrlIds = $list->urls()->pluck('seo_urls.id');
            $childIds = SeoUrlRelationship::where('type', 'parent_child')
                ->whereIn('source_url_id', $rootUrlIds)
                ->pluck('target_url_id');

            $allIds = $rootUrlIds->merge($childIds);
            $allUrls = $allIds->isNotEmpty()
                ? SeoUrl::whereIn('id', $allIds)->get()
                : collect();

            $list->agg_keywords = $allUrls->sum('keyword_count');
            $list->agg_search_volume = $allUrls->sum('total_search_volume');
            $list->agg_visibility = $allUrls->sum('visibility_score');
            $list->agg_backlinks = $allUrls->sum('backlink_count');
        }

        return view('seo::livewire.seo-url-list-manager', [
            'lists' => $lists,
        ])->layout('platform::layouts.app');
    }
}
