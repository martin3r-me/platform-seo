<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoPortfolio;

/**
 * Baut den {nodes, links}-Graphen für den 3D-Kosmos eines Wirkungsraums.
 *
 * Kugel = ein Zimmer/Cluster (Thema). Größe = Potenzial. Leuchten/Ring =
 * Wirkungsgrad (IST ÷ Potenzial = Zielerreichung). Farbe/Rand = Land-Typ
 * (besetzt/Weißraum/Grau). Kanten = Bedeutungsnähe (Zimmer eines Quartiers).
 *
 * Liest die bereits gebaute semantic_map des Portfolios (kein Embedding-Call).
 * Adoptierte Cluster leuchten hell (besetzt), Kandidaten-Zimmer sind dimmer —
 * „übernehmen" zündet einen Kandidatenstern zum echten Cluster.
 */
class SeoClusterGraphBuilder
{
    protected const MAX_NODES = 300;

    public function build(SeoPortfolio $portfolio): array
    {
        $map = $portfolio->semantic_map;
        if (! is_array($map) || empty($map['neighborhoods'])) {
            return [
                'nodes' => [],
                'links' => [],
                'meta' => ['empty' => true, 'reason' => 'Noch keine semantische Karte — erst „Karte bauen".'],
            ];
        }

        // Adoptierte Cluster: keyword_id → cluster_id (markiert „besetzte" Sterne).
        $clusters = SeoKeywordCluster::where('team_id', $portfolio->team_id)->get()->keyBy('id');
        $clusterByKeyword = [];
        SeoKeyword::whereNotNull('cluster_id')
            ->where('team_id', $portfolio->team_id)
            ->get(['id', 'cluster_id'])
            ->each(function ($k) use (&$clusterByKeyword) {
                $clusterByKeyword[(int) $k->id] = (int) $k->cluster_id;
            });

        $nodes = [];
        $links = [];

        foreach ($map['neighborhoods'] as $ni => $nb) {
            // Quartier → seine Zimmer; kleine Nachbarschaft → sie selbst als ein Knoten.
            $rooms = ! empty($nb['rooms']) ? $nb['rooms'] : [$nb];
            $hubId = null;

            foreach ($rooms as $ri => $room) {
                if (count($nodes) >= self::MAX_NODES) {
                    break 2;
                }
                $id = 'n'.$ni.'r'.$ri;
                $node = $this->roomNode($id, $room, $clusterByKeyword, $clusters);
                $node['quarter'] = (string) ($nb['label'] ?? '');
                $node['region'] = $ni; // Quartier-Index → Region-Farbe im Kosmos
                $nodes[] = $node;

                if ($hubId === null) {
                    $hubId = $id;
                } elseif (! empty($nb['rooms'])) {
                    // Zimmer eines Quartiers verbinden → sie ballen sich im Kosmos.
                    $links[] = ['source' => $hubId, 'target' => $id, 'w' => 1];
                }
            }
        }

        // Beitragende eigene URLs je Node (ein Batch-Query über alle Keyword-IDs).
        $allKwIds = [];
        foreach ($nodes as $n) {
            foreach ($n['keyword_ids'] as $kid) {
                $allKwIds[$kid] = true;
            }
        }
        if (! empty($allKwIds)) {
            $byKw = [];
            DB::table('seo_url_keywords as uk')
                ->join('seo_urls as u', 'u.id', '=', 'uk.url_id')
                ->whereIn('uk.keyword_id', array_keys($allKwIds))
                ->where('u.is_own', true)
                ->where('u.team_id', $portfolio->team_id)
                ->whereNotNull('uk.position')
                ->select('uk.keyword_id', 'u.domain', 'u.path')
                ->get()
                ->each(function ($r) use (&$byKw) {
                    $byKw[$r->keyword_id][] = ($r->domain ?? '').($r->path ?: '/');
                });

            foreach ($nodes as &$n) {
                $paths = [];
                foreach ($n['keyword_ids'] as $kid) {
                    foreach ($byKw[$kid] ?? [] as $p) {
                        $paths[$p] = ($paths[$p] ?? 0) + 1;
                    }
                }
                arsort($paths);
                $n['urls'] = array_slice(array_keys($paths), 0, 6);
            }
            unset($n);
        }

        $regions = count(array_unique(array_column($nodes, 'region')));

        return [
            'nodes' => $nodes,
            'links' => $links,
            'meta' => [
                'empty' => false,
                'source' => $map['source'] ?? 'own',
                'built_at' => $map['built_at'] ?? null,
                'regions' => $regions,
                'counts' => [
                    'nodes' => count($nodes),
                    'own' => count(array_filter($nodes, fn ($n) => $n['landtype'] === 'own')),
                    'white' => count(array_filter($nodes, fn ($n) => $n['landtype'] === 'white')),
                    'grau' => count(array_filter($nodes, fn ($n) => $n['landtype'] === 'grau')),
                    'adopted' => count(array_filter($nodes, fn ($n) => $n['adopted'])),
                ],
            ],
        ];
    }

    protected function roomNode(string $id, array $room, array $clusterByKeyword, $clusters): array
    {
        $potenzial = (int) ($room['potenzial'] ?? 0);
        $ist = (int) ($room['ist'] ?? 0);
        $isOpp = ! empty($room['is_opportunity']); // ≥60% Wettbewerber = das Grau
        $wirkungsgrad = $potenzial > 0 ? min(1.0, $ist / $potenzial) : 0.0;

        $kwIds = $room['keyword_ids'] ?? [];
        $clusterId = $this->dominantCluster($kwIds, $clusterByKeyword);
        $adopted = $clusterId !== null;

        // Land-Typ: adoptiert → besetzt (hell); sonst Grau (Wettbewerber) /
        // Weißraum (eigen, ungehoben) / besetzt (eigen, schon eingelöst).
        $landtype = $adopted
            ? 'own'
            : ($isOpp ? 'grau' : ($wirkungsgrad >= 0.3 ? 'own' : 'white'));

        $cluster = $clusterId !== null ? $clusters->get($clusterId) : null;

        return [
            'id' => $id,
            'name' => (string) ($room['label'] ?? '—'),
            'val' => max(1, $potenzial),
            'landtype' => $landtype,
            'wirkungsgrad' => round($wirkungsgrad, 3),
            'potenzial' => $potenzial,
            'ist' => $ist,
            'gap' => (int) ($room['gap'] ?? max(0, $potenzial - $ist)),
            'kw' => (int) ($room['size'] ?? count($kwIds)),
            'adopted' => $adopted,
            'cluster_id' => $clusterId,
            'pillar_url_id' => $cluster?->pillar_url_id,
            'keyword_ids' => array_values(array_slice($kwIds, 0, 200)),
            'kw_sample' => array_values(array_slice(array_map(
                fn ($r) => (string) ($r['keyword'] ?? ''),
                $room['keywords'] ?? []
            ), 0, 10)),
        ];
    }

    /** Der Cluster, der ≥50% der Keywords hält, sonst null (= noch Kandidat). */
    protected function dominantCluster(array $kwIds, array $clusterByKeyword): ?int
    {
        if (empty($kwIds)) {
            return null;
        }
        $counts = [];
        foreach ($kwIds as $kid) {
            $cid = $clusterByKeyword[(int) $kid] ?? null;
            if ($cid !== null) {
                $counts[$cid] = ($counts[$cid] ?? 0) + 1;
            }
        }
        if (empty($counts)) {
            return null;
        }
        arsort($counts);
        $topCid = (int) array_key_first($counts);

        return $counts[$topCid] >= max(1, (int) ceil(count($kwIds) * 0.5)) ? $topCid : null;
    }
}
