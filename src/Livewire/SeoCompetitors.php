<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * Team-weiter Wettbewerber-Explorer (U4) — raus aus dem List-Silo.
 *
 * Zeigt alle Wettbewerber-Domains (is_own=false) des Teams aggregiert plus die
 * einzelnen Wettbewerber-URLs, optional nach Domain gefiltert.
 */
class SeoCompetitors extends Component
{
    use ResolvesTeamSettings;

    public ?string $filterDomain = null;
    public int $limit = 30;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    public function setDomainFilter(?string $domain): void
    {
        $this->filterDomain = $this->filterDomain === $domain ? null : $domain;
        $this->limit = 30;
    }

    public function loadMore(): void
    {
        $this->limit += 30;
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $domains = SeoUrl::where('team_id', $teamId)
            ->where('is_own', false)
            ->where('status', 'active')
            ->selectRaw('domain, COUNT(*) as url_count, SUM(keyword_count) as keyword_count, SUM(visibility_score) as visibility')
            ->groupBy('domain')
            ->orderByDesc('visibility')
            ->limit(40)
            ->get();

        $urlQuery = SeoUrl::where('team_id', $teamId)
            ->where('is_own', false)
            ->where('status', 'active')
            ->when($this->filterDomain, fn ($q) => $q->where('domain', $this->filterDomain))
            ->orderByDesc('visibility_score');

        $all = $urlQuery->take($this->limit + 1)->get();
        $hasMore = $all->count() > $this->limit;
        $urls = $all->take($this->limit);

        return view('seo::livewire.seo-competitors', [
            'domains' => $domains,
            'urls' => $urls,
            'hasMore' => $hasMore,
        ])->layout('platform::layouts.app');
    }
}
