<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoUrl;

/**
 * Gemeinsamer Kennzahlen-Kern für JEDEN Scope — URL(+Unterseiten), Portfolio,
 * Liste. Die Facetten (Durchdringung IST/SOLL, Ordnungsgrad, ungeclusterter
 * Rest, Wettbewerber) sind reine Funktionen einer URL-Menge; dieselbe Lesart
 * überall. Genau EINE Quelle, damit sich der Nutzer an eine Sprache gewöhnt und
 * nichts dreimal driftet. Haltung/Aktionen bleiben je Scope verschieden — hier
 * nur das Messen. Siehe docs/WIRKUNGSRAUM-CONCEPT.md (teilen, nicht duplizieren).
 */
class SeoScopeMetrics
{
    /**
     * Der volle Kennzahlen-Satz für eine URL-Menge.
     *
     * @param  int[]  $urlIds  Bereits aufgelöste, deduplizierte URL-IDs (Property-Ebene).
     */
    public function forUrlIds(int $teamId, array $urlIds): array
    {
        $urlIds = array_values(array_unique(array_filter($urlIds)));
        if (empty($urlIds)) {
            return [
                'headline' => ['visibility' => 0.0, 'keywords' => 0, 'search_volume' => 0, 'urls' => 0],
                'clusters' => collect(),
                'unclustered' => null,
                'coverage' => ['total' => 0, 'clustered' => 0, 'ranking' => 0, 'pct' => 0, 'unclustered_pct' => 0],
                'competitors' => collect(),
            ];
        }

        $pen = $this->penetration($urlIds);

        return [
            'headline' => $this->headline($urlIds),
            'clusters' => $pen['clusters'],
            'unclustered' => $pen['unclustered'],
            'coverage' => $pen['coverage'],
            'competitors' => $this->competitors($teamId, $urlIds),
        ];
    }

    /**
     * Headline-Summen (Property-Ebene). Sichtbarkeit/Suchvolumen additiv (je URL
     * eigener Beitrag), wie überall in der Codebase.
     *
     * @param  int[]  $urlIds
     */
    public function headline(array $urlIds): array
    {
        $m = SeoUrl::whereIn('id', $urlIds)
            ->selectRaw('COALESCE(SUM(visibility_score),0) as vis, COALESCE(SUM(keyword_count),0) as kw, COALESCE(SUM(total_search_volume),0) as sv')
            ->first();

        return [
            'visibility' => (float) ($m->vis ?? 0),
            'keywords' => (int) ($m->kw ?? 0),
            'search_volume' => (int) ($m->sv ?? 0),
            'urls' => count($urlIds),
        ];
    }

    /**
     * Durchdringung je Cluster: SOLL (Ziel-Keywords, an den URLs) vs. IST (davon
     * rankend = Pivot-Position gesetzt). Plus ungeclusterter Rest (wild rankend)
     * und Ordnungsgrad (Anteil Keywords in einem Cluster). Distinct je Keyword.
     *
     * @param  int[]  $urlIds
     * @return array{clusters: Collection, unclustered: ?array, coverage: array}
     */
    public function penetration(array $urlIds): array
    {
        if (empty($urlIds)) {
            return ['clusters' => collect(), 'unclustered' => null,
                'coverage' => ['total' => 0, 'clustered' => 0, 'ranking' => 0, 'pct' => 0, 'unclustered_pct' => 0]];
        }

        // Je Keyword: beste Position über die URLs (null = rankt nirgends = nur SOLL).
        $rows = DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->whereIn('uk.url_id', $urlIds)
            ->groupBy('k.id', 'k.cluster_id', 'k.search_volume')
            ->select('k.id', 'k.cluster_id', 'k.search_volume', DB::raw('MIN(uk.position) as best_position'))
            ->get();

        $groups = $rows->groupBy(fn ($r) => $r->cluster_id ?? 0);
        $build = fn ($kws) => [
            'soll' => $kws->count(),
            'ist' => $kws->filter(fn ($r) => $r->best_position !== null)->count(),
            'volume' => (int) $kws->sum('search_volume'),
        ];

        $unclusteredKws = $groups->get(0);
        $unclustered = $unclusteredKws ? $build($unclusteredKws) : null;

        $clusterIds = $groups->keys()->filter(fn ($k) => $k > 0);
        $names = SeoKeywordCluster::whereIn('id', $clusterIds)->pluck('name', 'id');
        $origins = SeoKeywordCluster::whereIn('id', $clusterIds)->pluck('origin', 'id');

        $clusters = $groups->except([0])->map(function ($kws, $cid) use ($build, $names, $origins) {
            $b = $build($kws);
            $b['cluster_id'] = (int) $cid;
            $b['name'] = $names[(int) $cid] ?? ('#' . $cid);
            $b['origin'] = $origins[(int) $cid] ?? 'harvested';
            $b['pct'] = $b['soll'] > 0 ? (int) round($b['ist'] / $b['soll'] * 100) : 0;

            return $b;
        })->sortByDesc('volume')->values();

        // Ordnungsgrad: Anteil der Keywords, die einem Cluster zugeordnet sind.
        $total = $rows->count();
        $clusteredCount = $rows->filter(fn ($r) => $r->cluster_id !== null)->count();
        $rankingCount = $rows->filter(fn ($r) => $r->best_position !== null)->count();
        $coverage = [
            'total' => $total,
            'clustered' => $clusteredCount,
            'ranking' => $rankingCount,
            'pct' => $total > 0 ? (int) round($clusteredCount / $total * 100) : 0,
            'unclustered_pct' => $total > 0 ? (int) round(($total - $clusteredCount) / $total * 100) : 0,
        ];

        return ['clusters' => $clusters, 'unclustered' => $unclustered, 'coverage' => $coverage];
    }

    /**
     * Wettbewerber-Benchmark: fremde Domains, die um dieselben Keywords ranken
     * (Überlapp mit den Scope-Keywords) + ihre Stärke. Der Markt drumherum.
     *
     * @param  int[]  $urlIds
     */
    public function competitors(int $teamId, array $urlIds): Collection
    {
        if (empty($urlIds)) {
            return collect();
        }

        return DB::table('seo_url_keywords as uk')
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->join('seo_url_keywords as cuk', 'cuk.keyword_id', '=', 'k.id')
            ->join('seo_urls as cu', 'cu.id', '=', 'cuk.url_id')
            ->whereIn('uk.url_id', $urlIds)
            ->where('cu.is_own', false)
            ->where('cu.team_id', $teamId)
            ->groupBy('cu.domain')
            ->select('cu.domain', DB::raw('COUNT(DISTINCT k.id) as shared_keywords'), DB::raw('MAX(cu.visibility_score) as visibility'))
            ->orderByDesc('shared_keywords')
            ->limit(12)
            ->get();
    }
}
