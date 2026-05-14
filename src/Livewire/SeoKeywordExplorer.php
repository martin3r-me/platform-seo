<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoKeywordService;

class SeoKeywordExplorer extends Component
{
    use WithPagination;

    public SeoProject $seoProject;

    public string $search = '';
    public ?string $filterIntent = null;
    public ?int $filterCluster = null;
    public string $sortField = 'search_volume';
    public string $sortDirection = 'desc';

    public bool $showAddModal = false;
    public string $newKeywords = '';

    public array $selectedKeywords = [];
    public bool $selectAll = false;

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

    public function addKeywords()
    {
        $lines = array_filter(array_map('trim', explode("\n", $this->newKeywords)));

        if (empty($lines)) {
            return;
        }

        $keywordService = app(SeoKeywordService::class);
        $data = array_map(fn($line) => ['keyword' => strtolower($line)], $lines);
        $keywordService->addKeywords($this->seoProject, $data, Auth::user());

        $this->newKeywords = '';
        $this->showAddModal = false;
    }

    public function deleteSelected()
    {
        if (empty($this->selectedKeywords)) {
            return;
        }

        SeoKeyword::where('project_id', $this->seoProject->id)
            ->whereIn('id', $this->selectedKeywords)
            ->delete();

        $this->selectedKeywords = [];
        $this->selectAll = false;
    }

    public function fetchMetrics()
    {
        $keywordService = app(SeoKeywordService::class);
        $result = $keywordService->fetchMetrics($this->seoProject, null, Auth::user());

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

    public function render()
    {
        $query = $this->seoProject->keywords()
            ->with('cluster');

        if ($this->search) {
            $query->where('keyword', 'like', "%{$this->search}%");
        }

        if ($this->filterIntent) {
            $query->where('search_intent', $this->filterIntent);
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
