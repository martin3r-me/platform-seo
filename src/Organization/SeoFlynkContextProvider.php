<?php

namespace Platform\Seo\Organization;

use Platform\FlynkConnector\Contracts\ProvidesFlynkContext;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Liefert den SEO-Kontext eines Organisations-Knotens an den Flynk-Connector.
 *
 * Der Connector sammelt beim Push pro Knoten den Kontext aller Lieferanten ein —
 * SEO ruft Flynk nie selbst. So fließen die am Knoten hängenden Handlungs-
 * empfehlungen (und Cluster-/URL-Kennzahlen) in den Kunden-Container.
 */
class SeoFlynkContextProvider implements ProvidesFlynkContext
{
    public function contextKey(): string
    {
        return 'seo';
    }

    public function contextForEntity(OrganizationEntity $node): ?array
    {
        $linker = app(SeoOrganizationLinker::class);
        $nodeId = $node->id;

        $signalIds = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_SIGNAL, $nodeId);
        $clusterIds = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_CLUSTER, $nodeId);
        $urlIds = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_URL, $nodeId);

        $recommendations = $this->recommendations($signalIds, $urlIds, (int) $node->team_id);
        $clusters = $this->clusters($clusterIds);
        $urls = $this->urlSummary($urlIds);

        if (empty($recommendations) && empty($clusters) && $urls === null) {
            return null;
        }

        return array_filter([
            'recommendations' => $recommendations ?: null,
            'clusters' => $clusters ?: null,
            'urls' => $urls,
        ], fn ($value) => $value !== null);
    }

    /**
     * Offene Empfehlungen des Knotens: sowohl direkt an den Knoten gehängte Signale
     * (ALIAS_SIGNAL) als auch die offenen Empfehlungen seiner URLs (ALIAS_URL) —
     * so trägt der Flynk-Push dieselben Empfehlungen wie der Agentur-Workspace (U1).
     */
    protected function recommendations(array $signalIds, array $urlIds, int $teamId): array
    {
        if (empty($signalIds) && empty($urlIds)) {
            return [];
        }

        return SeoSignal::where('team_id', $teamId)
            ->where('signal_type', 'like', 'rec\_%')
            ->where('status', '!=', 'resolved')
            ->where(function ($q) use ($signalIds, $urlIds) {
                if (! empty($signalIds)) {
                    $q->orWhereIn('id', $signalIds);
                }
                if (! empty($urlIds)) {
                    $q->orWhereIn('url_id', $urlIds);
                }
            })
            ->with('url:id,url')
            ->orderByDesc('detected_at')
            ->limit(50)
            ->get()
            ->map(fn (SeoSignal $s) => array_filter([
                'type' => $s->signal_type,
                'title' => $s->title,
                'description' => $s->description,
                'severity' => $s->severity,
                'status' => $s->status,
                'url' => $s->url?->url,
                'detected_at' => optional($s->detected_at)->toDateString(),
                'evidence' => $s->context,
            ], fn ($v) => $v !== null))
            ->values()
            ->all();
    }

    protected function clusters(array $clusterIds): array
    {
        if (empty($clusterIds)) {
            return [];
        }

        return SeoKeywordCluster::whereIn('id', $clusterIds)
            ->get()
            ->map(fn (SeoKeywordCluster $c) => [
                'name' => $c->name,
                'coverage_pct' => (float) $c->coverage_pct,
                'health_score' => $c->health_score,
                'visibility' => (float) $c->visibility,
                'keyword_count' => (int) $c->keyword_count,
                'top10_count' => (int) $c->top10_count,
            ])
            ->values()
            ->all();
    }

    protected function urlSummary(array $urlIds): ?array
    {
        if (empty($urlIds)) {
            return null;
        }

        $agg = SeoUrl::whereIn('id', $urlIds)
            ->selectRaw('COUNT(*) as total, SUM(is_own) as own, SUM(visibility_score) as visibility, SUM(visitors_30d) as visitors')
            ->first();

        return [
            'total' => (int) $agg->total,
            'own' => (int) $agg->own,
            'visibility' => round((float) $agg->visibility, 4),
            'visitors_30d' => (int) $agg->visitors,
        ];
    }
}
