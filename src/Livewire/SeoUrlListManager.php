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

        if ($lists->isEmpty()) {
            return view('seo::livewire.seo-url-list-manager', [
                'lists' => $lists,
            ])->layout('platform::layouts.app');
        }

        // 1. Bulk: all list-URL entries (one query instead of N)
        $listEntries = \DB::table('seo_url_list_entries')
            ->whereIn('list_id', $lists->pluck('id'))
            ->get()
            ->groupBy('list_id');

        $allRootUrlIds = $listEntries->flatMap(fn ($entries) => $entries->pluck('url_id'))->unique();

        // 2. Bulk: all child relationships for all root URLs (one query)
        $childRelations = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $allRootUrlIds)
            ->get()
            ->groupBy('source_url_id');

        $allChildIds = $childRelations->flatMap(fn ($rels) => $rels->pluck('target_url_id'))->unique();
        $allUrlIds = $allRootUrlIds->merge($allChildIds)->unique();

        // 3. Bulk: load all URLs at once (one query)
        $allUrls = $allUrlIds->isNotEmpty()
            ? SeoUrl::whereIn('id', $allUrlIds)->get()->keyBy('id')
            : collect();

        // 4. Bulk: signal counts grouped by url_id and severity (one query)
        $signalCounts = $allUrlIds->isNotEmpty()
            ? SeoSignal::whereIn('url_id', $allUrlIds)
                ->where('status', 'new')
                ->whereIn('severity', ['critical', 'warning', 'opportunity'])
                ->selectRaw('url_id, severity, COUNT(*) as cnt')
                ->groupBy('url_id', 'severity')
                ->get()
            : collect();

        // 5. Bulk: top keywords per URL (one query, will filter per list in PHP)
        $topKeywordsRaw = $allUrlIds->isNotEmpty()
            ? \DB::table('seo_url_keywords')
                ->join('seo_keywords', 'seo_keywords.id', '=', 'seo_url_keywords.keyword_id')
                ->whereIn('seo_url_keywords.url_id', $allUrlIds)
                ->select('seo_url_keywords.url_id', 'seo_keywords.keyword', 'seo_keywords.search_volume', 'seo_url_keywords.position')
                ->orderByDesc('seo_keywords.search_volume')
                ->get()
                ->groupBy('url_id')
            : collect();

        // Compute per-list aggregates from pre-loaded data
        foreach ($lists as $list) {
            $rootIds = isset($listEntries[$list->id])
                ? $listEntries[$list->id]->pluck('url_id')
                : collect();

            $childIds = $rootIds->flatMap(fn ($id) => isset($childRelations[$id])
                ? $childRelations[$id]->pluck('target_url_id')
                : collect()
            );

            $listUrlIds = $rootIds->merge($childIds)->unique();
            $listUrls = $listUrlIds->map(fn ($id) => $allUrls->get($id))->filter();

            $list->agg_keywords = $listUrls->sum('keyword_count');
            $list->agg_search_volume = $listUrls->sum('total_search_volume');
            $list->agg_visibility = $listUrls->sum('visibility_score');
            $list->agg_backlinks = $listUrls->sum('backlink_count');
            $list->agg_own_count = $listUrls->where('is_own', true)->count();
            $list->agg_competitor_count = $listUrls->where('is_own', false)->count();
            $list->agg_last_crawled = $listUrls->max('last_crawled_at');
            $list->agg_active = $listUrls->where('status', 'active')->count();
            $list->agg_errors = $listUrls->filter(fn ($u) => in_array($u->status, ['error', 'redirect']))->count();

            // Signal counts from pre-loaded data
            $listSignals = $signalCounts->whereIn('url_id', $listUrlIds);
            $list->agg_signals_critical = $listSignals->where('severity', 'critical')->sum('cnt');
            $list->agg_signals_warning = $listSignals->where('severity', 'warning')->sum('cnt');
            $list->agg_signals_opportunity = $listSignals->where('severity', 'opportunity')->sum('cnt');

            // Top 3 keywords from pre-loaded data
            $seenKeywords = [];
            $topKws = collect();
            foreach ($listUrlIds as $urlId) {
                if (isset($topKeywordsRaw[$urlId])) {
                    foreach ($topKeywordsRaw[$urlId] as $kw) {
                        if (! isset($seenKeywords[$kw->keyword]) && $topKws->count() < 3) {
                            $seenKeywords[$kw->keyword] = true;
                            $topKws->push($kw);
                        }
                    }
                }
            }
            $list->top_keywords = $topKws->sortByDesc('search_volume')->values()->take(3);
        }

        return view('seo::livewire.seo-url-list-manager', [
            'lists' => $lists,
        ])->layout('platform::layouts.app');
    }
}
