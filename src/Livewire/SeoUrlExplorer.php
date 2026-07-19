<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoOrganizationLinker;
use Platform\Seo\Services\SeoUrlService;

class SeoUrlExplorer extends Component
{
    use ResolvesTeamSettings;

    public string $search = '';
    public ?string $filterIsOwn = null;
    public ?string $filterStatus = null;
    public string $sortField = 'visibility_score';
    public string $sortDirection = 'desc';

    public bool $groupByContext = false;

    public bool $showAddModal = false;
    public string $newUrls = '';
    public bool $newUrlsIsOwn = true;

    public array $selectedUrls = [];
    public bool $selectAll = false;

    public int $limit = 50;

    public function mount()
    {
        $this->resolveSettings();
    }

    public function updatedSearch()
    {
        $this->limit = 50;
    }

    public function updatedFilterIsOwn()
    {
        $this->limit = 50;
    }

    public function updatedFilterStatus()
    {
        $this->limit = 50;
    }

    public function loadMore(): void
    {
        $this->limit += 50;
    }

    public function sortBy(string $field)
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'desc';
        }
    }

    public function addUrls()
    {
        $lines = array_filter(array_map('trim', explode("\n", $this->newUrls)));
        if (empty($lines)) {
            return;
        }

        $urlService = app(SeoUrlService::class);
        $teamId = $this->seoSettings->team_id;
        foreach ($lines as $line) {
            $urlService->register($teamId, $line, [
                'is_own' => $this->newUrlsIsOwn,
                'reason' => 'manual',
            ]);
        }

        $this->newUrls = '';
        $this->showAddModal = false;
    }

    public function enrichSelected()
    {
        if (empty($this->selectedUrls)) {
            return;
        }

        $urlService = app(SeoUrlService::class);
        $teamId = $this->seoSettings->team_id;
        foreach ($this->selectedUrls as $urlId) {
            $url = SeoUrl::find($urlId);
            if ($url) {
                $urlService->enrich($teamId, $url->url);
            }
        }

        $this->selectedUrls = [];
        $this->selectAll = false;
    }

    public function deleteSelected()
    {
        if (empty($this->selectedUrls)) {
            return;
        }

        SeoUrl::whereIn('id', $this->selectedUrls)
            ->where('team_id', $this->seoSettings->team_id)
            ->delete();

        $this->selectedUrls = [];
        $this->selectAll = false;
    }

    public function render()
    {
        $teamId = $this->seoSettings->team_id;
        $query = SeoUrl::where('team_id', $teamId)->with('onPage');

        // Root-only: exclude child URLs
        $childIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id');
        if ($childIds->isNotEmpty()) {
            $query->whereNotIn('id', $childIds);
        }

        if ($this->search) {
            $query->where('url', 'like', "%{$this->search}%");
        }
        if ($this->filterIsOwn !== null) {
            $query->where('is_own', $this->filterIsOwn === '1');
        }
        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        $query->orderBy($this->sortField, $this->sortDirection);

        $allUrls = $query->take($this->limit + 1)->get();
        $hasMore = $allUrls->count() > $this->limit;
        $urls = $allUrls->take($this->limit);

        // Aggregate children metrics (bulk)
        $urlIds = $urls->pluck('id');
        $childRelations = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $urlIds)
            ->get()
            ->groupBy('source_url_id');

        $allChildIds = $childRelations->flatMap(fn ($rels) => $rels->pluck('target_url_id'));
        $childUrls = $allChildIds->isNotEmpty()
            ? SeoUrl::whereIn('id', $allChildIds)->get()->keyBy('id')
            : collect();

        $urls->transform(function (SeoUrl $url) use ($childRelations, $childUrls) {
            $children = collect();
            if (isset($childRelations[$url->id])) {
                $children = $childRelations[$url->id]->map(fn ($rel) => $childUrls->get($rel->target_url_id))->filter();
            }

            $url->child_count = $children->count();
            $url->agg_keyword_count = $url->keyword_count + $children->sum('keyword_count');
            $url->agg_search_volume = $url->total_search_volume + $children->sum('total_search_volume');
            $url->agg_visibility = (float) $url->visibility_score + (float) $children->sum('visibility_score');
            $url->agg_backlinks = $url->backlink_count + $children->sum('backlink_count');

            return $url;
        });

        // Gruppieren nach Kontext (U5): die Baum-Ordnung sichtbar statt flach.
        // Jede URL landet unter ihrem/ihren Org-Knoten, der Rest unter „Ohne Kontext".
        $grouped = null;
        if ($this->groupByContext && $urls->isNotEmpty()) {
            $nodesByUrl = app(SeoOrganizationLinker::class)
                ->nodesForMany(SeoOrganizationLinker::ALIAS_URL, $urls->pluck('id')->all());

            $buckets = [];
            foreach ($urls as $url) {
                $nodes = $nodesByUrl[$url->id] ?? [];
                if (empty($nodes)) {
                    $buckets['__none__'] ??= ['label' => 'Ohne Kontext', 'entityId' => null, 'urls' => collect()];
                    $buckets['__none__']['urls']->push($url);
                    continue;
                }
                foreach ($nodes as $n) {
                    $key = 'e'.$n['id'];
                    $buckets[$key] ??= ['label' => $n['name'] ?: ('Knoten #'.$n['id']), 'entityId' => (int) $n['id'], 'urls' => collect()];
                    $buckets[$key]['urls']->push($url);
                }
            }

            $named = collect($buckets)->filter(fn ($b) => $b['entityId'] !== null)
                ->sortByDesc(fn ($b) => $b['urls']->count())->values();
            $none = collect($buckets)->filter(fn ($b) => $b['entityId'] === null)->values();
            $grouped = $named->concat($none)->all();
        }

        return view('seo::livewire.seo-url-explorer', [
            'urls' => $urls,
            'grouped' => $grouped,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
