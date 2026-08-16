<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Services\EmbeddingProviderRegistry;
use Platform\Core\Services\EmbeddingStoreRegistry;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;

/**
 * Die „Wirkungsraum-Linse" auf den gemeinsamen Keyword-Vektorraum (Qdrant): liest
 * die schon vorhandenen Embeddings (Slice 1) und zeichnet das erste Sternbild —
 *
 *  - Nachbarschaften: Keywords, die sich semantisch nah sind (Cosine ≥ Schwelle),
 *    zu Themen-Gruppen verbunden (Kanten → Zusammenhangskomponenten).
 *  - Ausreißer: Keywords ohne Nachbarn im Ausschnitt (Quarantäne-Kandidaten).
 *  - Themenfern: Keywords mit geringster Nähe zum Wirkungsraum-Anker (Schrott-Sicht).
 *
 * Read-only, kein SERP, kein Content — nur sehen. Kosten: 1 gebündelter Embed-Aufruf
 * für den Anker + Ausschnitt (die Keyword-Vektoren liegen bereits in Qdrant).
 */
class SeoSemanticMapService
{
    /** Cosine-Schwelle, ab der zwei Keywords als „Nachbarn" gelten. */
    protected const NEIGHBOR_THRESHOLD = 0.55;

    /** Nachbarn, die je Keyword max. betrachtet werden. */
    protected const NEIGHBOR_LIMIT = 12;

    /** Ausschnitt begrenzen (Laufzeit/Kosten); die volumenstärksten zuerst. */
    protected const SCOPE_CAP = 1500;

    /** So viele Zeilen je Liste an die UI geben. */
    protected const LIST_CAP = 60;

    /** Ab dieser Größe gilt eine Nachbarschaft als „Quartier" und wird in Zimmer aufgelöst. */
    protected const ROOM_TRIGGER = 50;

    /** Start-Schwelle beim Auflösen eines Quartiers in Zimmer. */
    protected const ROOM_THRESHOLD = 0.68;

    /** Ziel: ein Zimmer ist seiten-groß. Größere Zimmer werden adaptiv weiter geteilt. */
    protected const MAX_ROOM = 50;

    /** Schrittweite, um die die Schwelle bei jedem Rekursionsschritt steigt. */
    protected const ROOM_STEP = 0.04;

    /** Decke: höher geht Semantik nicht sinnvoll — der dichte Rest ist der SERP-Fall. */
    protected const ROOM_CEILING = 0.86;

    /** Volumen-Boden: Keywords darunter (reiner Long-Tail) kommen gar nicht in die Karte;
     *  die Kopf-Seite deckt den Schwanz mit ab. 0 = kein Boden. */
    protected const VOLUME_FLOOR = 10;

    /** Angenommene CTR bei Top-Position — die Decke fürs „Potenzial". */
    protected const TOP_CTR = 0.30;

    /** keyword_id => unsere beste eigene Position (für IST je Keyword); je Build gesetzt. */
    protected array $ownPositions = [];

    public function __construct(
        protected EmbeddingProviderRegistry $providers,
        protected EmbeddingStoreRegistry $stores,
    ) {}

    public function build(int $portfolioId, bool $includeCompetitors = false): array
    {
        $portfolio = SeoPortfolio::find($portfolioId);
        if (! $portfolio) {
            return ['error' => 'Wirkungsraum nicht gefunden'];
        }

        $teamId = (int) $portfolio->team_id;
        $provider = $this->providers->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['error' => 'Kein Embedding-Provider verfügbar (API-Key prüfen).'];
        }
        $store = $this->stores->resolve(null, 'seo_keyword');
        $providerKey = $provider->getName();
        $modelKey = $provider->getModel();

        $ownUrlIds = $portfolio->effectiveUrlIds();
        if (empty($ownUrlIds)) {
            return ['error' => 'Keine eigenen URLs im Wirkungsraum'];
        }

        // --- Faden 1: eigene Keywords (was wir schon haben) ---
        $ownKeywordIds = DB::table('seo_url_keywords')->whereIn('url_id', $ownUrlIds)
            ->distinct()->pluck('keyword_id')->map('intval')->all();
        $ownSet = array_flip($ownKeywordIds);

        // --- Faden 2 (optional): Wettbewerber-Keywords (wozu ranken die = das Grau) ---
        // Wettbewerber DIESES Wirkungsraums (teilen Keywords mit uns) → deren volle
        // Keyword-Menge; die Teile, die NICHT in $ownSet sind, sind die Chance.
        $compKeywordIds = [];
        if ($includeCompetitors) {
            $compDomains = collect(
                app(SeoScopeMetrics::class)->forUrlIds($teamId, $ownUrlIds)['competitors'] ?? []
            )->pluck('domain')->filter()->unique()->all();

            if (! empty($compDomains)) {
                $compUrlIds = SeoUrl::where('team_id', $teamId)->where('is_own', false)
                    ->whereIn('domain', $compDomains)->pluck('id')->all();
                if (! empty($compUrlIds)) {
                    $compKeywordIds = DB::table('seo_url_keywords')->whereIn('url_id', $compUrlIds)
                        ->distinct()->pluck('keyword_id')->map('intval')->all();
                }
            }
        }

        $allKeywordIds = array_values(array_unique(array_merge($ownKeywordIds, $compKeywordIds)));

        // Ausschnitt (die Linse), volumenstark zuerst. Volumen-Boden: reiner Long-Tail
        // (unter VOLUME_FLOOR) kommt gar nicht rein — die Kopf-Seite deckt ihn mit ab.
        $rows = SeoKeyword::where('team_id', $teamId)
            ->whereIn('id', $allKeywordIds)
            ->when(self::VOLUME_FLOOR > 0, fn ($q) => $q->where('search_volume', '>=', self::VOLUME_FLOOR))
            ->orderByDesc('search_volume')
            ->limit(self::SCOPE_CAP + 1)
            ->get(['id', 'keyword', 'search_volume', 'cluster_id']);

        $truncated = $rows->count() > self::SCOPE_CAP;
        if ($truncated) {
            $rows = $rows->take(self::SCOPE_CAP);
        }
        if ($rows->count() < 2) {
            return ['error' => 'Zu wenige Keywords für eine semantische Karte'];
        }

        $byId = $rows->keyBy('id');
        $scope = $rows->pluck('id')->flip()->all(); // set: keyword_id => true

        // IST je Keyword: unsere beste eigene Position (min über die eigenen URLs).
        // Fehlt sie (Wettbewerber-KW / wir ranken nicht) → kein IST = reines Potenzial.
        $this->ownPositions = DB::table('seo_url_keywords')
            ->whereIn('keyword_id', array_keys($scope))
            ->whereIn('url_id', $ownUrlIds)
            ->whereNotNull('position')
            ->groupBy('keyword_id')
            ->selectRaw('keyword_id, MIN(position) as pos')
            ->pluck('pos', 'keyword_id')
            ->map(fn ($p) => (int) $p)
            ->all();

        // --- Anker (Identität des Wirkungsraums) für die Themenferne-Sicht ---
        // NUR die Cluster DIESES Wirkungsraums (aus den cluster_ids seiner Keywords),
        // nicht alle Team-Cluster — sonst verwässert Fremdes (z.B. SOVRA) den Anker.
        $ownClusterIds = $rows->filter(fn ($r) => isset($ownSet[(int) $r->id]))
            ->pluck('cluster_id')->filter()->unique()->values()->all();
        $ownClusterNames = empty($ownClusterIds) ? [] : \Platform\Seo\Models\SeoKeywordCluster::whereIn('id', $ownClusterIds)
            ->whereNotNull('name')->pluck('name')->all();
        $anchorText = $this->buildAnchorText($portfolio, $ownClusterNames);
        $anchorScores = [];
        if ($anchorText !== '') {
            $anchorVec = $provider->embed([$anchorText], 'query')[0] ?? null;
            if ($anchorVec !== null) {
                $hits = $store->search($teamId, $anchorVec, $providerKey, $modelKey, ['seo_keyword'], max(2000, $rows->count()), 0.0);
                foreach ($hits as $h) {
                    $anchorScores[(int) $h['entity_id']] = (float) $h['score'];
                }
            }
        }

        // --- Alle Ausschnitt-Keywords in EINEM gebündelten Aufruf embedden (query) ---
        $texts = $rows->map(fn ($k) => (string) $k->keyword)->all();
        $idsInOrder = $rows->pluck('id')->all();
        $vectors = $this->embedBatched($provider, $texts);

        // --- Nachbarschafts-Graph über Qdrant (indexierte Suche, kein N×N in PHP) ---
        $adjacency = [];
        foreach ($idsInOrder as $id) {
            $adjacency[$id] = [];
        }
        foreach ($idsInOrder as $i => $id) {
            $vec = $vectors[$i] ?? null;
            if ($vec === null) {
                continue;
            }
            $hits = $store->search($teamId, $vec, $providerKey, $modelKey, ['seo_keyword'], self::NEIGHBOR_LIMIT, self::NEIGHBOR_THRESHOLD);
            foreach ($hits as $h) {
                $nid = (int) $h['entity_id'];
                if ($nid === $id || ! isset($scope[$nid])) {
                    continue; // sich selbst und alles außerhalb der Linse ignorieren
                }
                // Kante mit Cosine-Score (max beider Richtungen) — der Score erlaubt
                // später das feinere Auflösen eines Quartiers in Zimmer, ohne neuen API-Call.
                $score = (float) ($h['score'] ?? 0.0);
                $adjacency[$id][$nid] = max($adjacency[$id][$nid] ?? 0.0, $score);
                $adjacency[$nid][$id] = max($adjacency[$nid][$id] ?? 0.0, $score);
            }
        }

        // --- Nachbarschaften = Zusammenhangskomponenten (≥2); Ausreißer = Grad 0 ---
        $components = $this->connectedComponents($adjacency);

        $neighborhoods = [];
        foreach ($components as $comp) {
            if (count($comp) < 2) {
                continue;
            }
            $members = array_map(fn ($id) => $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet), $comp);
            usort($members, fn ($a, $b) => $b['volume'] <=> $a['volume']);

            // Großes Quartier → in Zimmer auflösen (SIMULATION, read-only): dasselbe
            // Kanten-Set, nur bei feinerer Schwelle re-partitioniert. Keine Persistenz.
            $rooms = [];
            if (count($comp) > self::ROOM_TRIGGER) {
                $rooms = $this->rooms($comp, $adjacency, $byId, $anchorScores, $ownSet);
            }

            $neighborhoods[] = array_merge([
                'label' => $members[0]['keyword'],
                'size' => count($members),
                'volume' => array_sum(array_column($members, 'volume')),
                'keywords' => array_slice($members, 0, 10), // Anzeige zeigt 8; size trägt den Rest
                'keyword_ids' => array_values($comp),        // volle Menge fürs SERP-Übernehmen
                'rooms' => $rooms,
                'is_quarter' => ! empty($rooms),
            ], $this->groupStats($members));
        }
        // Nach OPPORTUNITY sortieren (größte ungehobene Nachfrage zuerst): Grau
        // (Wettbewerber, IST=0) und eigen-aber-schlecht-platziert steigen nach oben,
        // schon gewonnene Themen (kleiner Gap) sinken.
        usort($neighborhoods, fn ($a, $b) => ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0));

        $outliers = [];
        foreach ($idsInOrder as $id) {
            if (empty($adjacency[$id])) {
                $outliers[] = $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet);
            }
        }
        usort($outliers, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        // Themenfern: geringste Anker-Nähe zuerst (Ranking, kein harter Schnitt —
        // der Mensch kuratiert). Nur wenn ein Anker vorhanden war.
        $themefar = [];
        if (! empty($anchorScores)) {
            $themefar = $rows
                ->map(fn ($k) => $this->row($k, $anchorScores[$k->id] ?? 0.0, $ownSet))
                ->sortBy('anchor_score')
                ->take(self::LIST_CAP)
                ->values()
                ->all();
        }

        $grouped = array_sum(array_map(fn ($n) => $n['size'], $neighborhoods));
        $compTotal = $rows->reject(fn ($r) => isset($ownSet[(int) $r->id]))->count();

        return [
            'anchor' => $anchorText,
            'threshold' => self::NEIGHBOR_THRESHOLD,
            'truncated' => $truncated,
            'cap' => self::SCOPE_CAP,
            'source' => $includeCompetitors ? 'both' : 'own',
            'stats' => [
                'total' => $rows->count(),
                'competitors' => $compTotal, // Wettbewerber-Herkunft (das Grau)
                'neighborhoods' => count($neighborhoods),
                'grouped' => $grouped,
                'outliers' => count($outliers),
                'opportunities' => count(array_filter($neighborhoods, fn ($n) => ! empty($n['is_opportunity']))),
            ],
            'neighborhoods' => array_slice($neighborhoods, 0, 40),
            'outliers' => array_slice($outliers, 0, self::LIST_CAP),
            'themefar' => $themefar,
            'built_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Anker-Text aus der Identität des Wirkungsraums: Name + Beschreibung + die
     * Namen seiner bestehenden Cluster. Daran wird „themenfern" gemessen.
     */
    /**
     * Anker-Text = Identität des Wirkungsraums: Name + die Namen SEINER EIGENEN
     * Cluster (übergeben, nicht team-weit gezogen). Bewusst OHNE die Meta-
     * Beschreibung („Steuer-Scope …") — die ist Boilerplate, kein Thema.
     *
     * @param  string[]  $ownClusterNames
     */
    protected function buildAnchorText(SeoPortfolio $portfolio, array $ownClusterNames): string
    {
        $parts = array_filter([(string) $portfolio->name]);

        if (! empty($ownClusterNames)) {
            $parts[] = implode(', ', $ownClusterNames);
        }

        return trim(implode('. ', $parts));
    }

    /**
     * @param  string[]  $texts
     * @return array<int, float[]>  Vektoren in Eingabereihenfolge.
     */
    protected function embedBatched($provider, array $texts): array
    {
        $vectors = [];
        $batchSize = max(1, $provider->getMaxBatchSize());
        foreach (array_chunk($texts, $batchSize, true) as $chunk) {
            $embedded = $provider->embed(array_values($chunk), 'query');
            $i = 0;
            foreach (array_keys($chunk) as $origIndex) {
                $vectors[$origIndex] = $embedded[$i] ?? null;
                $i++;
            }
        }
        ksort($vectors);

        return array_values($vectors);
    }

    /**
     * @param  array<int, array<int, bool>>  $adjacency
     * @return array<int, int[]>
     */
    protected function connectedComponents(array $adjacency): array
    {
        $visited = [];
        $components = [];

        foreach (array_keys($adjacency) as $start) {
            if (isset($visited[$start])) {
                continue;
            }
            $component = [];
            $queue = [$start];
            $visited[$start] = true;

            while (! empty($queue)) {
                $current = array_shift($queue);
                $component[] = $current;
                foreach (array_keys($adjacency[$current] ?? []) as $neighbor) {
                    if (! isset($visited[$neighbor])) {
                        $visited[$neighbor] = true;
                        $queue[] = $neighbor;
                    }
                }
            }
            $components[] = $component;
        }

        return $components;
    }

    /**
     * Löst ein Quartier ADAPTIV in seiten-große Zimmer auf (SIMULATION, read-only):
     * teilt bei steigender Schwelle rekursiv, bis ein Zimmer ≤ MAX_ROOM ist oder die
     * Decke (ROOM_CEILING) erreicht ist — dann bleibt der dichte Rest EIN großes
     * Zimmer = der klare SERP-Fall. Nutzt nur die vorhandenen Cosine-Scores (gratis).
     * Gibt [] zurück, wenn es gar nicht echt splittet (Quartier bleibt flach).
     *
     * @param  int[]  $memberIds
     * @param  array<int, array<int, float>>  $adjacency
     */
    protected function rooms(array $memberIds, array $adjacency, $byId, array $anchorScores, array $ownSet): array
    {
        // Arbeits-Queue: (Mitglieder, aktuelle Schwelle). Ergebnis = Blatt-Gruppen.
        $leaves = [];
        $inLeaf = [];
        $queue = [[$memberIds, self::ROOM_THRESHOLD]];
        $guard = 0;

        while (! empty($queue) && $guard++ < 5000) {
            [$members, $thr] = array_shift($queue);

            // Seiten-groß genug ODER Decke erreicht → Blatt (dichter Rest = SERP-Fall).
            if (count($members) <= self::MAX_ROOM || $thr > self::ROOM_CEILING) {
                if (count($members) >= 2) {
                    $leaves[] = $members;
                    foreach ($members as $id) {
                        $inLeaf[$id] = true;
                    }
                }

                continue;
            }

            // Bei $thr teilen (Teilgraph nur mit Kanten ≥ $thr innerhalb der Menge).
            $memberSet = array_flip($members);
            $sub = [];
            foreach ($members as $id) {
                $sub[$id] = [];
            }
            foreach ($members as $id) {
                foreach (($adjacency[$id] ?? []) as $nid => $score) {
                    if (isset($memberSet[$nid]) && $score >= $thr) {
                        $sub[$id][$nid] = true;
                    }
                }
            }

            $big = array_values(array_filter($this->connectedComponents($sub), fn ($c) => count($c) >= 2));

            // Kein echtes Splitten (≤ 1 große Komponente) → Schwelle anheben, erneut.
            if (count($big) <= 1) {
                $queue[] = [$members, $thr + self::ROOM_STEP];

                continue;
            }

            // Fortschritt: jede große Komponente weiter prüfen (wird Blatt, sobald klein
            // genug); Singletons dieser Ebene fallen unten in den Rest.
            foreach ($big as $c) {
                $queue[] = [$c, $thr + self::ROOM_STEP];
            }
        }

        // Kein echtes Auflösen → Quartier flach lassen.
        if (count($leaves) < 2) {
            return [];
        }

        $rooms = [];
        foreach ($leaves as $c) {
            $m = array_map(fn ($id) => $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet), $c);
            usort($m, fn ($a, $b) => $b['volume'] <=> $a['volume']);
            $rooms[] = array_merge([
                'label' => $m[0]['keyword'],
                'size' => count($m),
                'volume' => array_sum(array_column($m, 'volume')),
                'keywords' => array_slice($m, 0, 10),
                'keyword_ids' => array_values($c),
                'is_rest' => false,
            ], $this->groupStats($m));
        }
        usort($rooms, fn ($a, $b) => ($b['gap'] ?? 0) <=> ($a['gap'] ?? 0));

        // Rest: Quartier-Keywords, die in kein Zimmer fielen (lose Ränder).
        $rest = [];
        $restIds = [];
        foreach ($memberIds as $id) {
            if (! isset($inLeaf[$id])) {
                $rest[] = $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet);
                $restIds[] = $id;
            }
        }
        if (! empty($rest)) {
            usort($rest, fn ($a, $b) => $b['volume'] <=> $a['volume']);
            $rooms[] = array_merge([
                'label' => 'übrige (kein enges Zimmer)',
                'size' => count($rest),
                'volume' => array_sum(array_column($rest, 'volume')),
                'keywords' => array_slice($rest, 0, 10),
                'keyword_ids' => array_values($restIds),
                'is_rest' => true,
            ], $this->groupStats($rest));
        }

        return $rooms;
    }

    protected function row($kw, ?float $anchorScore, array $ownSet): array
    {
        $id = (int) $kw->id;
        $volume = (int) ($kw->search_volume ?? 0);
        $position = $this->ownPositions[$id] ?? null; // unsere beste Position (null = ranken nicht)

        return [
            'id' => $id,
            'keyword' => (string) $kw->keyword,
            'volume' => $volume,
            'clustered' => $kw->cluster_id !== null,
            // Herkunft (Faden 1 vs 2): 'own' = wir ranken dafür; 'competitor' = nur
            // Wettbewerber ranken, wir nicht = das Grau/die Chance.
            'origin' => isset($ownSet[$id]) ? 'own' : 'competitor',
            'position' => $position,
            // IST: geschätzt erreichter Traffic bei aktueller Position (0 = ranken nicht).
            'reach' => (int) round($volume * $this->ctr($position)),
            'anchor_score' => $anchorScore !== null ? round($anchorScore, 3) : null,
        ];
    }

    /** Grobe CTR je Position (organisch) — für die IST-Schätzung. null/keine = 0. */
    protected function ctr(?int $pos): float
    {
        if ($pos === null || $pos < 1) {
            return 0.0;
        }

        $curve = [1 => 0.30, 2 => 0.15, 3 => 0.10, 4 => 0.07, 5 => 0.05,
            6 => 0.04, 7 => 0.03, 8 => 0.025, 9 => 0.02, 10 => 0.018];
        if (isset($curve[$pos])) {
            return $curve[$pos];
        }

        return match (true) {
            $pos <= 20 => 0.010,
            $pos <= 50 => 0.005,
            default => 0.002,
        };
    }

    /**
     * Kennzahlen einer Gruppe: Herkunft (Wettbewerber-Anteil → „Chance") UND
     * Potenzial vs IST — Potenzial = erreichbarer Traffic (bei Top-Position),
     * IST = aktuell geschätzt erreichter; Chance-Gap = Potenzial − IST. Danach
     * sortieren wir (größte ungehobene Nachfrage zuerst).
     *
     * @param  array<int, array>  $members
     * @return array{comp_count:int,is_opportunity:bool,potenzial:int,ist:int,gap:int}
     */
    protected function groupStats(array $members): array
    {
        $total = count($members);
        $comp = count(array_filter($members, fn ($m) => ($m['origin'] ?? 'own') === 'competitor'));

        $volume = array_sum(array_column($members, 'volume'));
        $ist = array_sum(array_column($members, 'reach'));
        $potenzial = (int) round($volume * self::TOP_CTR); // Ceiling: als würden wir top ranken

        return [
            'comp_count' => $comp,
            'is_opportunity' => $total > 0 && ($comp / $total) >= 0.6,
            'potenzial' => $potenzial,
            'ist' => $ist,
            'gap' => max(0, $potenzial - $ist),
        ];
    }
}
