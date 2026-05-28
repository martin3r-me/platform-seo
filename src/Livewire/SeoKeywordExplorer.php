<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoRankingHistory;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoKeywordService;

class SeoKeywordExplorer extends Component
{
    use ResolvesTeamSettings;

    public SeoUrl $seoUrl;

    public string $search = '';
    public ?string $filterIntent = null;
    public ?string $filterTopic = null;
    public ?int $filterCluster = null;
    public string $sortField = 'search_volume';
    public string $sortDirection = 'desc';

    public bool $showAddModal = false;
    public string $newKeywords = '';

    public array $selectedKeywords = [];
    public bool $selectAll = false;

    public ?int $selectedKeywordId = null;

    public int $limit = 50;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function updatedSearch()
    {
        $this->limit = 50;
        $this->selectedKeywordId = null;
    }

    public function updatedFilterIntent()
    {
        $this->limit = 50;
        $this->selectedKeywordId = null;
    }

    public function updatedFilterTopic()
    {
        $this->limit = 50;
        $this->selectedKeywordId = null;
    }

    public function updatedFilterCluster()
    {
        $this->limit = 50;
        $this->selectedKeywordId = null;
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

    public function addKeywords()
    {
        $lines = array_filter(array_map('trim', explode("\n", $this->newKeywords)));

        if (empty($lines)) {
            return;
        }

        $keywordService = app(SeoKeywordService::class);
        $data = array_map(fn ($line) => ['keyword' => strtolower($line)], $lines);
        $keywordService->addKeywords($this->seoSettings->team_id, $data, Auth::user());

        $this->newKeywords = '';
        $this->showAddModal = false;
    }

    public function deleteSelected()
    {
        if (empty($this->selectedKeywords)) {
            return;
        }

        SeoKeyword::where('team_id', $this->seoSettings->team_id)
            ->whereIn('id', $this->selectedKeywords)
            ->delete();

        $this->selectedKeywords = [];
        $this->selectAll = false;
    }

    public function fetchMetrics()
    {
        $keywordService = app(SeoKeywordService::class);
        $result = $keywordService->fetchMetrics($this->seoSettings->team_id, null, Auth::user());

        if (isset($result['error'])) {
            session()->flash('error', $result['error']);
        } else {
            session()->flash('success', "{$result['fetched']} Keywords aktualisiert.");
        }
    }

    private function getAllUrlIds(): array
    {
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->where('source_url_id', $this->seoUrl->id)
            ->pluck('target_url_id');

        return collect([$this->seoUrl->id])->merge($childIds)->all();
    }

    private function buildFilteredQuery()
    {
        $allUrlIds = $this->getAllUrlIds();

        $query = SeoKeyword::where('team_id', $this->seoSettings->team_id)
            ->whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds));

        if ($this->search) {
            $query->where('keyword', 'like', "%{$this->search}%");
        }

        if ($this->filterIntent) {
            $query->where('search_intent', $this->filterIntent);
        }

        if ($this->filterTopic) {
            $query->where('topic', $this->filterTopic);
        }

        if ($this->filterCluster !== null) {
            if ($this->filterCluster === 0) {
                $query->whereNull('cluster_id');
            } else {
                $query->where('cluster_id', $this->filterCluster);
            }
        }

        return $query;
    }

    #[Computed]
    public function clusters()
    {
        return SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->get();
    }

    #[Computed]
    public function topics()
    {
        $allUrlIds = $this->getAllUrlIds();

        return SeoKeyword::where('team_id', $this->seoSettings->team_id)
            ->whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
            ->whereNotNull('topic')
            ->distinct()
            ->pluck('topic');
    }

    #[Computed]
    public function selectedKeyword()
    {
        if (! $this->selectedKeywordId) {
            return null;
        }

        return SeoKeyword::with(['cluster', 'competitors' => fn ($q) => $q->orderBy('position')->limit(10), 'positions' => fn ($q) => $q->latest('tracked_at')->limit(1)])
            ->find($this->selectedKeywordId);
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

    #[Computed]
    public function aggregateStats()
    {
        $query = $this->buildFilteredQuery();

        return $query->selectRaw('
            COUNT(*) as count,
            COALESCE(SUM(search_volume), 0) as total_sv,
            ROUND(COALESCE(AVG(keyword_difficulty), 0), 1) as avg_kd,
            ROUND(COALESCE(AVG(cpc_cents), 0) / 100, 2) as avg_cpc
        ')->first();
    }

    public function render()
    {
        $query = $this->buildFilteredQuery()
            ->with(['cluster', 'competitors'])
            ->withCount('urls');

        $query->orderBy($this->sortField, $this->sortDirection);

        $allKeywords = $query->take($this->limit + 1)->get();
        $hasMore = $allKeywords->count() > $this->limit;
        $keywords = $allKeywords->take($this->limit);

        return view('seo::livewire.seo-keyword-explorer', [
            'keywords' => $keywords,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
