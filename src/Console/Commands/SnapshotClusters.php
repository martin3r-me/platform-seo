<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoClusterSnapshot;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Services\SeoClusterMetricsService;

/**
 * Misst alle Cluster und schreibt einen Tages-Snapshot (Trajektorie) plus die
 * denormalisierten Aktuell-Werte auf den Cluster. Reine DB-Aggregation, keine
 * API-Kosten — deshalb täglich planbar.
 */
class SnapshotClusters extends Command
{
    protected $signature = 'seo:snapshot-clusters
                            {--team= : Nur ein bestimmtes Team}';

    protected $description = 'Berechnet Cluster-KPIs (Abdeckung, Sichtbarkeit, Traffic, Health) als Tages-Snapshot';

    public function handle(SeoClusterMetricsService $metrics): int
    {
        $date = now()->toDateString();
        $count = 0;

        $query = SeoKeywordCluster::query();
        if ($teamId = $this->option('team')) {
            $query->where('team_id', $teamId);
        }

        $query->chunkById(100, function ($clusters) use ($metrics, $date, &$count) {
            foreach ($clusters as $cluster) {
                $kpi = $metrics->computeForCluster($cluster);

                SeoClusterSnapshot::updateOrCreate(
                    ['cluster_id' => $cluster->id, 'snapshot_date' => $date],
                    $kpi,
                );

                $cluster->update([
                    'keyword_count' => $kpi['keyword_count'],
                    'covered_keywords' => $kpi['covered_keywords'],
                    'coverage_pct' => $kpi['coverage_pct'],
                    'top3_count' => $kpi['top3_count'],
                    'top10_count' => $kpi['top10_count'],
                    'avg_position' => $kpi['avg_position'],
                    'visibility' => $kpi['visibility'],
                    'clicks_30d' => $kpi['clicks'],
                    'visitors_30d' => $kpi['visitors'],
                    'health_score' => $kpi['health_score'],
                    'measured_at' => now(),
                ]);

                $count++;
            }
        });

        $this->info("{$count} Cluster gemessen (Snapshot {$date}).");

        return self::SUCCESS;
    }
}
