<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoWirkungsraum;

/**
 * Wirkungsraum-Detail — der Arbeitsraum (Slice 2: Listen-Niveau + Mitglieder-
 * Management). Aggregat-KPIs, Mitglieder-Tabelle, URLs zufügen/lösen. Steuer-
 * Invariante: nur eigene (kontrollierte) URLs. Steuer-Facetten (Durchdringung,
 * ungeclusterter Rest, Wettbewerber, KI) folgen in Slice 3/4.
 */
class SeoWirkungsraumDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoWirkungsraum $wirkungsraum;

    public bool $showAddUrls = false;
    public string $urlSearch = '';
    public array $selectedUrlIds = [];

    public function mount(SeoWirkungsraum $seoWirkungsraum): void
    {
        $this->resolveSettings();
        abort_unless((int) $seoWirkungsraum->team_id === (int) $this->seoSettings->team_id, 404);
        $this->wirkungsraum = $seoWirkungsraum;
    }

    public function openAddUrls(): void
    {
        $this->urlSearch = '';
        $this->selectedUrlIds = [];
        $this->showAddUrls = true;
    }

    public function addUrls(): void
    {
        if (empty($this->selectedUrlIds)) {
            return;
        }

        // Steuer-Invariante: nur eigene URLs des Teams.
        $ownIds = SeoUrl::where('team_id', $this->seoSettings->team_id)
            ->where('is_own', true)
            ->whereIn('id', $this->selectedUrlIds)
            ->pluck('id');

        $this->wirkungsraum->urls()->syncWithoutDetaching(
            $ownIds->mapWithKeys(fn ($id) => [$id => ['added_at' => now()]])->all()
        );

        $this->reset('showAddUrls', 'selectedUrlIds', 'urlSearch');
    }

    public function removeUrl(int $urlId): void
    {
        $this->wirkungsraum->urls()->detach($urlId);
    }

    public function render()
    {
        $members = $this->wirkungsraum->urls()
            ->orderByDesc('visibility_score')
            ->get();

        $agg = [
            'visibility' => (float) $members->sum('visibility_score'),
            'keywords' => (int) $members->sum('keyword_count'),
            'search_volume' => (int) $members->sum('total_search_volume'),
            'urls' => $members->count(),
        ];

        // Add-Modal: nur EIGENE, noch nicht zugeordnete URLs.
        $availableUrls = collect();
        if ($this->showAddUrls) {
            $existing = $this->wirkungsraum->urls()->pluck('seo_urls.id');
            $q = SeoUrl::where('team_id', $this->seoSettings->team_id)
                ->where('is_own', true)
                ->whereNotIn('id', $existing);
            if ($this->urlSearch !== '') {
                $q->where('url', 'like', "%{$this->urlSearch}%");
            }
            $availableUrls = $q->orderBy('domain')->orderBy('path')->limit(50)->get();
        }

        return view('seo::livewire.seo-wirkungsraum-detail', [
            'members' => $members,
            'agg' => $agg,
            'availableUrls' => $availableUrls,
        ])->layout('platform::layouts.app');
    }
}
