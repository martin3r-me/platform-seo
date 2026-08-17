<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Services\SeoClusterGraphBuilder;

/**
 * 3D-Kosmos eines Wirkungsraums — die Themen als Sterne (Three.js +
 * 3d-force-graph, dieselbe Engine wie die Org-Mindmap). Größe = Potenzial,
 * Leuchten/Ring = Wirkungsgrad, Farbe = Land-Typ, Kanten = Bedeutungsnähe.
 * V1: erkunden + Klick → Thema/Keywords. Übernehmen bleibt (noch) im Wirkungsraum.
 */
class SeoKosmos extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        $this->portfolio = $seoPortfolio;
    }

    public function render()
    {
        return view('seo::livewire.seo-kosmos', [
            'graph' => app(SeoClusterGraphBuilder::class)->build($this->portfolio),
        ])->layout('platform::layouts.app');
    }
}
