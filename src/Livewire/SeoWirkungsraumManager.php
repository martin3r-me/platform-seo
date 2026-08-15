<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoWirkungsraum;

/**
 * Wirkungsraum-Index — Steuer-Scopes des Teams (Gegenstück zur Listen-Übersicht,
 * aber: Steuern statt Beobachten). Inline anlegen; Detail steuert die Mitglieder.
 */
class SeoWirkungsraumManager extends Component
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

        $base = \Illuminate\Support\Str::slug($name) ?: 'wirkungsraum';
        $slug = $base;
        $i = 1;
        while (SeoWirkungsraum::where('team_id', $this->seoSettings->team_id)->where('slug', $slug)->exists()) {
            $slug = $base . '-' . (++$i);
        }

        $wr = SeoWirkungsraum::create([
            'team_id' => $this->seoSettings->team_id,
            'user_id' => auth()->id(),
            'name' => $name,
            'slug' => $slug,
            'goal' => trim($this->newGoal) ?: null,
        ]);

        $this->reset('newName', 'newGoal', 'showCreate');
        $this->redirectRoute('seo.wirkungsraeume.show', $wr, navigate: true);
    }

    public function render()
    {
        $items = SeoWirkungsraum::where('team_id', $this->seoSettings->team_id)
            ->withCount('urls', 'children')
            ->with(['urls:id,visibility_score'])
            ->orderBy('name')
            ->get()
            ->map(function ($wr) {
                $wr->agg_visibility = (float) $wr->urls->sum('visibility_score');

                return $wr;
            });

        return view('seo::livewire.seo-wirkungsraum-manager', [
            'items' => $items,
        ])->layout('platform::layouts.app');
    }
}
