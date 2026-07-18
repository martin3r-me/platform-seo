<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Services\SeoRecommendationService;
use Platform\Seo\Services\SeoSignalService;

/**
 * Team-weite Empfehlungs-Inbox — macht die Handlungsempfehlungen der Engine (P4)
 * sichtbar. Baut auf der bestehenden Signal-Infrastruktur auf (Empfehlungen sind
 * rec_*-Signale); wiederverwendet SeoSignalService für acknowledge/resolve.
 */
class SeoRecommendations extends Component
{
    use ResolvesTeamSettings;

    public string $filterStatus = 'open';   // open | resolved | all
    public ?string $filterType = null;
    public int $limit = 25;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function setFilterStatus(string $status): void
    {
        $this->filterStatus = $status;
        $this->limit = 25;
    }

    public function updatedFilterType(): void
    {
        $this->limit = 25;
    }

    public function loadMore(): void
    {
        $this->limit += 25;
    }

    public function acknowledge(int $signalId): void
    {
        app(SeoSignalService::class)->acknowledge(SeoSignal::findOrFail($signalId));
    }

    public function resolve(int $signalId): void
    {
        app(SeoSignalService::class)->resolve(SeoSignal::findOrFail($signalId));
    }

    /** Anzeige-Metadaten je Empfehlungstyp (Label + Icon). */
    public function typeMeta(): array
    {
        return [
            SeoRecommendationService::EXPAND_URL => ['label' => 'URL ausbauen', 'icon' => 'heroicon-o-arrow-trending-up'],
            SeoRecommendationService::BUILD_BACKLINKS => ['label' => 'Backlinks aufbauen', 'icon' => 'heroicon-o-link'],
            SeoRecommendationService::RETIRE_URL => ['label' => 'URL abstellen', 'icon' => 'heroicon-o-archive-box-x-mark'],
            SeoRecommendationService::CREATE_URL => ['label' => 'Neue URL', 'icon' => 'heroicon-o-document-plus'],
            SeoRecommendationService::QUICK_WIN => ['label' => 'Quick Win', 'icon' => 'heroicon-o-bolt'],
        ];
    }

    public function render()
    {
        $teamId = $this->seoSettings->team_id;

        $base = SeoSignal::where('team_id', $teamId)->where('signal_type', 'like', 'rec\_%');

        $query = (clone $base)->with('url:id,url,path')->orderByDesc('detected_at');

        if ($this->filterStatus === 'open') {
            $query->whereIn('status', ['new', 'acknowledged']);
        } elseif ($this->filterStatus === 'resolved') {
            $query->where('status', 'resolved');
        }

        if ($this->filterType) {
            $query->where('signal_type', $this->filterType);
        }

        $all = $query->take($this->limit + 1)->get();
        $hasMore = $all->count() > $this->limit;
        $signals = $all->take($this->limit);

        $statusCounts = [
            'open' => (clone $base)->whereIn('status', ['new', 'acknowledged'])->count(),
            'resolved' => (clone $base)->where('status', 'resolved')->count(),
            'all' => (clone $base)->count(),
        ];

        $typeCounts = (clone $base)
            ->whereIn('status', ['new', 'acknowledged'])
            ->selectRaw('signal_type, count(*) as count')
            ->groupBy('signal_type')
            ->pluck('count', 'signal_type')
            ->toArray();

        return view('seo::livewire.seo-recommendations', [
            'signals' => $signals,
            'statusCounts' => $statusCounts,
            'typeCounts' => $typeCounts,
            'typeMeta' => $this->typeMeta(),
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
