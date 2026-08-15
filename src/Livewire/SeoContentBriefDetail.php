<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoKeyword;

/**
 * Content-Brief-Detail (U3) — der Produktions-Plan für ein Stück Content.
 *
 * Zeigt Meta (Typ, Intent, Ziel-URL, Umfang), das verknüpfte Cluster, die
 * Abschnitte, die Notizen — sowie (aus brands aktiviert): die internen
 * Verlinkungen (Hub-and-Spoke), die Revisions-Historie und das Ranking-
 * Feedback (rankt die Ziel-URL wirklich für die Cluster-Keywords?).
 */
class SeoContentBriefDetail extends Component
{
    use ResolvesTeamSettings;

    public SeoContentBrief $brief;

    public function mount(SeoContentBrief $brief): void
    {
        $this->resolveSettings();

        // Team-Guard: nur eigene Briefs.
        abort_unless((int) $brief->team_id === (int) $this->seoSettings->team_id, 404);

        $this->brief = $brief;
    }

    /**
     * Ranking-Feedback: schließt den Loop — für die Cluster-Keywords des Briefs
     * die aktuelle Position + ob die rankende URL wirklich unsere Ziel-URL ist
     * (target_match). So sieht man, ob der Content wirkt, nicht nur ob er existiert.
     */
    protected function rankingFeedback(): array
    {
        $target = $this->brief->target_url;
        $clusterIds = $this->brief->clusters->pluck('id');

        $empty = ['rows' => collect(), 'ranking' => 0, 'target_hits' => 0, 'target_url' => $target];
        if ($clusterIds->isEmpty()) {
            return $empty;
        }

        $keywords = SeoKeyword::whereIn('cluster_id', $clusterIds)
            ->where('team_id', $this->seoSettings->team_id)
            ->orderByDesc('search_volume')
            ->limit(50)
            ->get(['id', 'keyword', 'search_volume', 'position', 'ranked_url']);

        $targetHost = $target ? parse_url($target, PHP_URL_HOST) : null;
        $targetPath = $target ? rtrim((string) parse_url($target, PHP_URL_PATH), '/') : null;

        $rows = $keywords->map(function ($kw) use ($targetHost, $targetPath) {
            $ranks = $kw->position !== null && (int) $kw->position > 0;
            $match = false;
            if ($ranks && $kw->ranked_url && $targetHost) {
                $rHost = parse_url($kw->ranked_url, PHP_URL_HOST);
                $rPath = rtrim((string) parse_url($kw->ranked_url, PHP_URL_PATH), '/');
                $match = $rHost === $targetHost && ($targetPath === '' || $rPath === $targetPath);
            }

            return [
                'keyword' => $kw->keyword,
                'volume' => (int) $kw->search_volume,
                'position' => $ranks ? (int) $kw->position : null,
                'ranked_url' => $kw->ranked_url,
                'target_match' => $match,
            ];
        });

        return [
            'rows' => $rows,
            'ranking' => $rows->whereNotNull('position')->count(),
            'target_hits' => $rows->where('target_match', true)->count(),
            'target_url' => $target,
        ];
    }

    public function render()
    {
        $this->brief->load([
            'sections' => fn ($q) => $q->orderBy('order'),
            'notes',
            'clusters',
            'outgoingLinks.target:id,uuid,name,content_type,status',
            'incomingLinks.source:id,uuid,name,content_type,status',
            'revisions',
        ]);

        return view('seo::livewire.seo-content-brief-detail', [
            'brief' => $this->brief,
            'sections' => $this->brief->sections,
            'notes' => $this->brief->notes,
            'clusters' => $this->brief->clusters,
            'outgoingLinks' => $this->brief->outgoingLinks,
            'incomingLinks' => $this->brief->incomingLinks,
            'revisions' => $this->brief->revisions,
            'ranking' => $this->rankingFeedback(),
        ])->layout('platform::layouts.app');
    }
}
