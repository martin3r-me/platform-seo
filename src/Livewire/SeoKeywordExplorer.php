<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoKeywordService;

class SeoKeywordExplorer extends Component
{
    use WithPagination;
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

    public ?int $expandedKeywordId = null;

    public function mount(SeoUrl $seoUrl)
    {
        $this->resolveSettings();
        $this->seoUrl = $seoUrl;
    }

    public function updatedSearch()
    {
        $this->resetPage();
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

    public function toggleExpand(int $keywordId)
    {
        $this->expandedKeywordId = $this->expandedKeywordId === $keywordId ? null : $keywordId;
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
    public function expandedUrls()
    {
        if (! $this->expandedKeywordId) {
            return collect();
        }

        $allUrlIds = $this->getAllUrlIds();

        return SeoUrl::whereIn('id', $allUrlIds)
            ->whereHas('keywords', fn ($q) => $q->where('seo_keywords.id', $this->expandedKeywordId))
            ->with(['keywords' => fn ($q) => $q->where('seo_keywords.id', $this->expandedKeywordId)])
            ->get();
    }

    public function render()
    {
        $allUrlIds = $this->getAllUrlIds();

        $query = SeoKeyword::where('team_id', $this->seoSettings->team_id)
            ->whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $allUrlIds))
            ->with('cluster')
            ->withCount('urls');

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

        $query->orderBy($this->sortField, $this->sortDirection);

        $keywords = $query->paginate(50);

        return view('seo::livewire.seo-keyword-explorer', [
            'keywords' => $keywords,
        ])->layout('platform::layouts.app');
    }
}
