<?php

namespace Platform\Seo\Services;

use Platform\Core\Services\EmbeddingProviderRegistry;
use Platform\Core\Services\EmbeddingStoreRegistry;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoPortfolio;

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
    protected const ROOM_TRIGGER = 40;

    /** Feinere Cosine-Schwelle INNERHALB eines Quartiers (Simulation, read-only). */
    protected const ROOM_THRESHOLD = 0.68;

    public function __construct(
        protected EmbeddingProviderRegistry $providers,
        protected EmbeddingStoreRegistry $stores,
    ) {}

    public function build(int $portfolioId): array
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

        $urlIds = $portfolio->effectiveUrlIds();
        if (empty($urlIds)) {
            return ['error' => 'Keine eigenen URLs im Wirkungsraum'];
        }

        // Ausschnitt (die Linse): Keywords der Wirkungsraum-URLs, volumenstark zuerst.
        $rows = SeoKeyword::where('team_id', $teamId)
            ->whereHas('urls', fn ($q) => $q->whereIn('seo_url_keywords.url_id', $urlIds))
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

        // --- Anker (Identität des Wirkungsraums) für die Themenferne-Sicht ---
        // NUR die Cluster DIESES Wirkungsraums (aus den cluster_ids seiner Keywords),
        // nicht alle Team-Cluster — sonst verwässert Fremdes (z.B. SOVRA) den Anker.
        $ownClusterIds = $rows->pluck('cluster_id')->filter()->unique()->values()->all();
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
            $members = array_map(fn ($id) => $this->row($byId[$id], $anchorScores[$id] ?? null), $comp);
            usort($members, fn ($a, $b) => $b['volume'] <=> $a['volume']);

            // Großes Quartier → in Zimmer auflösen (SIMULATION, read-only): dasselbe
            // Kanten-Set, nur bei feinerer Schwelle re-partitioniert. Keine Persistenz.
            $rooms = [];
            if (count($comp) > self::ROOM_TRIGGER) {
                $rooms = $this->rooms($comp, $adjacency, $byId, $anchorScores);
            }

            $neighborhoods[] = [
                'label' => $members[0]['keyword'],
                'size' => count($members),
                'volume' => array_sum(array_column($members, 'volume')),
                'keywords' => array_slice($members, 0, 10), // Anzeige zeigt 8; size trägt den Rest
                'rooms' => $rooms,
                'is_quarter' => ! empty($rooms),
            ];
        }
        usort($neighborhoods, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        $outliers = [];
        foreach ($idsInOrder as $id) {
            if (empty($adjacency[$id])) {
                $outliers[] = $this->row($byId[$id], $anchorScores[$id] ?? null);
            }
        }
        usort($outliers, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        // Themenfern: geringste Anker-Nähe zuerst (Ranking, kein harter Schnitt —
        // der Mensch kuratiert). Nur wenn ein Anker vorhanden war.
        $themefar = [];
        if (! empty($anchorScores)) {
            $themefar = $rows
                ->map(fn ($k) => $this->row($k, $anchorScores[$k->id] ?? 0.0))
                ->sortBy('anchor_score')
                ->take(self::LIST_CAP)
                ->values()
                ->all();
        }

        $grouped = array_sum(array_map(fn ($n) => $n['size'], $neighborhoods));

        return [
            'anchor' => $anchorText,
            'threshold' => self::NEIGHBOR_THRESHOLD,
            'truncated' => $truncated,
            'cap' => self::SCOPE_CAP,
            'stats' => [
                'total' => $rows->count(),
                'neighborhoods' => count($neighborhoods),
                'grouped' => $grouped,
                'outliers' => count($outliers),
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
     * Löst ein Quartier in Zimmer auf (SIMULATION): re-partitioniert dieselben
     * Keywords mit dem GLEICHEN Kanten-Set, aber nur Kanten ≥ ROOM_THRESHOLD. Kein
     * neuer API-Call, keine Persistenz. Gibt [] zurück, wenn es nicht echt splittet
     * (< 2 Zimmer) — dann bleibt das Quartier flach.
     *
     * @param  int[]  $memberIds
     * @param  array<int, array<int, float>>  $adjacency
     */
    protected function rooms(array $memberIds, array $adjacency, $byId, array $anchorScores): array
    {
        $memberSet = array_flip($memberIds);
        $sub = [];
        foreach ($memberIds as $id) {
            $sub[$id] = [];
        }
        foreach ($memberIds as $id) {
            foreach (($adjacency[$id] ?? []) as $nid => $score) {
                if (isset($memberSet[$nid]) && $score >= self::ROOM_THRESHOLD) {
                    $sub[$id][$nid] = true;
                }
            }
        }

        $rooms = [];
        $inRoom = [];
        foreach ($this->connectedComponents($sub) as $c) {
            if (count($c) < 2) {
                continue;
            }
            $m = array_map(fn ($id) => $this->row($byId[$id], $anchorScores[$id] ?? null), $c);
            usort($m, fn ($a, $b) => $b['volume'] <=> $a['volume']);
            foreach ($c as $id) {
                $inRoom[$id] = true;
            }
            $rooms[] = [
                'label' => $m[0]['keyword'],
                'size' => count($m),
                'volume' => array_sum(array_column($m, 'volume')),
                'keywords' => array_slice($m, 0, 10),
                'is_rest' => false,
            ];
        }

        // Kein echtes Auflösen (nur ein Zimmer) → Quartier flach lassen.
        if (count($rooms) < 2) {
            return [];
        }

        usort($rooms, fn ($a, $b) => $b['volume'] <=> $a['volume']);

        // Rest: Quartier-Keywords, die in kein enges Zimmer fielen.
        $rest = [];
        foreach ($memberIds as $id) {
            if (! isset($inRoom[$id])) {
                $rest[] = $this->row($byId[$id], $anchorScores[$id] ?? null);
            }
        }
        if (! empty($rest)) {
            usort($rest, fn ($a, $b) => $b['volume'] <=> $a['volume']);
            $rooms[] = [
                'label' => 'übrige (kein enges Zimmer)',
                'size' => count($rest),
                'volume' => array_sum(array_column($rest, 'volume')),
                'keywords' => array_slice($rest, 0, 10),
                'is_rest' => true,
            ];
        }

        return $rooms;
    }

    protected function row($kw, ?float $anchorScore): array
    {
        return [
            'id' => (int) $kw->id,
            'keyword' => (string) $kw->keyword,
            'volume' => (int) ($kw->search_volume ?? 0),
            'clustered' => $kw->cluster_id !== null,
            'anchor_score' => $anchorScore !== null ? round($anchorScore, 3) : null,
        ];
    }
}
