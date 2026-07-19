<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Core\Contracts\SeoSignalServiceInterface;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
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
        $signals = app(SeoSignalServiceInterface::class)
            ->getSignalsForNode((int) $this->seoSettings->team_id, $this->entityId);

        $visibility = 0.0;
        $visitors = 0;
        $clicks = 0;
        $backlinks = 0;
        $openRecommendations = 0;
        $own = 0;

        foreach ($signals as $s) {
            $visibility += (float) ($s['visibility'] ?? 0);
            $visitors += (int) ($s['traffic']['visitors_30d'] ?? 0);
            $clicks += (int) ($s['gsc']['clicks'] ?? 0);
            $backlinks += (int) ($s['backlinks']['count'] ?? 0);
            $openRecommendations += count($s['recommendations'] ?? []);
            if (! empty($s['is_own'])) {
                $own++;
            }
        }

        return view('seo::livewire.seo-context-workspace', [
            'signals' => $signals,
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
