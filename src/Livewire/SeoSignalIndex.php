<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoSignalService;

class SeoSignalIndex extends Component
{
    use WithPagination;
    use ResolvesTeamSettings;

    public SeoUrlList $seoUrlList;

    public string $filterStatus = 'new';
    public ?string $filterType = null;
    public ?string $filterSeverity = null;

    public function mount(SeoUrlList $seoUrlList)
    {
        $this->resolveSettings();
        $this->seoUrlList = $seoUrlList;
    }

    public function setFilterStatus(string $status)
    {
        $this->filterStatus = $status;
        $this->resetPage();
    }

    public function acknowledge(int $signalId)
    {
        $signal = SeoSignal::findOrFail($signalId);
        app(SeoSignalService::class)->acknowledge($signal);
    }

    public function resolve(int $signalId)
    {
        $signal = SeoSignal::findOrFail($signalId);
        app(SeoSignalService::class)->resolve($signal);
    }

    public function render()
    {
        $listUrlIds = $this->getListUrlIds();

        $query = SeoSignal::where('team_id', $this->seoSettings->team_id)
            ->whereIn('url_id', $listUrlIds)
            ->with(['keyword', 'url'])
            ->orderByDesc('detected_at');

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }

        if ($this->filterType) {
            $query->where('signal_type', $this->filterType);
        }

        if ($this->filterSeverity) {
            $query->where('severity', $this->filterSeverity);
        }

        $signals = $query->paginate(25);

        $statusCounts = SeoSignal::where('team_id', $this->seoSettings->team_id)
            ->whereIn('url_id', $listUrlIds)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('seo::livewire.seo-signal-index', [
            'signals' => $signals,
            'statusCounts' => $statusCounts,
        ])->layout('platform::layouts.app');
    }

    private function getListUrlIds(): array
    {
        $rootIds = $this->seoUrlList->urls()->pluck('seo_urls.id');
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $rootIds)
            ->pluck('target_url_id');

        return $rootIds->merge($childIds)->all();
    }
}
