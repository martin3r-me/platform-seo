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

    /** Ab so vielen Zimmern wird ein Quartier nach Firma in Sub-Quartiere gruppiert. */
    protected const SUBQUARTER_TRIGGER = 10;

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

    /** Fit je Keyword = Cosine zum Schwerpunkt seiner Nachbarschaft (Kern-Nähe). */
    protected array $fitScores = [];

    /** Für die Cluster-Verwandtschaft (A+.2): Roh-Vektoren + Cluster-Repräsentanten. */
    protected array $allVectors = [];

    protected array $vecPosById = [];

    /** cluster_id => Repräsentanten-Vektor (Name + Top-Keywords). */
    protected array $clusterVecs = [];

    /** cluster_id => SeoKeywordCluster (für Name-Anzeige). */
    protected array $clustersById = [];

    /** Nähe-Schwelle, ab der ein Zimmer als „nah an Cluster X" gilt. */
    protected const NEAR_CLUSTER_THRESHOLD = 0.60;

    /** domain => Repräsentanten-Vektor (Firmen-Feld im Verbund) für das Routing. */
    protected array $companyVecs = [];

    /** Schwelle, ab der ein Zimmer klar zu einer Firma „gehört". */
    protected const COMPANY_THRESHOLD = 0.48;

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
            ->whereNull('cluster_id') // Frontier: geordnete (geclusterte) Keywords sind raus — die Karte zeigt nur den ungeordneten Rest
            ->whereNull('retired_at') // abgestellte (Außenseiter/Rausch) ebenfalls raus
            ->when(self::VOLUME_FLOOR > 0, fn ($q) => $q->where('search_volume', '>=', self::VOLUME_FLOOR))
            ->orderByDesc('search_volume')
            ->limit(self::SCOPE_CAP + 1)
            ->get(['id', 'keyword', 'search_volume', 'cluster_id', 'keyword_difficulty', 'search_intent']);

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

        // --- Echtes Fit: Cosine jedes Keywords zum Schwerpunkt SEINER Nachbarschaft.
        // Peripherer Rausch (in ein Quartier eingekettet, aber fern vom Kern, z.B.
        // „blähen kirschen" im Catering-Feld) bekommt niedriges Fit → sinkt im Score.
        $this->fitScores = $this->computeFit($components, $vectors, $idsInOrder);

        // A+.2: Cluster-Verwandtschaft vorbereiten — Team-Cluster als Repräsentanten
        // einbetten, damit jedes Zimmer seine nächste Cluster-Nähe zeigt (integrieren vs. neu).
        $this->allVectors = $vectors;
        $this->vecPosById = array_flip($idsInOrder);
        $this->prepareClusterVectors($teamId, $provider);
        // A+.3: Firmen-Felder (Domains der Mitglieder) als Zentroide → Themen-Routing
        // im Verbund („kellner jobs" gehört zu Rheingedeck, nicht zu Broich-Catering).
        $this->prepareCompanyVectors($ownUrlIds, $provider);

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

            // Mega-Quartier (viele Zimmer) → Zimmer nach Firma in Sub-Quartiere
            // gruppieren = Verbund-Felder (Broich · Rheingedeck · Foodtruck …),
            // damit das Quartier scanbar wird statt „catering, 100 Zimmer".
            $subquarters = count($rooms) > self::SUBQUARTER_TRIGGER
                ? $this->groupRoomsByCompany($rooms)
                : [];

            $neighborhoods[] = array_merge([
                'label' => $members[0]['keyword'],
                'size' => count($members),
                'volume' => array_sum(array_column($members, 'volume')),
                'keywords' => array_slice($members, 0, 10), // Anzeige zeigt 8; size trägt den Rest
                'keyword_ids' => array_values($comp),        // volle Menge fürs SERP-Übernehmen
                'rooms' => $rooms,
                'subquarters' => $subquarters,
                'is_quarter' => ! empty($rooms),
                'near_cluster' => $this->nearestClusterFor($comp),
                'company' => $this->nearestCompanyFor($comp),
            ], $this->groupStats($members));
        }
        // Nach SCORE sortieren (Chance × Fit × Winnability): on-topic-gewinnbare
        // Nachfrage zuerst; Fremd-Rausch und Unrankbares sinken trotz hoher Chance.
        usort($neighborhoods, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

        $outliers = [];
        foreach ($idsInOrder as $id) {
            if (empty($adjacency[$id])) {
                // Auch Einzelgänger bekommen Firmen-Routing: passt der zu einer Firma
                // (→ zuordnen) oder zu keiner (→ abstellen)?
                $outliers[] = array_merge(
                    $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet),
                    ['company' => $this->nearestCompanyFor([$id])]
                );
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

        // Post-Pass: ein übergroßes Blatt ist meist ein Geo-/Modifier-Rest (z.B.
        // „catering hamburg/köln …"), den Semantik NICHT trennt (gleiche Wörter).
        // Regel-basiert nach dem diskriminierenden Token splitten → 1 Gruppe pro
        // Ort/Modifier. Präziser + gratis, genau wo SERP nur Einzelgänger liefert.
        $groups = [];
        foreach ($leaves as $c) {
            if (count($c) > self::MAX_ROOM) {
                $split = $this->tokenSplit($c, $byId);
                if (! empty($split)) {
                    foreach ($split as $tok => $ids) {
                        if (count($ids) >= 2) {
                            $groups[] = ['ids' => $ids, 'pattern' => $tok === '_rest' ? null : $tok];
                        }
                    }

                    continue;
                }
            }
            $groups[] = ['ids' => $c, 'pattern' => null];
        }

        $inLeaf = [];
        foreach ($groups as $g) {
            foreach ($g['ids'] as $id) {
                $inLeaf[$id] = true;
            }
        }

        $rooms = [];
        foreach ($groups as $g) {
            $c = $g['ids'];
            $m = array_map(fn ($id) => $this->row($byId[$id], $anchorScores[$id] ?? null, $ownSet), $c);
            usort($m, fn ($a, $b) => $b['volume'] <=> $a['volume']);
            $rooms[] = array_merge([
                'label' => $m[0]['keyword'],
                'size' => count($m),
                'volume' => array_sum(array_column($m, 'volume')),
                'keywords' => array_slice($m, 0, 10),
                'keyword_ids' => array_values($c),
                'is_rest' => false,
                'pattern' => $g['pattern'], // gesetzt = per Geo/Modifier-Regel entstanden
                'near_cluster' => $this->nearestClusterFor($c),
                'company' => $this->nearestCompanyFor($c),
            ], $this->groupStats($m));
        }
        usort($rooms, fn ($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));

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

    /**
     * Regel-basierter Split eines dichten Zimmers nach dem DISKRIMINIERENDEN Token:
     * gemeinsamer Stamm (Token in ≥ 50 % der Keywords) raus, dann je Keyword das
     * häufigste Nicht-Stamm-Token als Gruppen-Schlüssel → 1 Gruppe pro Ort/Modifier
     * („catering hamburg" → hamburg, „… köln" → köln). Greift nur, wenn ein Stamm
     * existiert UND ≥ 2 echte Gruppen entstehen — sonst []. Gazetteer-frei.
     *
     * @param  int[]  $memberIds
     * @return array<string, int[]>  Token => Keyword-IDs
     */
    protected function tokenSplit(array $memberIds, $byId): array
    {
        if (count($memberIds) < 4) {
            return [];
        }

        $tokensByKw = [];
        $freq = [];
        foreach ($memberIds as $id) {
            $kw = $byId[$id] ?? null;
            if (! $kw) {
                continue;
            }
            $tokens = $this->tokenize((string) $kw->keyword);
            $tokensByKw[$id] = $tokens;
            foreach (array_unique($tokens) as $t) {
                $freq[$t] = ($freq[$t] ?? 0) + 1;
            }
        }

        $n = count($tokensByKw);
        if ($n < 4) {
            return [];
        }

        // Gemeinsamer Stamm: Tokens in ≥ 50 % der Keywords.
        $common = [];
        foreach ($freq as $t => $c) {
            if ($c / $n >= 0.5) {
                $common[$t] = true;
            }
        }
        if (empty($common)) {
            return []; // kein gemeinsamer Stamm → kein Muster
        }

        // Diskriminator je Keyword = häufigstes Nicht-Stamm-Token.
        $groups = [];
        foreach ($memberIds as $id) {
            $best = null;
            $bestFreq = -1;
            foreach ($tokensByKw[$id] ?? [] as $t) {
                if (isset($common[$t])) {
                    continue;
                }
                $f = $freq[$t] ?? 0;
                if ($f > $bestFreq) {
                    $bestFreq = $f;
                    $best = $t;
                }
            }
            $groups[$best ?? '_rest'][] = $id;
        }

        // Mindestens 2 echte Gruppen (ohne _rest) nötig, sonst kein sinnvoller Split.
        $realCount = 0;
        foreach ($groups as $k => $g) {
            if ($k !== '_rest' && count($g) >= 2) {
                $realCount++;
            }
        }

        return $realCount >= 2 ? $groups : [];
    }

    /**
     * Tokenisiert ein Keyword (klein, ohne Satzzeichen, ohne kurze Stoppwörter).
     *
     * @return string[]
     */
    protected function tokenize(string $s): array
    {
        $parts = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($s), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = ['für', 'und', 'in', 'im', 'der', 'die', 'das', 'den', 'dem', 'mit', 'von', 'vom',
            'zu', 'zum', 'zur', 'am', 'an', 'auf', 'aus', 'bei', 'pro', 'je', 'ohne', 'oder', 'ein',
            'eine', 'einen', 'als', 'wie', 'was', 'wo', 'ist', 'de', 'the', 'and', 'for'];

        return array_values(array_filter($parts, fn ($t) => mb_strlen($t) >= 2 && ! in_array($t, $stop, true)));
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
            // Fit = Kern-Nähe (Cosine zum Nachbarschafts-Schwerpunkt) + Gewinnbarkeit + Intent.
            'fit' => $this->fitScores[$id] ?? null,
            'winnability' => $this->winnability($kw->keyword_difficulty ?? null),
            'intent' => $kw->search_intent ?? null,
        ];
    }

    /** Gewinnbarkeit aus Keyword-Difficulty: 0 (unmöglich) … 1 (leicht). null = unbekannt → neutral-optimistisch. */
    protected function winnability(?int $kd): float
    {
        if ($kd === null) {
            return 0.55;
        }

        return max(0.08, round(1 - $kd / 100, 3));
    }

    /**
     * Fit je Keyword = Cosine zum Schwerpunkt (Zentroid) seiner Nachbarschaft.
     * Nutzt die bereits berechneten Roh-Vektoren — kein zusätzlicher API-Call.
     *
     * @param  array<int, int[]>  $components
     * @param  array<int, ?array>  $vectors     positions-indexiert (wie $idsInOrder)
     * @param  int[]  $idsInOrder
     * @return array<int, float>  keyword_id => fit (0..1)
     */
    protected function computeFit(array $components, array $vectors, array $idsInOrder): array
    {
        $posById = array_flip($idsInOrder);
        $fit = [];

        foreach ($components as $comp) {
            if (count($comp) < 2) {
                continue;
            }

            // Zentroid der Mitglieder-Vektoren.
            $centroid = null;
            $memberVecs = [];
            foreach ($comp as $id) {
                $idx = $posById[$id] ?? null;
                $v = $idx !== null ? ($vectors[$idx] ?? null) : null;
                if (! is_array($v)) {
                    continue;
                }
                $memberVecs[$id] = $v;
                if ($centroid === null) {
                    $centroid = array_fill(0, count($v), 0.0);
                }
                foreach ($v as $d => $val) {
                    $centroid[$d] += $val;
                }
            }
            if ($centroid === null || empty($memberVecs)) {
                continue;
            }
            $n = count($memberVecs);
            foreach ($centroid as $d => $val) {
                $centroid[$d] = $val / $n;
            }
            $cNorm = $this->vecNorm($centroid);
            if ($cNorm <= 0.0) {
                continue;
            }

            foreach ($memberVecs as $id => $v) {
                $vNorm = $this->vecNorm($v);
                if ($vNorm <= 0.0) {
                    $fit[$id] = 0.0;
                    continue;
                }
                $dot = 0.0;
                foreach ($v as $d => $val) {
                    $dot += $val * ($centroid[$d] ?? 0.0);
                }
                $fit[$id] = round(max(0.0, $dot / ($vNorm * $cNorm)), 3);
            }
        }

        return $fit;
    }

    /**
     * Zimmer eines Mega-Quartiers nach Firma (company.domain) in Sub-Quartiere
     * gruppieren. Jedes Sub-Quartier trägt die Original-Zimmer-Indizes + Aggregate.
     *
     * @param  array<int, array>  $rooms
     * @return array<int, array{domain:string,room_indices:int[],count:int,size:int,potenzial:int,ist:int,gap:int,score:int}>
     */
    protected function groupRoomsByCompany(array $rooms): array
    {
        $groups = [];
        foreach ($rooms as $ri => $room) {
            $dom = $room['company']['domain'] ?? '—';
            if (! isset($groups[$dom])) {
                $groups[$dom] = ['domain' => $dom, 'room_indices' => [], 'size' => 0, 'potenzial' => 0, 'ist' => 0, 'score' => 0];
            }
            $groups[$dom]['room_indices'][] = $ri;
            $groups[$dom]['size'] += (int) ($room['size'] ?? 0);
            $groups[$dom]['potenzial'] += (int) ($room['potenzial'] ?? 0);
            $groups[$dom]['ist'] += (int) ($room['ist'] ?? 0);
            $groups[$dom]['score'] += (int) ($room['score'] ?? 0);
        }

        $out = array_values($groups);
        foreach ($out as &$g) {
            $g['count'] = count($g['room_indices']);
            $g['gap'] = max(0, $g['potenzial'] - $g['ist']);
        }
        unset($g);

        usort($out, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $out;
    }

    protected function vecNorm(array $v): float
    {
        $s = 0.0;
        foreach ($v as $val) {
            $s += $val * $val;
        }

        return sqrt($s);
    }

    /** Team-Cluster als Repräsentanten-Vektoren (Name + Top-Keywords) einbetten. */
    protected function prepareClusterVectors(int $teamId, $provider): void
    {
        $this->clusterVecs = [];
        $this->clustersById = [];

        $clusters = \Platform\Seo\Models\SeoKeywordCluster::where('team_id', $teamId)->get(['id', 'name']);
        if ($clusters->isEmpty()) {
            return;
        }
        $this->clustersById = $clusters->keyBy('id')->all();

        $texts = [];
        $ids = [];
        foreach ($clusters as $c) {
            $top = SeoKeyword::where('cluster_id', $c->id)
                ->orderByDesc('search_volume')->limit(8)->pluck('keyword')->all();
            $text = trim(((string) $c->name) . ' ' . implode(' ', $top));
            if ($text === '') {
                continue;
            }
            $texts[] = $text;
            $ids[] = (int) $c->id;
        }
        if (empty($texts)) {
            return;
        }

        $vecs = $this->embedBatched($provider, $texts);
        foreach ($ids as $i => $cid) {
            if (isset($vecs[$i]) && is_array($vecs[$i])) {
                $this->clusterVecs[$cid] = $vecs[$i];
            }
        }
    }

    /**
     * Nächstes bestehendes Cluster zu einer Keyword-Gruppe (Zentroid-Cosine).
     * → „integrieren in X" statt „neues Cluster", wenn nah genug.
     *
     * @param  int[]  $memberIds
     * @return array{id:int,name:string,sim:float}|null
     */
    protected function nearestClusterFor(array $memberIds): ?array
    {
        if (empty($this->clusterVecs) || empty($memberIds)) {
            return null;
        }

        $centroid = null;
        $n = 0;
        foreach ($memberIds as $id) {
            $idx = $this->vecPosById[$id] ?? null;
            $v = $idx !== null ? ($this->allVectors[$idx] ?? null) : null;
            if (! is_array($v)) {
                continue;
            }
            if ($centroid === null) {
                $centroid = array_fill(0, count($v), 0.0);
            }
            foreach ($v as $d => $val) {
                $centroid[$d] += $val;
            }
            $n++;
        }
        if ($centroid === null || $n === 0) {
            return null;
        }
        foreach ($centroid as $d => $val) {
            $centroid[$d] = $val / $n;
        }
        $cNorm = $this->vecNorm($centroid);
        if ($cNorm <= 0.0) {
            return null;
        }

        $bestCid = null;
        $bestSim = -1.0;
        foreach ($this->clusterVecs as $cid => $cv) {
            $cvNorm = $this->vecNorm($cv);
            if ($cvNorm <= 0.0) {
                continue;
            }
            $dot = 0.0;
            foreach ($cv as $d => $val) {
                $dot += $val * ($centroid[$d] ?? 0.0);
            }
            $sim = $dot / ($cvNorm * $cNorm);
            if ($sim > $bestSim) {
                $bestSim = $sim;
                $bestCid = $cid;
            }
        }

        if ($bestCid === null || $bestSim < self::NEAR_CLUSTER_THRESHOLD) {
            return null;
        }

        return [
            'id' => (int) $bestCid,
            'name' => (string) ($this->clustersById[$bestCid]->name ?? 'Cluster'),
            'sim' => round($bestSim, 3),
        ];
    }

    /** Firmen-Felder (Domains der Mitglieder) als Repräsentanten-Vektoren. */
    protected function prepareCompanyVectors(array $ownUrlIds, $provider): void
    {
        $this->companyVecs = [];
        if (empty($ownUrlIds)) {
            return;
        }

        $byDomain = SeoUrl::whereIn('id', $ownUrlIds)->get(['id', 'domain'])
            ->filter(fn ($u) => ! empty($u->domain))
            ->groupBy('domain');
        if ($byDomain->count() < 2) {
            return; // Routing lohnt nur im Verbund (≥ 2 Firmen)
        }

        $texts = [];
        $domains = [];
        foreach ($byDomain as $domain => $group) {
            $top = DB::table('seo_url_keywords as uk')
                ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
                ->whereIn('uk.url_id', $group->pluck('id')->all())
                ->orderByDesc('k.search_volume')
                ->limit(15)->pluck('k.keyword')->unique()->take(15)->all();
            $text = trim(((string) $domain) . ' ' . implode(' ', $top));
            if ($text === '') {
                continue;
            }
            $texts[] = $text;
            $domains[] = (string) $domain;
        }
        if (empty($texts)) {
            return;
        }

        $vecs = $this->embedBatched($provider, $texts);
        foreach ($domains as $i => $d) {
            if (isset($vecs[$i]) && is_array($vecs[$i])) {
                $this->companyVecs[$d] = $vecs[$i];
            }
        }
    }

    /**
     * Firma im Verbund, zu deren Feld eine Keyword-Gruppe am besten passt.
     *
     * @param  int[]  $memberIds
     * @return array{domain:string,sim:float}|null
     */
    protected function nearestCompanyFor(array $memberIds): ?array
    {
        if (empty($this->companyVecs) || empty($memberIds)) {
            return null;
        }

        $centroid = null;
        $n = 0;
        foreach ($memberIds as $id) {
            $idx = $this->vecPosById[$id] ?? null;
            $v = $idx !== null ? ($this->allVectors[$idx] ?? null) : null;
            if (! is_array($v)) {
                continue;
            }
            if ($centroid === null) {
                $centroid = array_fill(0, count($v), 0.0);
            }
            foreach ($v as $d => $val) {
                $centroid[$d] += $val;
            }
            $n++;
        }
        if ($centroid === null || $n === 0) {
            return null;
        }
        foreach ($centroid as $d => $val) {
            $centroid[$d] = $val / $n;
        }
        $cNorm = $this->vecNorm($centroid);
        if ($cNorm <= 0.0) {
            return null;
        }

        $bestDomain = null;
        $bestSim = -1.0;
        foreach ($this->companyVecs as $domain => $cv) {
            $cvNorm = $this->vecNorm($cv);
            if ($cvNorm <= 0.0) {
                continue;
            }
            $dot = 0.0;
            foreach ($cv as $d => $val) {
                $dot += $val * ($centroid[$d] ?? 0.0);
            }
            $sim = $dot / ($cvNorm * $cNorm);
            if ($sim > $bestSim) {
                $bestSim = $sim;
                $bestDomain = $domain;
            }
        }

        if ($bestDomain === null || $bestSim < self::COMPANY_THRESHOLD) {
            return null;
        }

        return ['domain' => (string) $bestDomain, 'sim' => round($bestSim, 3)];
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
        $gap = max(0, $potenzial - $ist);

        // Fit = Kern-Nähe (Ø Cosine zum Nachbarschafts-Schwerpunkt) — dämpft
        // peripheren Fremd-Rausch (blähen kirschen im Catering-Feld).
        $fits = array_values(array_filter(array_column($members, 'fit'), fn ($f) => $f !== null));
        $fit = ! empty($fits) ? round(array_sum($fits) / count($fits), 3) : 0.5;

        // Winnability = Ø Gewinnbarkeit (aus KD) — dämpft unrealistisch schweres Volumen.
        $winn = $total > 0 ? round(array_sum(array_column($members, 'winnability')) / $total, 3) : 0.5;

        // Intent = dominante Suchintention der Gruppe.
        $intents = array_values(array_filter(array_column($members, 'intent')));
        $intent = null;
        if (! empty($intents)) {
            $counts = array_count_values($intents);
            arsort($counts);
            $intent = (string) array_key_first($counts);
        }

        // Score = Chance × Fit × Winnability. On-Topic-Gewinnbares steigt,
        // volumenstarker Fremd-Rausch / Unrankbares sinkt trotz hoher Chance.
        $score = (int) round($gap * $fit * $winn);

        return [
            'comp_count' => $comp,
            'is_opportunity' => $total > 0 && ($comp / $total) >= 0.6,
            'potenzial' => $potenzial,
            'ist' => $ist,
            'gap' => $gap,
            'fit' => $fit,
            'winnability' => $winn,
            'intent' => $intent,
            'score' => $score,
        ];
    }
}
