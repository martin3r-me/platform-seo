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
        $anchorText = $this->buildAnchorText($portfolio);
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
                $adjacency[$id][$nid] = true;
                $adjacency[$nid][$id] = true; // ungerichtet
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
            $neighborhoods[] = [
                'label' => $members[0]['keyword'],
                'size' => count($members),
                'volume' => array_sum(array_column($members, 'volume')),
                'keywords' => $members,
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
    protected function buildAnchorText(SeoPortfolio $portfolio): string
    {
        $parts = array_filter([
            (string) $portfolio->name,
            (string) ($portfolio->description ?? ''),
        ]);

        $clusterNames = \Platform\Seo\Models\SeoKeywordCluster::where('team_id', $portfolio->team_id)
            ->whereNotNull('name')
            ->orderByDesc('id')
            ->limit(30)
            ->pluck('name')
            ->all();

        if (! empty($clusterNames)) {
            $parts[] = implode(', ', $clusterNames);
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
