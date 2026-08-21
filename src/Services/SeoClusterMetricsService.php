<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoKeywordCluster;

/**
 * Erfolgsmessung je Cluster (P2).
 *
 * Verdichtet die konsolidierten Per-URL-Daten (Rankings, GSC, Plausible) auf die
 * strategische Einheit Cluster: Abdeckung, Top-N, Sichtbarkeit, Traffic, Health.
 * Die Werte werden als Zeitreihe (seo_cluster_snapshots) und denormalisiert auf
 * dem Cluster gespeichert — die Trajektorie ist der eigentliche Erfolgsmaßstab.
 */
class SeoClusterMetricsService
{
    /**
     * Berechnet die KPI eines Clusters aus dem aktuellen Datenbestand.
     *
     * @return array{keyword_count:int,covered_keywords:int,coverage_pct:float,top3_count:int,top10_count:int,avg_position:?float,visibility:float,clicks:int,visitors:int,health_score:?int}
     */
    public function computeForCluster(SeoKeywordCluster $cluster): array
    {
        // Beste eigene Position je Keyword im Cluster (nur eigene, aktive URLs).
        $rows = DB::table('seo_keywords as k')
            ->leftJoin('seo_url_keywords as uk', 'uk.keyword_id', '=', 'k.id')
            ->leftJoin('seo_urls as u', function ($join) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at');
            })
            ->where('k.cluster_id', $cluster->id)
            ->groupBy('k.id', 'k.search_volume')
            ->select('k.id', 'k.search_volume', DB::raw(
                'MIN(CASE WHEN u.id IS NOT NULL AND uk.position IS NOT NULL THEN uk.position END) as best_position'
            ))
            ->get();

        $total = $rows->count();
        $covered = 0;
        $top3 = 0;
        $top10 = 0;
        $positionSum = 0;
        $visibility = 0.0;

        foreach ($rows as $row) {
            $pos = $row->best_position !== null ? (int) $row->best_position : null;
            if ($pos === null || $pos < 1) {
                continue;
            }
            $covered++;
            $positionSum += $pos;
            if ($pos <= 3) {
                $top3++;
            }
            if ($pos <= 10) {
                $top10++;
            }
            $visibility += ((int) ($row->search_volume ?? 0)) * $this->ctr($pos);
        }

        $coveragePct = $total > 0 ? round($covered / $total * 100, 2) : 0.0;
        $avgPosition = $covered > 0 ? round($positionSum / $covered, 2) : null;

        // Traffic der rankenden eigenen Cluster-URLs.
        $rankingUrlIds = DB::table('seo_url_keywords as uk')
            ->join('seo_urls as u', function ($join) {
                $join->on('u.id', '=', 'uk.url_id')
                    ->where('u.is_own', true)
                    ->whereNull('u.deleted_at');
            })
            ->join('seo_keywords as k', 'k.id', '=', 'uk.keyword_id')
            ->where('k.cluster_id', $cluster->id)
            ->whereNotNull('uk.position')
            ->distinct()
            ->pluck('u.id')
            ->all();

        $visitors = empty($rankingUrlIds)
            ? 0
            : (int) DB::table('seo_urls')->whereIn('id', $rankingUrlIds)->sum('visitors_30d');

        $clicks = (int) DB::table('seo_url_gsc_data as g')
            ->join('seo_keywords as k', 'k.id', '=', 'g.keyword_id')
            ->where('k.cluster_id', $cluster->id)
            ->where('g.date', '>=', now()->subDays(30)->toDateString())
            ->sum('g.clicks');

        // Durchdringung (Erfolgs-Quotient, 0–100) — s. docs/CLUSTER-PLAYBOOK.md §4.
        // Gewichteter Blend aus Coverage, Top-10- und Top-3-Anteil.
        $w = config('seo.clusters.penetration_weights', ['coverage' => 0.4, 'top10' => 0.4, 'top3' => 0.2]);
        $penetration = $total > 0
            ? (int) round(min(100, 100 * (
                ($w['coverage'] ?? 0.4) * ($covered / $total)
                + ($w['top10'] ?? 0.4) * ($top10 / $total)
                + ($w['top3'] ?? 0.2) * ($top3 / $total)
            )))
            : null;

        return [
            'keyword_count' => $total,
            'covered_keywords' => $covered,
            'coverage_pct' => $coveragePct,
            'top3_count' => $top3,
            'top10_count' => $top10,
            'avg_position' => $avgPosition,
            'visibility' => round($visibility, 4),
            'clicks' => $clicks,
            'visitors' => $visitors,
            'penetration' => $penetration,
            'health_score' => $penetration, // Rückwärtskompat.: health = Durchdringung
        ];
    }

    /**
     * Wendet die Lifecycle-Regeln (Playbook §3) auf einen frisch gemessenen
     * Cluster an. Automatisiert nur active↔monitored und active→stalled;
     * candidate/stalled/archived bleiben menschlicher Kuration vorbehalten.
     *
     * @return string|null  Neuer Status, falls ein Übergang stattfand.
     */
    public function applyLifecycle(SeoKeywordCluster $cluster): ?string
    {
        $cfg = config('seo.clusters', []);
        $status = $cluster->status;
        $pen = $cluster->penetration;

        if ($pen === null || ! in_array($status, [SeoKeywordCluster::STATUS_ACTIVE, SeoKeywordCluster::STATUS_MONITORED], true)) {
            return null;
        }

        // monitored → active (Rückfall)
        if ($status === SeoKeywordCluster::STATUS_MONITORED) {
            if ($pen < ($cfg['demote_to_active']['penetration'] ?? 60)) {
                $cluster->transitionTo(SeoKeywordCluster::STATUS_ACTIVE);

                return SeoKeywordCluster::STATUS_ACTIVE;
            }

            return null;
        }

        // active → monitored (gut durchdrungen, über N Snapshots gehalten)
        $promote = $cfg['promote_to_monitored'] ?? ['penetration' => 70, 'top10_share' => 50, 'hold_snapshots' => 2];
        $hold = max(1, (int) ($promote['hold_snapshots'] ?? 2));
        $recent = $cluster->snapshots()->take($hold)->get();
        if ($recent->count() >= $hold && $recent->every(fn ($s) => $s->penetration !== null
            && $s->penetration >= ($promote['penetration'] ?? 70)
            && $this->top10Share($s) >= ($promote['top10_share'] ?? 50))) {
            $cluster->transitionTo(SeoKeywordCluster::STATUS_MONITORED);

            return SeoKeywordCluster::STATUS_MONITORED;
        }

        // active → stalled (Stillstand über das Fenster)
        $weeks = (int) ($cfg['stalled_after_weeks'] ?? 8);
        $past = $cluster->snapshots()
            ->whereDate('snapshot_date', '<=', now()->subWeeks($weeks)->toDateString())
            ->first();
        if ($past && $past->penetration !== null
            && ($pen - $past->penetration) < ($cfg['stalled_min_gain'] ?? 5)) {
            $cluster->transitionTo(SeoKeywordCluster::STATUS_STALLED);

            return SeoKeywordCluster::STATUS_STALLED;
        }

        return null;
    }

    protected function top10Share($snapshot): float
    {
        $kc = (int) $snapshot->keyword_count;

        return $kc > 0 ? ((int) $snapshot->top10_count / $kc * 100) : 0.0;
    }

    /**
     * Grobe organische CTR-Kurve je Position (für die volumen-gewichtete Sichtbarkeit).
     * Bewusst modul-lokal; ein gemeinsamer CTR-Helfer ist ein Aufräum-Thema (Phase 4).
     * Public, damit die ehrliche (positionsgewichtete) Durchdringung im Cluster-
     * Index dieselbe Kurve nutzt.
     */
    public function ctr(int $position): float
    {
        return match (true) {
            $position <= 1 => 0.28,
            $position === 2 => 0.15,
            $position === 3 => 0.10,
            $position === 4 => 0.07,
            $position === 5 => 0.05,
            $position === 6 => 0.04,
            $position === 7 => 0.03,
            $position === 8 => 0.025,
            $position === 9 => 0.02,
            $position === 10 => 0.015,
            $position <= 20 => 0.01,
            $position <= 50 => 0.005,
            default => 0.002,
        };
    }
}
