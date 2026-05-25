<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Str;
use Livewire\Component;
use Platform\Seo\Models\SeoSignal;
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
            $list->agg_own_count = $allUrls->where('is_own', true)->count();
            $list->agg_competitor_count = $allUrls->where('is_own', false)->count();
            $list->agg_last_crawled = $allUrls->max('last_crawled_at');

            // Top 3 keywords by search volume across all URLs in this list
            $list->top_keywords = $allIds->isNotEmpty()
                ? \DB::table('seo_keyword_url')
                    ->join('seo_keywords', 'seo_keywords.id', '=', 'seo_keyword_url.seo_keyword_id')
                    ->whereIn('seo_keyword_url.seo_url_id', $allIds)
                    ->select('seo_keywords.keyword', 'seo_keywords.search_volume', 'seo_keyword_url.position')
                    ->orderByDesc('seo_keywords.search_volume')
                    ->limit(3)
                    ->get()
                : collect();

            // Signal counts by severity for URLs in this list
            $list->agg_signals_critical = $allIds->isNotEmpty()
                ? SeoSignal::whereIn('url_id', $allIds)->where('status', 'new')->where('severity', 'critical')->count()
                : 0;
            $list->agg_signals_warning = $allIds->isNotEmpty()
                ? SeoSignal::whereIn('url_id', $allIds)->where('status', 'new')->where('severity', 'warning')->count()
                : 0;
            $list->agg_signals_opportunity = $allIds->isNotEmpty()
                ? SeoSignal::whereIn('url_id', $allIds)->where('status', 'new')->where('severity', 'opportunity')->count()
                : 0;

            // Status breakdown
            $list->agg_active = $allUrls->where('status', 'active')->count();
            $list->agg_errors = $allUrls->whereIn('status', ['error', 'redirect'])->count();
        }

        return view('seo::livewire.seo-url-list-manager', [
            'lists' => $lists,
        ])->layout('platform::layouts.app');
    }
}
