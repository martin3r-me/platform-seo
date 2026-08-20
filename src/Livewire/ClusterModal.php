<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoUrl;

/**
 * Cluster-Inspektor als inline-Modal — eigenständige, wiederverwendbare
 * Komponente (KEIN Anbau an SeoPortfolioDetail). Wird per Event `open-cluster`
 * geöffnet, egal aus welchem Kontext (Wirkungsraum, Liste, URL-Scope). Zeigt
 * Keywords + beste eigene Position + Ziel-Seite (Pillar, setzbar); für die Tiefe
 * verlinkt es auf die volle Cluster-Seite. Übersteht den Routen-Split unbeschadet.
 */
class ClusterModal extends Component
{
    use ResolvesTeamSettings;

    public bool $show = false;

    public ?int $clusterId = null;

    public function mount(): void
    {
        $this->resolveSettings();
    }

    #[On('open-cluster')]
    public function open(int $id): void
    {
        $c = SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->find($id);
        if (! $c) {
            return;
        }
        $this->clusterId = $c->id;
        $this->show = true;
    }

    public function close(): void
    {
        $this->show = false;
        $this->clusterId = null;
    }

    public function setPillarUrl(int $urlId): void
    {
        $c = SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->find($this->clusterId);
        $url = SeoUrl::where('team_id', $this->seoSettings->team_id)->where('id', $urlId)->where('is_own', true)->first();
        if ($c && $url) {
            $c->update(['pillar_url_id' => $url->id]);
        }
    }

    public function removePillar(): void
    {
        $c = SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->find($this->clusterId);
        if ($c) {
            $c->update(['pillar_url_id' => null]);
        }
    }

    public function render()
    {
        $cluster = $this->clusterId
            ? SeoKeywordCluster::where('team_id', $this->seoSettings->team_id)->with('pillarUrl')->find($this->clusterId)
            : null;

        $keywords = collect();
        $bestPos = collect();
        $candidates = collect();
        $volume = 0;

        if ($cluster) {
            $teamId = (int) $this->seoSettings->team_id;
            $keywords = SeoKeyword::where('cluster_id', $cluster->id)->orderByDesc('search_volume')->limit(100)->get();
            $allIds = SeoKeyword::where('cluster_id', $cluster->id)->pluck('id');
            $volume = (int) SeoKeyword::where('cluster_id', $cluster->id)->sum('search_volume');

            // Beste eigene Position je Keyword.
            $bestPos = DB::table('seo_url_keywords as uk')
                ->join('seo_urls as u', function ($j) use ($teamId) {
                    $j->on('u.id', '=', 'uk.url_id')->where('u.is_own', true)
                        ->whereNull('u.deleted_at')->where('u.team_id', $teamId);
                })
                ->whereIn('uk.keyword_id', $allIds)
                ->groupBy('uk.keyword_id')
                ->selectRaw('uk.keyword_id, MIN(uk.position) as pos')
                ->pluck('pos', 'keyword_id');

            // Ziel-Seiten-Kandidaten: eigene URLs, die für Cluster-Keywords ranken.
            $candidates = DB::table('seo_url_keywords as uk')
                ->join('seo_urls as u', function ($j) use ($teamId) {
                    $j->on('u.id', '=', 'uk.url_id')->where('u.is_own', true)
                        ->whereNull('u.deleted_at')->where('u.team_id', $teamId);
                })
                ->whereIn('uk.keyword_id', $allIds)
                ->groupBy('u.id', 'u.url', 'u.path')
                ->selectRaw('u.id, u.url, u.path, COUNT(DISTINCT uk.keyword_id) as kw_covered')
                ->orderByDesc('kw_covered')->limit(20)->get();
        }

        return view('seo::livewire.cluster-modal', [
            'cluster' => $cluster,
            'keywords' => $keywords,
            'bestPos' => $bestPos,
            'candidates' => $candidates,
            'volume' => $volume,
        ]);
    }
}
