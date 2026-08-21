<?php

namespace Platform\Seo\Organization;

use Illuminate\Database\Eloquent\Collection;
use Platform\FlynkConnector\Contracts\ProvidesFlynkContext;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoCta;
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

        // Cluster + Briefs hängen NICHT extra am Knoten. Anker ist die DOMAIN: der Knoten
        // ist über seine URL(s) an eine Domain gebunden (z.B. nodera.health). Briefs tragen
        // ihre target_url auf dieser Domain, Cluster hängen an diesen Briefs. So fließt die
        // Arbeit automatisch — auch für NEUE Seiten, die noch für nichts ranken (dort ist die
        // Ranking-Kette URL→Keyword leer, die Domain aber trägt).
        $domains = $this->nodeDomains($urlIds);
        $briefs = $this->briefModelsForNode($domains);
        $clusterModels = $this->clusterModelsForNode($linker, $nodeId, $urlIds, $briefs);

        $recommendations = $this->recommendations($signalIds, $urlIds, (int) $node->team_id);
        $clusters = $this->clusters($clusterModels);
        $contentBriefs = $this->contentBriefs($briefs);
        $urls = $this->urlSummary($urlIds);
        $ctas = $this->ctaTargets($urlIds);

        if (empty($recommendations) && empty($clusters) && empty($contentBriefs) && $urls === null && empty($ctas)) {
            return null;
        }

        return array_filter([
            'recommendations' => $recommendations ?: null,
            'clusters' => $clusters ?: null,
            'content_briefs' => $contentBriefs ?: null,
            'urls' => $urls,
            'ctas' => $ctas ?: null,
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
     * Die Domains des Knotens — aus seinen verorteten URLs (Host, ohne www).
     * Das ist der Anker: alles, was auf dieser Domain produziert wird, gehört zum Knoten.
     */
    protected function nodeDomains(array $urlIds): array
    {
        if (empty($urlIds)) {
            return [];
        }

        return SeoUrl::whereIn('id', $urlIds)->pluck('url')
            ->map(fn ($u) => $this->host($u))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /** Host einer URL, normalisiert (kleingeschrieben, ohne führendes www.). */
    protected function host(?string $url): ?string
    {
        if (! $url) {
            return null;
        }
        $host = parse_url(str_contains($url, '://') ? $url : 'https://'.$url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    /**
     * Fertige Content-Briefs des Knotens — angehängt über die DOMAIN ihrer target_url.
     * Robust auch für neue Seiten: der Brief zielt auf nodera.health/…, die Seite muss
     * dafür nicht ranken oder als URL getrackt sein. Reine Entwürfe (draft) bleiben WIP.
     */
    protected function briefModelsForNode(array $domains): Collection
    {
        if (empty($domains)) {
            return new Collection;
        }

        return SeoContentBrief::query()
            ->where('status', '!=', 'draft')
            ->whereNotNull('target_url')
            // Grob-Vorfilter auf DB-Ebene; exakter Host-Abgleich danach in PHP.
            ->where(function ($q) use ($domains) {
                foreach ($domains as $d) {
                    $q->orWhere('target_url', 'like', '%'.$d.'%');
                }
            })
            ->with(['sections', 'clusters'])
            ->orderBy('order')
            ->get()
            ->filter(fn (SeoContentBrief $b) => in_array($this->host($b->target_url), $domains, true))
            ->values();
    }

    /**
     * Die Cluster des Knotens, aus drei Quellen vereint:
     *  1. von den Domain-Briefs des Knotens referenziert (Pivot) — der Normalfall.
     *  2. Keyword-Rankings: Cluster, deren Keywords auf den eigenen URLs des Knotens ranken
     *     (greift bei etablierten Seiten; bei neuen Seiten leer).
     *  3. optional manuell an den Knoten gehängt (ALIAS_CLUSTER) — Override für strategische
     *     / domänenübergreifende Cluster.
     * Reifegrad-Gate: alles außer archiviert (candidate/active/monitored/stalled fließen —
     * gerade der candidate-Zustand IST die zu bauende Arbeit).
     */
    protected function clusterModelsForNode(SeoOrganizationLinker $linker, int $nodeId, array $urlIds, Collection $briefs): Collection
    {
        $fromBriefs = $briefs->flatMap(fn (SeoContentBrief $b) => $b->clusters->pluck('id'))->all();

        $fromRankings = empty($urlIds) ? [] : SeoKeyword::query()
            ->whereNotNull('cluster_id')
            ->whereIn('id', function ($q) use ($urlIds) {
                $q->select('keyword_id')->from('seo_url_keywords')->whereIn('url_id', $urlIds);
            })
            ->distinct()
            ->pluck('cluster_id')
            ->all();

        $manual = $linker->linkableIdsForNode(SeoOrganizationLinker::ALIAS_CLUSTER, $nodeId);

        $clusterIds = array_values(array_unique(array_filter(array_merge($fromBriefs, $fromRankings, $manual))));
        if (empty($clusterIds)) {
            return new Collection;
        }

        return SeoKeywordCluster::whereIn('id', $clusterIds)
            ->where('status', '!=', SeoKeywordCluster::STATUS_ARCHIVED)
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
     * Serialisiert die Domain-Briefs des Knotens — der Produktions-Plan, den FLYNK umsetzen
     * soll — inkl. Gliederung (sections) + Ziel-Cluster.
     */
    protected function contentBriefs(Collection $briefs): array
    {
        return $briefs
            ->map(fn (SeoContentBrief $b) => array_filter([
                'ref'               => $b->uuid,
                // Provenance-Marker: MUSS von FLYNK unverändert in den <head> der erzeugten
                // Seite. Daran erkennt der SeoContentBriefReconciler die Umsetzung automatisch
                // (leichter HTTP-Fetch, kein API-Cost) und schaltet den Brief auf published.
                'provenance'        => [
                    'meta_tag'    => $b->markerMeta(),
                    'instruction' => 'Diese Zeile unverändert in den <head> der zu dieser Seite gehörenden '
                        .'URL einbauen. Sie ist der Umsetzungs-Nachweis: das SEO-System liest den Marker '
                        .'zurück und markiert den Brief automatisch als veröffentlicht. Überlebt Slug-Änderungen.',
                ],
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

    /**
     * Ziel-CTAs je URL für den Flynk-Push: WAS die Agentur bauen soll (typisiert:
     * Mechanik + Prominenz + Copy-Vorschlag), NICHT wo/wie — das ist ihr Handwerk.
     * Nur source=target (observed bleibt intern fürs Gegenmessen).
     */
    protected function ctaTargets(array $urlIds): array
    {
        if (empty($urlIds)) {
            return [];
        }

        $ctas = SeoCta::whereIn('url_id', $urlIds)
            ->where('source', SeoCta::SOURCE_TARGET)
            ->with(['ctaType', 'url'])
            ->orderBy('url_id')
            ->orderByRaw("FIELD(prominence,'primary','secondary','tertiary')")
            ->get();

        if ($ctas->isEmpty()) {
            return [];
        }

        return $ctas->groupBy('url_id')->map(function ($group) {
            return [
                'url' => $group->first()->url?->url,
                'ctas' => $group->map(fn (SeoCta $c) => array_filter([
                    'type' => $c->ctaType?->code,
                    'mechanism' => $c->ctaType?->mechanism,
                    'prominence' => $c->prominence,
                    'label' => $c->label,
                    'target' => $c->target,
                ], fn ($v) => $v !== null && $v !== ''))->values()->all(),
            ];
        })->values()->all();
    }
}
