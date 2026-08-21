<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlSnapshot;

/**
 * Geteilte Property-Sicht eines Wirkungsraums — die Grunddaten, die ALLE
 * Stationen brauchen (Mitglieder, effektive URL-Menge inkl. eigener Unterseiten,
 * Property-Totals je Mitglied, Aggregat). Aus der Gott-Komponente herausgezogen,
 * damit die herausgelösten Stations-Komponenten sie teilen (kein Duplikat).
 */
class SeoPortfolioView
{
    /**
     * @return array{members: \Illuminate\Support\Collection, effectiveIds: array<int,int>, memberTotals: array<int,array>, agg: array{visibility: float, keywords: int, search_volume: int, urls: int}}
     */
    public function forPortfolio(SeoPortfolio $portfolio): array
    {
        $members = $portfolio->urls()->orderByDesc('visibility_score')->get();
        $memberIds = $members->pluck('id')->all();

        if (empty($memberIds)) {
            return ['members' => $members, 'effectiveIds' => [], 'memberTotals' => [],
                'agg' => ['visibility' => 0.0, 'keywords' => 0, 'search_volume' => 0, 'urls' => 0]];
        }

        // Eigene Unterseiten je Mitglied (parent_child, eine Ebene).
        $childRels = DB::table('seo_url_relationships as r')
            ->join('seo_urls as c', 'c.id', '=', 'r.target_url_id')
            ->whereIn('r.source_url_id', $memberIds)
            ->where('r.type', 'parent_child')
            ->where('c.is_own', true)
            ->get(['r.source_url_id', 'r.target_url_id']);

        $childrenByParent = $childRels->groupBy('source_url_id')
            ->map(fn ($g) => $g->pluck('target_url_id')->all());

        // Vereinigungsmenge, dedupliziert (keine Doppelzählung bei Überlapp).
        $effectiveIds = array_values(array_unique(array_merge(
            $memberIds, $childRels->pluck('target_url_id')->all()
        )));

        $metrics = SeoUrl::whereIn('id', $effectiveIds)
            ->get(['id', 'keyword_count', 'total_search_volume', 'visibility_score'])
            ->keyBy('id');

        // Property-Total je Mitglied (Mitglied + eigene Unterseiten).
        $memberTotals = [];
        foreach ($members as $m) {
            $ids = array_merge([$m->id], $childrenByParent->get($m->id, []));
            $kw = 0;
            $sv = 0;
            $vis = 0.0;
            foreach ($ids as $id) {
                $row = $metrics->get($id);
                if (! $row) {
                    continue;
                }
                $kw += (int) $row->keyword_count;
                $sv += (int) $row->total_search_volume;
                $vis += (float) $row->visibility_score;
            }
            $memberTotals[$m->id] = ['keywords' => $kw, 'search_volume' => $sv,
                'visibility' => $vis, 'subpages' => count($childrenByParent->get($m->id, []))];
        }

        // Nach Property-Sichtbarkeit sortieren (nicht nur Knoten).
        $members = $members->sortByDesc(fn ($m) => $memberTotals[$m->id]['visibility'] ?? 0)->values();

        $agg = [
            'visibility' => (float) $metrics->sum('visibility_score'),
            'keywords' => (int) $metrics->sum('keyword_count'),
            'search_volume' => (int) $metrics->sum('total_search_volume'),
            'urls' => count($memberIds),
        ];

        return compact('members', 'effectiveIds', 'memberTotals', 'agg');
    }

    /**
     * Sichtbarkeits-Verlauf (90 Tage) über eine URL-Menge — inkl. vorberechneter
     * Sparkline-Geometrie fürs Dashboard. Geteilt (aus der Gott-Komponente gezogen).
     */
    public function trendForUrlIds(array $memberIds): array
    {
        $empty = ['points' => [], 'count' => 0, 'since' => null, 'current' => null, 'delta' => null];
        if (empty($memberIds)) {
            return $empty;
        }

        $rows = SeoUrlSnapshot::whereIn('url_id', $memberIds)
            ->where('snapshot_date', '>=', now()->subDays(90))
            ->selectRaw('snapshot_date, SUM(visibility_score) as total_visibility')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->get();

        if ($rows->isEmpty()) {
            return $empty;
        }

        $points = $rows->map(fn ($r) => [
            'date' => \Illuminate\Support\Carbon::parse($r->snapshot_date)->format('Y-m-d'),
            'visibility' => (float) $r->total_visibility,
        ])->values()->all();

        $count = count($points);
        $current = $points[$count - 1]['visibility'];
        $first = $points[0]['visibility'];

        // Sparkline-Geometrie (Polyline + Flächen-Pfad) fürs Dashboard vorberechnen.
        $spark = null;
        if ($count >= 2) {
            $w = 240;
            $h = 40;
            $vals = array_column($points, 'visibility');
            $mn = min($vals);
            $rng = (max($vals) - $mn) ?: 1;
            $line = [];
            foreach ($points as $i => $p) {
                $x = round($i / ($count - 1) * $w, 1);
                $y = round($h - (($p['visibility'] - $mn) / $rng) * ($h - 4) - 2, 1);
                $line[] = $x.','.$y;
            }
            $poly = implode(' ', $line);
            $spark = ['w' => $w, 'h' => $h, 'line' => $poly, 'area' => '0,'.$h.' '.$poly.' '.$w.','.$h];
        }

        return [
            'points' => $points,
            'count' => $count,
            'since' => $points[0]['date'],
            'current' => $current,
            'delta' => $count >= 2 ? $current - $first : null,
            'spark' => $spark,
        ];
    }
}
