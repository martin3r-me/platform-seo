<?php

namespace Platform\Seo\Livewire;

use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlDimension;
use Platform\Seo\Services\SeoBaseClusterBuilder;
use Platform\Seo\Services\SeoPortfolioHealth;

/**
 * Station „Basis" als EIGENE Route/Komponente — Stufe-2-Entflechtung der
 * Gott-Komponente (Muster wie SeoPortfolioInbox). Index aller beteiligten
 * eigenen URLs: SEO-Ziel setzen (UrlSeoTarget-Modal) + Basis-Cluster bauen
 * (SeoBaseClusterBuilder). Aus diesen Basis-Clustern entstehen die Themenfelder.
 */
class SeoPortfolioBasis extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public ?string $clusterFlash = null;

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
    }

    /** Nach dem Speichern eines SEO-Ziels (UrlSeoTarget-Modal) neu rendern. */
    #[On('url-target-saved')]
    public function onUrlTargetSaved(): void
    {
    }

    /** Basis-Cluster einer eigenen WR-URL bauen/frischen (DataForSEO). */
    public function buildBaseClusterFor(int $urlId, SeoBaseClusterBuilder $builder): void
    {
        $url = SeoUrl::whereIn('id', $this->portfolio->effectiveUrlIds())
            ->where('id', $urlId)->where('is_own', true)->first();
        if (! $url) {
            return;
        }
        $res = $builder->build($url);
        $this->clusterFlash = ! empty($res['error'])
            ? $res['error']
            : sprintf('✓ „%s": %d Anker · %d neu, %d aus Bestand · Potenzial %s/Mon.',
                $res['cluster']->name ?? 'Basis-Cluster', $res['anchored'] ?? 0, $res['attached'] ?? 0, $res['swept'] ?? 0,
                number_format($res['potential'] ?? 0, 0, ',', '.'));
    }

    /** Alle eigenen WR-URLs mit SEO-Ziel bauen (der Rutsch). */
    public function buildAllBaseClusters(SeoBaseClusterBuilder $builder): void
    {
        $ids = $this->portfolio->effectiveUrlIds();
        $urls = empty($ids) ? collect() : SeoUrl::whereIn('id', $ids)->where('is_own', true)->get();
        $built = 0;
        $skipped = 0;
        foreach ($urls as $url) {
            if (! SeoUrlDimension::where('url_id', $url->id)->where('dimension', 'basis')->exists()) {
                $skipped++;

                continue;
            }
            if (empty($builder->build($url)['error'])) {
                $built++;
            }
        }
        $this->clusterFlash = "✓ {$built} Basis-Cluster gebaut/gefrischt".($skipped ? " · {$skipped} ohne SEO-Ziel übersprungen" : '').'.';
    }

    public function render()
    {
        $ids = $this->portfolio->effectiveUrlIds();
        $ownUrls = empty($ids) ? collect() : SeoUrl::whereIn('id', $ids)->where('is_own', true)
            ->orderBy('domain')->orderBy('path')->get();
        $urlIds = $ownUrls->pluck('id');
        $dimsAll = $urlIds->isEmpty() ? collect() : SeoUrlDimension::whereIn('url_id', $urlIds)->get()->groupBy('url_id');
        $baseClusters = $urlIds->isEmpty() ? collect() : SeoKeywordCluster::where('team_id', $this->portfolio->team_id)
            ->where('origin', SeoKeywordCluster::ORIGIN_BASE)->whereIn('pillar_url_id', $urlIds)->get()->keyBy('pillar_url_id');

        $basisRows = collect();
        foreach ($ownUrls as $u) {
            $bc = $baseClusters[$u->id] ?? null;
            $dims = ($dimsAll[$u->id] ?? collect())->groupBy('dimension');
            $basisRows->push([
                'url' => $u,
                'dims' => $dims,
                'hasBasis' => $dims->has('basis') && $dims->get('basis')->isNotEmpty(),
                'cluster' => $bc,
                'kw' => (int) ($bc?->keyword_count ?? 0),
                'potential' => $bc ? (int) SeoKeyword::where('cluster_id', $bc->id)->sum('search_volume') : 0,
            ]);
        }
        $basisRows = $basisRows->sortByDesc('hasBasis')->values();

        return view('seo::livewire.seo-portfolio-basis', [
            'portfolio' => $this->portfolio,
            'basisRows' => $basisRows,
            'clusterFlash' => $this->clusterFlash,
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
        ])->layout('platform::layouts.app');
    }
}
