<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoContentBrief;

/**
 * Content-Brief-Detail (U3) — der Produktions-Plan für ein Stück Content.
 *
 * Zeigt Meta (Typ, Intent, Ziel-URL, Umfang), das verknüpfte Cluster, die
 * Abschnitte (H2 mit Beschreibung + Ziel-Keywords) und die Notizen
 * (Instruktion/Referenz/Keywords) — der Übergang von der Chance zur Umsetzung.
 */
class SeoContentBriefDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoContentBrief $brief;

    public function mount(SeoContentBrief $brief): void
    {
        $this->resolveSettings();

        // Team-Guard: nur eigene Briefs.
        abort_unless((int) $brief->team_id === (int) $this->seoSettings->team_id, 404);

        $this->brief = $brief;
    }

    public function render()
    {
        $this->brief->load([
            'sections' => fn ($q) => $q->orderBy('order'),
            'notes',
            'clusters',
        ]);

        return view('seo::livewire.seo-content-brief-detail', [
            'brief' => $this->brief,
            'sections' => $this->brief->sections,
            'notes' => $this->brief->notes,
            'clusters' => $this->brief->clusters,
        ])->layout('platform::layouts.app');
    }
}
