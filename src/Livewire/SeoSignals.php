<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoSignalDefinition;
use Platform\Seo\Services\SeoSignalService;

/**
 * Portfolio-weite Signal-Übersicht — Signale erstklassig, quer über alle Kunden.
 * Definition-getriebene zuerst. Lifecycle-Aktionen (quittieren/erledigen) inline.
 */
class SeoSignals extends Component
{
    use ResolvesTeamSettings;

    public string $filterStatus = 'new';
    public ?string $filterSeverity = null;
    public ?string $filterPattern = null;
    public int $limit = 30;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function setStatus(string $status): void
    {
        $this->filterStatus = $status;
        $this->limit = 30;
    }

    public function updatedFilterSeverity(): void
    {
        $this->limit = 30;
    }

    public function updatedFilterPattern(): void
    {
        $this->limit = 30;
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    public function acknowledge(int $id): void
    {
        $signal = SeoSignal::where('team_id', (int) $this->seoSettings->team_id)->findOrFail($id);
        app(SeoSignalService::class)->acknowledge($signal);
    }

    public function resolve(int $id): void
    {
        $signal = SeoSignal::where('team_id', (int) $this->seoSettings->team_id)->findOrFail($id);
        app(SeoSignalService::class)->resolve($signal);
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $query = SeoSignal::where('team_id', $teamId)
            ->with(['url', 'keyword', 'definition']);

        if ($this->filterStatus) {
            $query->where('status', $this->filterStatus);
        }
        if ($this->filterSeverity) {
            $query->where('severity', $this->filterSeverity);
        }
        if ($this->filterPattern) {
            $query->where('signal_type', $this->filterPattern);
        }

        // Definition-getriebene zuerst (NULLs zuletzt via DESC), dann neueste.
        $all = $query->orderByDesc('signal_definition_id')
            ->orderByDesc('detected_at')
            ->take($this->limit + 1)
            ->get();

        $hasMore = $all->count() > $this->limit;
        $signals = $all->take($this->limit);

        $statusCounts = SeoSignal::where('team_id', $teamId)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        return view('seo::livewire.seo-signals', [
            'signals' => $signals,
            'statusCounts' => $statusCounts,
            'hasMore' => $hasMore,
            'catalog' => SeoSignalDefinition::patternCatalog(),
        ])->layout('platform::layouts.app');
    }
}
