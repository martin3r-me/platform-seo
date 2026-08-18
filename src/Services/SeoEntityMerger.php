<?php

namespace Platform\Seo\Services;

use Platform\Core\Services\EmbeddingProviderRegistry;
use Platform\Seo\Models\SeoAnswerPresence;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoEntity;

/**
 * Embedding-Merge (v2, docs/NORDSTERN-v2.md): führt semantisch gleiche Entitäten
 * zu EINER kanonischen zusammen — „Eventcatering Düsseldorf" (extrahiert, Angebot)
 * + Cluster „eventcatering düsseldorf" (Nachfrage) → eine Entität, die Nachfrage
 * UND Angebot UND Präsenz trägt. Das Fundament für wasserdichte Vorschläge und
 * kontinuierliches Clustern (ein Embedding-Raum für Historie + Neu-Entdeckung).
 */
class SeoEntityMerger
{
    /** Cosine-Schwelle: nur echte Fast-Duplikate zusammenführen. */
    private const THRESHOLD = 0.86;

    public function __construct(private EmbeddingProviderRegistry $providers) {}

    /**
     * @param  int[]  $entityIds
     * @return array{merged:int}
     */
    public function mergeForEntityIds(array $entityIds): array
    {
        $entities = SeoEntity::whereIn('id', $entityIds)->get()->values();
        if ($entities->count() < 2) {
            return ['merged' => 0];
        }

        $provider = $this->providers->getDefaultProvider();
        if (! $provider) {
            return ['merged' => 0];
        }
        $vectors = $provider->embed($entities->pluck('name')->map(fn ($n) => (string) $n)->all(), 'query');

        // Union-Find über paarweise Cosine ≥ Schwelle.
        $n = $entities->count();
        $parent = range(0, $n - 1);
        $find = function (int $i) use (&$parent, &$find): int {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }

            return $i;
        };
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if ($this->cosine($vectors[$i] ?? null, $vectors[$j] ?? null) >= self::THRESHOLD) {
                    $parent[$find($j)] = $find($i);
                }
            }
        }

        $groups = [];
        for ($i = 0; $i < $n; $i++) {
            $groups[$find($i)][] = $i;
        }

        $merged = 0;
        foreach ($groups as $idxs) {
            if (count($idxs) < 2) {
                continue;
            }
            $canonIdx = $this->pickCanonical($entities, $idxs);
            $canon = $entities[$canonIdx];

            foreach ($idxs as $mi) {
                if ($mi === $canonIdx) {
                    continue;
                }
                $dup = $entities[$mi];

                SeoAnswerUnit::where('entity_id', $dup->id)->update(['entity_id' => $canon->id]);
                SeoAnswerPresence::where('entity_id', $dup->id)->update(['entity_id' => $canon->id]);

                // Nachfrage/Angebot der kanonischen Entität anreichern, wo sie leer ist.
                $fill = [];
                if (! $canon->cluster_id && $dup->cluster_id) {
                    $fill['cluster_id'] = $dup->cluster_id;
                }
                if ((int) ($dup->search_volume ?? 0) > (int) ($canon->search_volume ?? 0)) {
                    $fill['search_volume'] = $dup->search_volume;
                }
                if (! $canon->entity_type && $dup->entity_type) {
                    $fill['entity_type'] = $dup->entity_type;
                }
                if (! empty($fill)) {
                    $canon->fill($fill)->save();
                }

                $dup->delete();
                $merged++;
            }
        }

        return ['merged' => $merged];
    }

    /** Kanonisch: bevorzugt Nachfrage-verankert (cluster_id), sonst niedrigste ID. */
    protected function pickCanonical($entities, array $idxs): int
    {
        $withCluster = array_values(array_filter($idxs, fn ($i) => $entities[$i]->cluster_id));
        $pool = ! empty($withCluster) ? $withCluster : $idxs;

        return collect($pool)->sortBy(fn ($i) => $entities[$i]->id)->first();
    }

    protected function cosine(?array $a, ?array $b): float
    {
        if (empty($a) || empty($b) || count($a) !== count($b)) {
            return 0.0;
        }
        $dot = 0.0;
        $na = 0.0;
        $nb = 0.0;
        foreach ($a as $k => $va) {
            $vb = $b[$k];
            $dot += $va * $vb;
            $na += $va * $va;
            $nb += $vb * $vb;
        }
        if ($na <= 0 || $nb <= 0) {
            return 0.0;
        }

        return $dot / (sqrt($na) * sqrt($nb));
    }
}
