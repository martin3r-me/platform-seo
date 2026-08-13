<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoClusteringService;

/**
 * Hintergrund-Discovery: kapselt das SERP-Clustering (1 Live-Call je Keyword),
 * damit es aus der UI angestoßen werden kann, ohne ein Request-Timeout zu reißen.
 * Der Status läuft über SeoTeamSettings.clustering_status (running/completed/failed).
 */
class DiscoverClustersJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public int $urlId,
        public int $minOverlap = 3,
    ) {}

    public function handle(SeoClusteringService $service): void
    {
        $service->autoClusterForUrl($this->urlId, $this->minOverlap);
    }

    public function failed(\Throwable $e): void
    {
        SeoUrl::find($this->urlId)?->markClustering('failed', ['error' => $e->getMessage()]);
    }
}
