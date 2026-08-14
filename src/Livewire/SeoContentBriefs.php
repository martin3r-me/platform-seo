<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoContentBrief;

/**
 * Content-Brief-Index — die Arbeitsaufträge der Werkbank auf einen Blick.
 *
 * Zeigt alle Briefs mit Status, Typ, Ziel-URL, Abschnitten und verknüpftem
 * Cluster. Von hier führt der Weg in den Brief-Detail (der Produktions-Plan).
 */
class SeoContentBriefs extends Component
{
    use ResolvesTeamSettings;

    public string $status = 'all';
    public int $limit = 50;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->limit = 50;
    }

    public function loadMore(): void
    {
        $this->limit += 50;
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $query = SeoContentBrief::withCount(['sections', 'notes'])
            ->with(['clusters'])
            ->where('team_id', $teamId);

        if ($this->status !== 'all') {
            $query->where('status', $this->status);
        }

        $all = $query->orderByDesc('id')->take($this->limit + 1)->get();
        $hasMore = $all->count() > $this->limit;
        $briefs = $all->take($this->limit);

        // Status-Facetten für die Filterleiste.
        $statusCounts = SeoContentBrief::where('team_id', $teamId)
            ->selectRaw('status, COUNT(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        return view('seo::livewire.seo-content-briefs', [
            'briefs' => $briefs,
            'hasMore' => $hasMore,
            'statusCounts' => $statusCounts,
            'total' => array_sum($statusCounts),
        ])->layout('platform::layouts.app');
    }
}
