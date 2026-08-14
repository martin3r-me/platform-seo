<?php

namespace Platform\Seo\Organization;

use Illuminate\Database\Eloquent\Collection;
use Platform\FlynkConnector\Contracts\ProvidesFlynkContext;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;
use Platform\Seo\Services\SeoSignalRouting;

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
        $urlIds = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_URL, $nodeId);

        // Cluster + Briefs hängen NICHT extra am Knoten — sie leiten sich aus den URLs
        // des Knotens ab (URL→Keyword→Cluster→Brief) und werden per Reifegrad (Status)
        // gefiltert. So reicht die eine URL-Verknüpfung; Cluster/Briefs fließen automatisch.
        $reifeCluster = $this->clusterModelsForNode($linker, $nodeId, $urlIds);

        $recommendations = $this->recommendations($signalIds, $urlIds, (int) $node->team_id);
        $clusters = $this->clusters($reifeCluster);
        $contentBriefs = $this->contentBriefs($reifeCluster->pluck('id')->all());
        $urls = $this->urlSummary($urlIds);

        if (empty($recommendations) && empty($clusters) && empty($contentBriefs) && $urls === null) {
            return null;
        }

        return array_filter([
            'recommendations' => $recommendations ?: null,
            'clusters' => $clusters ?: null,
            'content_briefs' => $contentBriefs ?: null,
            'urls' => $urls,
        ], fn ($value) => $value !== null);
    }

    /**
     * Offene Empfehlungen des Knotens: sowohl direkt an den Knoten gehängte Signale
     * (ALIAS_SIGNAL) als auch die offenen Empfehlungen seiner URLs (ALIAS_URL) —
     * so trägt der Flynk-Push dieselben Empfehlungen wie der Agentur-Workspace (U1).
     *
     * Content-getriebene Signale (target=content_brief) sind bewusst AUSGENOMMEN:
     * die inhaltliche Arbeit entsteht hier (SEO-intern) und geht als fertiger Brief
     * über content_briefs raus — nicht als „mach-Brief"-Auftrag an FLYNK. FLYNK
     * bekommt hier also nur, was DORT umgesetzt wird (Seitenänderungen etc.).
     */
    protected function recommendations(array $signalIds, array $urlIds, int $teamId): array
    {
        if (empty($signalIds) && empty($urlIds)) {
            return [];
        }

        return SeoSignal::where('team_id', $teamId)
            ->where('status', '!=', 'resolved')
            // Nur definition-getriebene Signale (das gesteuerte System). Legacy ist abgeschafft.
            ->whereNotNull('signal_definition_id')
            // Content-Signale bleiben intern (→ Content-Brief), nicht als FLYNK-Auftrag pushen.
            ->whereNotIn('signal_type', SeoSignalRouting::contentPatterns())
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
            ->map(function (SeoSignal $s) {
                $kind = SeoSignalRouting::kindFor($s->signal_type);
                $ai = $s->context['ai'] ?? null;

                // change_kind + target sagen Flynk, welche Art Arbeit das ist
                // (Seitenänderung → Aufgabe, Inhalt → Content-Brief). recommendation/steps
                // sind die konkrete, geerdete KI-Handlungsanweisung. ref = stabile Rückverknüpfung.
                return array_filter([
                    'ref' => $s->uuid,
                    'type' => $s->signal_type,
                    'change_kind' => $kind,
                    'target' => SeoSignalRouting::targetFor($kind),
                    'title' => $s->title,
                    'description' => $s->description,
                    'recommendation' => $ai['recommendation'] ?? null,
                    'steps' => $ai['steps'] ?? null,
                    'severity' => $s->severity,
                    'status' => $s->status,
                    'url' => $s->url?->url,
                    'detected_at' => optional($s->detected_at)->toDateString(),
                    'evidence' => $s->context,
                ], fn ($v) => $v !== null);
            })
            ->values()
            ->all();
    }

    /**
     * Die reifen Cluster des Knotens. Zwei Quellen, vereint:
     *  1. abgeleitet aus den URLs des Knotens (URL→seo_url_keywords→Keyword.cluster_id) —
     *     der Normalfall: wer die URL verortet, bekommt automatisch deren Cluster.
     *  2. optional manuell an den Knoten gehängte Cluster (ALIAS_CLUSTER) — Override für
     *     strategische/domänenübergreifende Cluster, die keiner Knoten-URL folgen.
     * Reifegrad-Gate: nur active/monitored (candidate/stalled/archived bleiben intern).
     */
    protected function clusterModelsForNode(SeoOrganizationLinker $linker, int $nodeId, array $urlIds): Collection
    {
        $derived = empty($urlIds) ? [] : SeoKeyword::query()
            ->whereNotNull('cluster_id')
            ->whereIn('id', function ($q) use ($urlIds) {
                $q->select('keyword_id')->from('seo_url_keywords')->whereIn('url_id', $urlIds);
            })
            ->distinct()
            ->pluck('cluster_id')
            ->all();

        $manual = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_CLUSTER, $nodeId);

        $clusterIds = array_values(array_unique(array_merge($derived, $manual)));
        if (empty($clusterIds)) {
            return new Collection;
        }

        return SeoKeywordCluster::whereIn('id', $clusterIds)
            ->whereIn('status', [SeoKeywordCluster::STATUS_ACTIVE, SeoKeywordCluster::STATUS_MONITORED])
            ->get();
    }

    protected function clusters(Collection $clusters): array
    {
        return $clusters
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

    /**
     * Fertige Content-Briefs des Knotens — der Produktions-Plan, den FLYNK umsetzen soll.
     * Aufgelöst über die reifen Cluster des Knotens (Brief↔Cluster-Pivot), NICHT über einen
     * eigenen Brief-Knoten-Link (den gibt es im SEO-Modul nicht). Reine Entwürfe (draft)
     * bleiben interne WIP; alles Freigegebene (briefed, in_production, review, published)
     * fließt inkl. Gliederung (sections) + Ziel-Cluster.
     */
    protected function contentBriefs(array $clusterIds): array
    {
        if (empty($clusterIds)) {
            return [];
        }

        return SeoContentBrief::query()
            ->whereHas('clusters', fn ($q) => $q->whereIn('seo_keyword_clusters.id', $clusterIds))
            ->where('status', '!=', 'draft')
            ->with(['sections', 'clusters'])
            ->orderBy('order')
            ->get()
            ->map(fn (SeoContentBrief $b) => array_filter([
                'ref'               => $b->uuid,
                'name'              => $b->name,
                'description'       => $b->description,
                'content_type'      => $b->content_type,
                'search_intent'     => $b->search_intent,
                'status'            => $b->status,
                'target_url'        => $b->target_url,
                'target_slug'       => $b->target_slug,
                'target_word_count' => $b->target_word_count,
                'clusters'          => $b->clusters->pluck('name')->filter()->values()->all(),
                // Gliederung: Überschriften + Beschreibung + Ziel-Keywords je Abschnitt.
                'sections'          => $b->sections->map(fn ($s) => array_filter([
                    'heading'         => $s->heading,
                    'level'           => $s->heading_level,
                    'description'     => $s->description,
                    'target_keywords' => $s->target_keywords,
                    'notes'           => $s->notes,
                ], fn ($v) => $v !== null && $v !== '' && $v !== []))->values()->all(),
            ], fn ($v) => $v !== null && $v !== '' && $v !== []))
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
