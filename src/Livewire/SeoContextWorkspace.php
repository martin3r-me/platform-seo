<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Core\Contracts\SeoSignalServiceInterface;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Kontext-Workspace (U1) — die SEO-Sicht eines Organisations-Knotens (z.B. Kunde).
 *
 * Bündelt die an den Knoten gehängten URLs samt ihrer zentral gemessenen Signale
 * über den Keystone (getSignalsForNode). Der Roll-up „Daten laufen in den Baum",
 * sichtbar als Arbeitsplatz.
 */
class SeoContextWorkspace extends Component
{
    use ResolvesTeamSettings;

    public int $entityId;
    public ?string $nodeName = null;

    public function mount(int $entity): void
    {
        $this->resolveSettings();
        $this->entityId = $entity;
        $this->nodeName = app(SeoOrganizationLinker::class)->nodeName($entity);
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;

        $signals = app(SeoSignalServiceInterface::class)
            ->getSignalsForNode($teamId, $this->entityId);

        $visibility = 0.0;
        $visitors = 0;
        $clicks = 0;
        $backlinks = 0;
        $openRecommendations = 0;
        $own = 0;
        $ownUrlIds = [];

        foreach ($signals as $urlId => $s) {
            $visibility += (float) ($s['visibility'] ?? 0);
            $visitors += (int) ($s['traffic']['visitors_30d'] ?? 0);
            $clicks += (int) ($s['gsc']['clicks'] ?? 0);
            $backlinks += (int) ($s['backlinks']['count'] ?? 0);
            $openRecommendations += count($s['recommendations'] ?? []);
            if (! empty($s['is_own'])) {
                $own++;
                $ownUrlIds[] = (int) $urlId;
            }
        }

        // Wettbewerber im Kontext (U4): Domains, die auf denselben Keywords ranken
        // wie die eigenen URLs dieses Knotens — abgeleitet, nicht verlinkt.
        $competitors = collect();
        if ($ownUrlIds) {
            $competitors = SeoUrl::where('team_id', $teamId)
                ->where('is_own', false)
                ->where('status', 'active')
                ->whereHas('keywords', fn ($q) => $q->whereHas('urls', fn ($q2) => $q2->whereIn('seo_url_keywords.url_id', $ownUrlIds)))
                ->selectRaw('domain, COUNT(*) as url_count, AVG(visibility_score) as avg_visibility, SUM(keyword_count) as total_keywords')
                ->groupBy('domain')
                ->orderByDesc('avg_visibility')
                ->limit(12)
                ->get();
        }

        return view('seo::livewire.seo-context-workspace', [
            'signals' => $signals,
            'competitors' => $competitors,
            'kpis' => [
                'urls' => count($signals),
                'own' => $own,
                'visibility' => round($visibility, 0),
                'visitors' => $visitors,
                'clicks' => $clicks,
                'backlinks' => $backlinks,
                'open_recommendations' => $openRecommendations,
            ],
        ])->layout('platform::layouts.app');
    }
}
