<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoPortfolioMeasure;
use Platform\Seo\Services\SeoPortfolioHealth;
use Platform\Seo\Services\SeoPortfolioOrchestrator;

/**
 * Posteingang eines Wirkungsraums als EIGENE Route — der erste Routen-Schnitt der
 * Stufe-2-Umstellung (Station aus der Gott-Komponente herausgelöst). Triagiert die
 * Maßnahmen genau dieses Wirkungsraums: Vorschläge holen (Orchestrator) → annehmen
 * (Queue) / begründet ablehnen (bleibt als Kontext).
 */
class SeoPortfolioInbox extends Component
{
    use ResolvesTeamSettings;

    public SeoPortfolio $portfolio;

    public ?string $measureFlash = null;

    public function mount(SeoPortfolio $seoPortfolio): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoPortfolio->team_id === (int) $this->seoSettings->team_id, 404);
        $this->portfolio = $seoPortfolio;
    }

    /** Vorschläge holen: der Orchestrator rechnet Nachfrage/Merge/Signale+KI neu. */
    public function generateMeasures(): void
    {
        $res = app(SeoPortfolioOrchestrator::class)->refresh($this->portfolio);
        $n = (int) ($res['measures'] ?? 0);
        $this->measureFlash = $n === 0
            ? 'Keine neuen Maßnahmen — alles bereits im Posteingang oder entschieden.'
            : $n.' neue '.($n === 1 ? 'Maßnahme' : 'Maßnahmen').' im Posteingang (Signale + KI).';
    }

    /** Maßnahme annehmen → Prioritäts-Queue (wartet aufs Tages-Ventil / Flynk). */
    public function acceptMeasure(int $id): void
    {
        $m = SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->find($id);
        if ($m && $m->status === SeoPortfolioMeasure::STATUS_PROPOSED) {
            $m->update(['status' => SeoPortfolioMeasure::STATUS_ACCEPTED, 'decided_at' => now()]);
        }
    }

    /** Maßnahme begründet ablehnen → bleibt als Wirkungsraum-Kontext erhalten. */
    public function rejectMeasure(int $id, string $reason = ''): void
    {
        $m = SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)->find($id);
        if ($m && $m->status === SeoPortfolioMeasure::STATUS_PROPOSED) {
            $m->update([
                'status' => SeoPortfolioMeasure::STATUS_REJECTED,
                'reject_reason' => trim($reason) ?: null,
                'decided_at' => now(),
            ]);
        }
    }

    public function render()
    {
        $measures = SeoPortfolioMeasure::where('portfolio_id', $this->portfolio->id)
            ->with(['targetUrl', 'targetCluster'])
            ->orderByRaw("FIELD(status,'proposed','accepted','released','done','rejected')")
            ->orderByDesc('score')->orderByDesc('created_at')->get();

        return view('seo::livewire.seo-portfolio-inbox', [
            'portfolio' => $this->portfolio,
            'measures' => $measures,
            'health' => app(SeoPortfolioHealth::class)->evaluate($this->portfolio),
        ])->layout('platform::layouts.app');
    }
}
