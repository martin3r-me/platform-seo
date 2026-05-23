<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Services\SeoSignalService;

class SeoSignalIndex extends Component
{
    use WithPagination;
    use ResolvesTeamSettings;

    public string $filterStatus = 'new';
    public ?string $filterType = null;
    public ?string $filterSeverity = null;

    public function mount()
    {
        $this->resolveSettings();
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
        $teamId = $this->seoSettings->team_id;

        $query = SeoSignal::where('team_id', $teamId)
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

        $statusCounts = SeoSignal::where('team_id', $teamId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('seo::livewire.seo-signal-index', [
            'signals' => $signals,
            'statusCounts' => $statusCounts,
        ])->layout('platform::layouts.app');
    }
}
