<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Services\SeoPortfolioHealth;
use Platform\Seo\Services\SeoPortfolioView;
use Platform\Seo\Services\SeoScopeMetrics;

/**
 * Station „Meta" (Steckbrief) als eigene Route/Komponente — Ziel/Auftrag des
 * Wirkungsraums, steht vor den Gates. Herausgelöst aus der Gott-Komponente;
 * geteilte Grunddaten via SeoPortfolioView/Health/ScopeMetrics.
 */
class SeoPortfolioMeta extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public bool $metaEditing = false;

    public string $metaGoal = '';

    public string $metaDescription = '';

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
        $this->metaGoal = (string) ($seoPortfolio->goal ?? '');
        $this->metaDescription = (string) ($seoPortfolio->description ?? '');
    }

    public function editMeta(): void
    {
        $this->metaGoal = (string) ($this->portfolio->goal ?? '');
        $this->metaDescription = (string) ($this->portfolio->description ?? '');
        $this->metaEditing = true;
    }

    public function cancelMeta(): void
    {
        $this->metaEditing = false;
    }

    public function saveMeta(): void
    {
        $this->portfolio->update([
            'goal' => trim($this->metaGoal) ?: null,
            'description' => trim($this->metaDescription) ?: null,
        ]);
        $this->portfolio->refresh();
        $this->metaEditing = false;
    }

    public function render()
    {
        $pv = app(SeoPortfolioView::class)->forPortfolio($this->portfolio);
        $scope = app(SeoScopeMetrics::class)->forUrlIds((int) $this->seoSettings->team_id, $pv['effectiveIds']);

        return view('seo::livewire.seo-portfolio-meta', [
            'portfolio' => $this->portfolio,
            'agg' => $pv['agg'],
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
            'penetration' => ['clusters' => $scope['clusters'], 'unclustered' => $scope['unclustered']],
            'metaEditing' => $this->metaEditing,
        ])->layout('platform::layouts.app');
    }
}
