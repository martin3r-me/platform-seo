<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoOrganizationLinker;

/**
 * Perspektive = Anker + Selektor. Eine Engine, viele Linsen:
 *   - subtree     : ein Knoten + alle Nachfahren (Hierarchie)
 *   - relation    : von einem Knoten über einen Relationstyp (z.B. „alle Kunden")
 *   - source      : von einem Modul eingespeiste URLs (z.B. Syltjunkie)
 *   - unassigned  : Agentur-URLs ohne Kontext (die Ablage / Arbeitsschlange)
 *
 * Der Selektor bestimmt nur die URL-Menge; Aggregation + Ansicht sind gemeinsam.
 */
class SeoPerspective extends Component
{
    use ResolvesTeamSettings;

    public string $mode = 'subtree';
    public ?int $entityId = null;
    public ?string $relation = null;
    public ?string $module = null;
    public ?string $heading = null;
    public ?string $subtitle = null;

    // Kontext-Tabs: die Linsen, gebunden an diese Perspektive.
    public string $tab = 'overview';

    // Arbeitsplatz: Auswahl + Ziel-Knoten für Bulk-Zuweisung/Klassifizierung.
    public array $selected = [];
    public ?int $assignNodeId = null;
    public ?string $notice = null;

    public function mount(?int $entity = null, ?string $relation = null, ?string $module = null): void
    {
        $this->resolveSettings();
        $linker = app(SeoOrganizationLinker::class);

        if ($entity !== null && request()->route()?->getName() === 'seo.perspective.customers') {
            $this->mode = 'customers';
            $this->entityId = $entity;
            $anchor = $linker->nodeName($entity) ?: ('Knoten #'.$entity);
            $this->heading = 'Kunden von '.$anchor;
            $this->subtitle = 'alle Kunden über die Engagement-Ebene';
        } elseif ($module !== null) {
            $this->mode = 'source';
            $this->module = $module;
            $this->heading = config('seo.provenance.'.$module.'.label') ?? ucfirst($module);
            $this->subtitle = 'Quelle · vom Modul eingespeiste URLs';
        } elseif ($entity !== null && $relation !== null) {
            $this->mode = 'relation';
            $this->entityId = $entity;
            $this->relation = $relation;
            $anchor = $linker->nodeName($entity) ?: ('Knoten #'.$entity);
            $this->heading = $linker->relationName($relation) ?: $relation;
            $this->subtitle = 'Relation · ausgehend von '.$anchor;
        } elseif ($entity !== null) {
            $this->mode = 'subtree';
            $this->entityId = $entity;
            $this->heading = $linker->nodeName($entity) ?: ('Knoten #'.$entity);
            $this->subtitle = 'Perspektive über den ganzen Teilbaum';
        } else {
            $this->mode = 'unassigned';
            $this->heading = 'Nicht eingeordnet';
            $this->subtitle = 'Ablage · Agentur-URLs ohne Kontext — hier verteilen oder klassifizieren';
        }
    }

    public function selectAll(array $ids): void
    {
        $this->selected = array_map('intval', $ids);
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /**
     * Ausgewählte URLs einem Knoten zuweisen — optional zugleich als Wettbewerber
     * klassifizieren (is_own=false). Der Kern des Arbeitsplatzes: verteilen + rollen.
     */
    public function assignSelected(bool $asCompetitor = false): void
    {
        if (empty($this->selected) || ! $this->assignNodeId) {
            return;
        }

        $teamId = (int) $this->seoSettings->team_id;
        $ids = array_map('intval', $this->selected);

        if ($asCompetitor) {
            SeoUrl::whereIn('id', $ids)->where('team_id', $teamId)->update(['is_own' => false]);
        }

        $linker = app(SeoOrganizationLinker::class);
        foreach ($ids as $urlId) {
            $linker->addNode(SeoOrganizationLinker::ALIAS_URL, $urlId, (int) $this->assignNodeId);
        }

        $count = count($ids);
        $this->notice = $asCompetitor
            ? "{$count} URL(s) als Wettbewerber diesem Kontext zugeordnet."
            : "{$count} URL(s) dem Kontext zugeordnet.";
        $this->selected = [];
        $this->assignNodeId = null;
    }

    /**
     * Nur als Wettbewerber markieren (ohne Knoten) — schiebt sie aus der Ablage
     * in die Wettbewerber-Linse.
     */
    public function markCompetitor(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $teamId = (int) $this->seoSettings->team_id;
        $ids = array_map('intval', $this->selected);
        SeoUrl::whereIn('id', $ids)->where('team_id', $teamId)->update(['is_own' => false]);

        $this->notice = count($ids).' URL(s) als Wettbewerber markiert.';
        $this->selected = [];
    }

    /** Empfehlung als erledigt markieren — Inline-Aktion im „Was jetzt". */
    public function resolveSignal(int $id): void
    {
        SeoSignal::where('id', $id)
            ->where('team_id', (int) $this->seoSettings->team_id)
            ->update(['status' => 'resolved']);

        $this->notice = 'Als erledigt markiert.';
    }

    public function render()
    {
        $teamId = (int) $this->seoSettings->team_id;
        $linker = app(SeoOrganizationLinker::class);

        $urlIds = [];
        $nodesCount = 0;
        $relations = [];
        $subPerspectives = [];
        $customerCount = 0;

        switch ($this->mode) {
            case 'subtree':
                $subtree = $linker->descendantEntityIds($this->entityId);
                $nodesCount = count($subtree);
                $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $subtree);
                $relations = $linker->availableRelations($this->entityId);
                $subPerspectives = $this->entityPerspectives($this->childEntityIds($this->entityId), $linker);
                $customerCount = count($linker->customersViaEngagement($this->entityId));
                break;

            case 'customers':
                $customerIds = $linker->customersViaEngagement($this->entityId);
                $nodesCount = count($customerIds);
                $allIds = [];
                foreach ($customerIds as $cid) {
                    foreach ($linker->descendantEntityIds((int) $cid) as $d) {
                        $allIds[$d] = true;
                    }
                }
                $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, array_keys($allIds));
                $subPerspectives = $this->entityPerspectives($customerIds, $linker);
                break;

            case 'relation':
                $related = $linker->relatedEntityIds($this->entityId, $this->relation);
                $nodesCount = count($related);
                $urlIds = $linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $related);
                $subPerspectives = $this->entityPerspectives($related, $linker);
                break;

            case 'source':
                $urlIds = SeoUrlRegistration::where('source_module', $this->module)
                    ->pluck('url_id')->map(fn ($i) => (int) $i)->unique()->all();
                break;

            case 'unassigned':
                $urlIds = $this->unassignedUrlIds($teamId, $linker);
                break;
        }

        // Root-only: Unterseiten nicht einzeln listen — man ordnet Seiten zu, die
        // Kinder folgen. Hält Ablage & Perspektive handlungsrelevant.
        $childUrlIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id')->all();

        $urls = collect();
        if (! empty($urlIds)) {
            $urls = SeoUrl::where('team_id', $teamId)
                ->whereIn('id', $urlIds)
                ->where('status', 'active')
                ->when(! empty($childUrlIds), fn ($q) => $q->whereNotIn('id', $childUrlIds))
                ->orderByDesc('visibility_score')
                ->get();

            $ownerByUrl = [];
            foreach (SeoUrlRegistration::whereIn('url_id', $urls->pluck('id'))
                        ->where('source_module', '!=', 'seo')
                        ->get(['url_id', 'source_module']) as $reg) {
                $ownerByUrl[$reg->url_id] ??= $reg->source_module;
            }
            $urls->each(function (SeoUrl $u) use ($ownerByUrl) {
                $u->provenance_key = ! $u->is_own ? 'competitor' : ($ownerByUrl[$u->id] ?? 'seo');
            });
        }

        $own = $urls->where('is_own', true);
        $kpis = [
            'urls' => $urls->count(),
            'own' => $own->count(),
            'competitors' => $urls->count() - $own->count(),
            'nodes' => $nodesCount,
            'visibility' => round((float) $own->sum('visibility_score'), 0),
            'keywords' => (int) $own->sum('keyword_count'),
            'search_volume' => (int) $own->sum('total_search_volume'),
            'backlinks' => (int) $own->sum('backlink_count'),
            'visitors' => (int) $own->sum('visitors_30d'),
        ];

        // Kontext-gebundene Tab-Daten: die „Linsen" gehören zu genau dieser Perspektive.
        $ownUrlIds = $own->pluck('id')->all();
        $competitors = collect();
        $recommendations = collect();
        $clusters = collect();

        // „Was jetzt" (Held der Übersicht) + Veränderung — kontextunabhängig geladen.
        $topRecommendations = collect();
        $openRecCount = 0;
        $visibilityDelta = null;

        if (! empty($ownUrlIds)) {
            $openRecCount = SeoSignal::whereIn('url_id', $ownUrlIds)
                ->where('signal_type', 'like', 'rec\_%')
                ->whereIn('status', ['new', 'acknowledged'])
                ->count();

            $sevRank = ['critical' => 0, 'high' => 0, 'error' => 0, 'warning' => 1, 'watch' => 2, 'info' => 3];
            $topRecommendations = SeoSignal::whereIn('url_id', $ownUrlIds)
                ->where('signal_type', 'like', 'rec\_%')
                ->whereIn('status', ['new', 'acknowledged'])
                ->with('url:id,url,domain,path')
                ->orderByDesc('detected_at')
                ->limit(40)
                ->get()
                ->sortBy(fn ($s) => (($sevRank[strtolower($s->severity ?? '')] ?? 5) * 1000000000) - (int) ($s->context['volume'] ?? 0))
                ->take(6)
                ->values();

            // Sichtbarkeits-Δ vs. ~30 Tage: jüngster Snapshot je URL <= Stichtag, summiert.
            $cut = now()->subDays(30)->toDateString();
            $latestPerUrl = [];
            foreach (\Platform\Seo\Models\SeoUrlSnapshot::whereIn('url_id', $ownUrlIds)
                        ->whereDate('snapshot_date', '<=', $cut)
                        ->orderBy('snapshot_date')
                        ->get(['url_id', 'visibility_score']) as $snap) {
                $latestPerUrl[$snap->url_id] = (float) $snap->visibility_score;
            }
            $past = array_sum($latestPerUrl);
            if ($past > 0) {
                $visibilityDelta = (int) round($kpis['visibility'] - $past);
            }
        }

        // URLs-Tab in der Kunden-Sicht: „Deine Seiten" — nur eigene, reichhaltig
        // (Frische via Accessor, Sichtbarkeits-Δ, offene Empfehlungen je Seite).
        $urlsRich = collect();
        if ($this->tab === 'urls' && ! in_array($this->mode, ['unassigned', 'source'], true) && ! empty($ownUrlIds)) {
            $recCounts = SeoSignal::whereIn('url_id', $ownUrlIds)
                ->where('signal_type', 'like', 'rec\_%')
                ->whereIn('status', ['new', 'acknowledged'])
                ->selectRaw('url_id, COUNT(*) as c')
                ->groupBy('url_id')->pluck('c', 'url_id');

            $cut = now()->subDays(30)->toDateString();
            $pastByUrl = [];
            foreach (\Platform\Seo\Models\SeoUrlSnapshot::whereIn('url_id', $ownUrlIds)
                        ->whereDate('snapshot_date', '<=', $cut)
                        ->orderBy('snapshot_date')
                        ->get(['url_id', 'visibility_score']) as $s) {
                $pastByUrl[$s->url_id] = (float) $s->visibility_score;
            }

            $urlsRich = $own->map(function (SeoUrl $u) use ($recCounts, $pastByUrl) {
                $u->open_recs = (int) ($recCounts[$u->id] ?? 0);
                $u->vis_delta = isset($pastByUrl[$u->id]) ? (int) round((float) $u->visibility_score - $pastByUrl[$u->id]) : null;

                return $u;
            })->values();
        }

        if ($this->tab === 'competitors' && ! empty($ownUrlIds)) {
            $competitors = SeoUrl::where('team_id', $teamId)
                ->where('is_own', false)
                ->where('status', 'active')
                ->whereHas('keywords', fn ($q) => $q->whereHas('urls', fn ($q2) => $q2->whereIn('seo_url_keywords.url_id', $ownUrlIds)))
                ->selectRaw('domain, COUNT(*) as url_count, AVG(visibility_score) as avg_visibility, SUM(keyword_count) as total_keywords')
                ->groupBy('domain')
                ->orderByDesc('avg_visibility')
                ->limit(30)
                ->get();
        }

        if ($this->tab === 'recommendations' && ! empty($ownUrlIds)) {
            $recommendations = SeoSignal::whereIn('url_id', $ownUrlIds)
                ->where('signal_type', 'like', 'rec\_%')
                ->where('status', '!=', 'resolved')
                ->with('url:id,url,domain,path')
                ->orderByDesc('detected_at')
                ->limit(50)
                ->get();
        }

        if ($this->tab === 'clusters' && ! empty($ownUrlIds)) {
            $clusterIds = DB::table('seo_url_keywords as uk')
                ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
                ->whereIn('uk.url_id', $ownUrlIds)
                ->whereNotNull('k.cluster_id')
                ->distinct()->pluck('k.cluster_id')->all();
            if (! empty($clusterIds)) {
                $clusters = SeoKeywordCluster::whereIn('id', $clusterIds)->orderByDesc('visibility')->get();
            }
        }

        // Bewegung-Linse: Positions-Deltas der Keywords (aus dem url-keyword-Pivot,
        // position vs. previous_position). Gewinner ▲ / Verlierer ▼.
        $moverGainers = collect();
        $moverLosers = collect();
        if ($this->tab === 'movers' && ! empty($ownUrlIds)) {
            $rows = DB::table('seo_url_keywords as uk')
                ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
                ->whereIn('uk.url_id', $ownUrlIds)
                ->whereNotNull('uk.position')
                ->whereNotNull('uk.previous_position')
                ->whereColumn('uk.position', '<>', 'uk.previous_position')
                ->selectRaw('k.keyword, uk.position, uk.previous_position, (uk.previous_position - uk.position) as delta')
                ->get()
                ->groupBy('keyword')
                ->map(fn ($g) => $g->sortByDesc(fn ($r) => abs($r->delta))->first())
                ->values();

            $moverGainers = $rows->where('delta', '>', 0)->sortByDesc('delta')->take(20)->values();
            $moverLosers = $rows->where('delta', '<', 0)->sortBy('delta')->take(20)->values();
        }

        return view('seo::livewire.seo-perspective', [
            'urls' => $urls,
            'kpis' => $kpis,
            'relations' => $relations,
            'subPerspectives' => $subPerspectives,
            'customerCount' => $customerCount,
            'availableNodes' => $linker->availableNodes($teamId),
            'competitors' => $competitors,
            'recommendations' => $recommendations,
            'clusters' => $clusters,
            'topRecommendations' => $topRecommendations,
            'openRecCount' => $openRecCount,
            'visibilityDelta' => $visibilityDelta,
            'topOwnUrls' => $own->take(6)->values(),
            'topCompetitors' => $urls->where('is_own', false)->take(6)->values(),
            'urlsRich' => $urlsRich,
            'moverGainers' => $moverGainers,
            'moverLosers' => $moverLosers,
        ])->layout('platform::layouts.app');
    }

    /** Direkte Kind-Knoten-IDs eines Knotens. */
    protected function childEntityIds(int $entityId): array
    {
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (! class_exists($class)) {
            return [];
        }
        try {
            return $class::where('parent_entity_id', $entityId)->pluck('id')->map(fn ($i) => (int) $i)->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Baut aus einer Menge Entitäten die Unter-Perspektiven (Name + URL-Anzahl im Teilbaum).
     *
     * @return array<int,array{id:int,name:?string,url_count:int}>
     */
    protected function entityPerspectives(array $entityIds, SeoOrganizationLinker $linker): array
    {
        if (empty($entityIds)) {
            return [];
        }

        $names = [];
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (class_exists($class)) {
            try {
                foreach ($class::whereIn('id', $entityIds)->get(['id', 'name']) as $e) {
                    $names[(int) $e->id] = $e->name;
                }
            } catch (\Throwable $e) {
                // Organization nicht geladen
            }
        }

        $out = [];
        foreach ($entityIds as $eid) {
            $ids = $linker->descendantEntityIds((int) $eid);
            $count = count($linker->linkableIdsForNodes(SeoOrganizationLinker::ALIAS_URL, $ids));
            if ($count > 0) {
                $out[] = ['id' => (int) $eid, 'name' => $names[(int) $eid] ?? null, 'url_count' => $count];
            }
        }

        return $out;
    }

    /** Agentur-URLs ohne Modul-Herkunft, die an keinem Knoten hängen. */
    protected function unassignedUrlIds(int $teamId, SeoOrganizationLinker $linker): array
    {
        $ownIds = SeoUrl::where('team_id', $teamId)
            ->where('status', 'active')
            ->where('is_own', true)
            ->pluck('id')->map(fn ($i) => (int) $i)->all();

        if (empty($ownIds)) {
            return [];
        }

        $moduleOwned = SeoUrlRegistration::whereIn('url_id', $ownIds)
            ->where('source_module', '!=', 'seo')
            ->pluck('url_id')->map(fn ($i) => (int) $i)->unique()->all();

        $linked = $linker->linkedLinkableIds(SeoOrganizationLinker::ALIAS_URL, $ownIds);

        $exclude = array_flip(array_merge($moduleOwned, $linked));

        return array_values(array_filter($ownIds, fn ($id) => ! isset($exclude[$id])));
    }
}
