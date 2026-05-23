<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoKeywordService;

class SeoKeywordExplorer extends Component
{
    use WithPagination;

    public SeoProject $seoProject;

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

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
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
        $keywordService->addKeywords($this->seoProject, $data, Auth::user());

        $this->newKeywords = '';
        $this->showAddModal = false;
    }

    public function deleteSelected()
    {
        if (empty($this->selectedKeywords)) {
            return;
        }

        $this->seoProject->keywords()->detach($this->selectedKeywords);

        $this->selectedKeywords = [];
        $this->selectAll = false;
    }

    public function fetchMetrics()
    {
        $keywordService = app(SeoKeywordService::class);
        $result = $keywordService->fetchMetrics($this->seoProject->team_id, $this->seoProject->id, Auth::user());

        if (isset($result['error'])) {
            session()->flash('error', $result['error']);
        } else {
            session()->flash('success', "{$result['fetched']} Keywords aktualisiert.");
        }
    }

    #[Computed]
    public function clusters()
    {
        return $this->seoProject->clusters()->get();
    }

    #[Computed]
    public function topics()
    {
        return $this->seoProject->keywords()
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

        return SeoUrl::where('project_id', $this->seoProject->id)
            ->whereHas('keywords', fn ($q) => $q->where('seo_keywords.id', $this->expandedKeywordId))
            ->with(['keywords' => fn ($q) => $q->where('seo_keywords.id', $this->expandedKeywordId)])
            ->get();
    }

    public function render()
    {
        $query = $this->seoProject->keywords()
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

        $pivotFields = ['position', 'content_status', 'priority'];
        if (in_array($this->sortField, $pivotFields)) {
            $query->orderByPivot($this->sortField, $this->sortDirection);
        } else {
            $query->orderBy($this->sortField, $this->sortDirection);
        }

        $keywords = $query->paginate(50);

        return view('seo::livewire.seo-keyword-explorer', [
            'keywords' => $keywords,
        ])->layout('platform::layouts.app');
    }
}
