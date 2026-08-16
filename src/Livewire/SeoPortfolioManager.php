<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoPortfolio;

/**
 * Wirkungsraum-Index — Steuer-Scopes des Teams (Gegenstück zur Listen-Übersicht,
 * aber: Steuern statt Beobachten). Inline anlegen; Detail steuert die Mitglieder.
 */
class SeoPortfolioManager extends Component
{
    use ResolvesTeamSettings;

    public bool $showCreate = false;
    public string $newName = '';
    public string $newGoal = '';

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function create(): void
    {
        $name = trim($this->newName);
        if ($name === '') {
            return;
        }

        $base = \Illuminate\Support\Str::slug($name) ?: 'portfolio';
        $slug = $base;
        $i = 1;
        while (SeoPortfolio::where('team_id', $this->seoSettings->team_id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        $wr = SeoPortfolio::create([
            'team_id' => $this->seoSettings->team_id,
            'user_id' => auth()->id(),
            'name' => $name,
            'slug' => $slug,
            'goal' => trim($this->newGoal) ?: null,
        ]);

        $this->reset('newName', 'newGoal', 'showCreate');
        $this->redirectRoute('seo.portfolios.show', $wr, navigate: true);
    }

    public function render()
    {
        $items = SeoPortfolio::where('team_id', $this->seoSettings->team_id)
            ->withCount('urls', 'children')
            ->with(['urls:id,visibility_score'])
            ->orderBy('name')
            ->get()
            ->map(function ($wr) {
                $wr->agg_visibility = (float) $wr->urls->sum('visibility_score');

                return $wr;
            });

        return view('seo::livewire.seo-portfolio-manager', [
            'items' => $items,
        ])->layout('platform::layouts.app');
    }
}
