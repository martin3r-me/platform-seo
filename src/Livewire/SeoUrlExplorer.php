<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoUrlService;

class SeoUrlExplorer extends Component
{
    use WithPagination;
    use ResolvesTeamSettings;

    public string $search = '';
    public ?string $filterIsOwn = null;
    public ?string $filterStatus = null;
    public string $sortField = 'visibility_score';
    public string $sortDirection = 'desc';

    public bool $showAddModal = false;
    public string $newUrls = '';
    public bool $newUrlsIsOwn = true;

    public array $selectedUrls = [];
    public bool $selectAll = false;

    public function mount()
    {
        $this->resolveSettings();
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
        $query = SeoUrl::where('team_id', $this->seoSettings->team_id)->with('onPage');

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

        $urls = $query->paginate(50);

        return view('seo::livewire.seo-url-explorer', [
            'urls' => $urls,
        ])->layout('platform::layouts.app');
    }
}
