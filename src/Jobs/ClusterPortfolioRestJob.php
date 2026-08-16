<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Services\SeoClusteringService;

/**
 * Hintergrund-Nach-Clustern eines Wirkungsraums: bündelt den ungeclusterten
 * Rest (wild rankende Keywords der Mitglieds-URLs) zu Themen — 1 SERP-Call je
 * Keyword, deshalb asynchron. Status läuft über SeoPortfolio.clustering_status.
 */
class ClusterPortfolioRestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public int $portfolioId,
        public int $minOverlap = 3,
        public ?int $minVolume = null,
    ) {}

    public function handle(SeoClusteringService $service): void
    {
        $service->autoClusterForPortfolio($this->portfolioId, $this->minOverlap, $this->minVolume);
    }

    public function failed(\Throwable $e): void
    {
        SeoPortfolio::find($this->portfolioId)?->markClustering('failed', ['error' => $e->getMessage()]);
    }
}
