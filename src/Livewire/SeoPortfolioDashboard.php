<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoPortfolioMeasure;
use Platform\Seo\Services\SeoPortfolioHealth;
use Platform\Seo\Services\SeoPortfolioView;
use Platform\Seo\Services\SeoScopeMetrics;

/**
 * Station „Dashboard" (Überblick) als eigene Route/Komponente — die gecraftete
 * NX-Überblickssicht (Held-Metrik + Wirkungsgrad + nächster Zug + Reifegrad +
 * Sparkline). Ist zugleich die Hauptseite eines Wirkungsraums (seo.portfolios.show).
 * Herausgelöst aus der Gott-Komponente; Grunddaten via SeoPortfolioView/Health/Scope.
 */
class SeoPortfolioDashboard extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
    }

    public function render()
    {
        $view = app(SeoPortfolioView::class);
        $pv = $view->forPortfolio($this->portfolio);
        $scope = app(SeoScopeMetrics::class)->forUrlIds((int) $this->seoSettings->team_id, $pv['effectiveIds']);
        $pid = $this->portfolio->id;

        return view('seo::livewire.seo-portfolio-dashboard', [
            'portfolio' => $this->portfolio,
            'agg' => $pv['agg'],
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
            'trend' => $view->trendForUrlIds($pv['effectiveIds']),
            'penetration' => ['clusters' => $scope['clusters'], 'unclustered' => $scope['unclustered']],
            'competitors' => $scope['competitors'],
            'measureInbox' => [
                'proposed' => SeoPortfolioMeasure::where('portfolio_id', $pid)->where('status', 'proposed')->count(),
                'accepted' => SeoPortfolioMeasure::where('portfolio_id', $pid)->where('status', 'accepted')->count(),
                'top' => SeoPortfolioMeasure::where('portfolio_id', $pid)->where('status', 'proposed')->orderByDesc('score')->first(),
            ],
        ])->layout('platform::layouts.app');
    }
}
