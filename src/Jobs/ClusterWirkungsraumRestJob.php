<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Models\SeoWirkungsraum;
use Platform\Seo\Services\SeoClusteringService;

/**
 * Hintergrund-Nach-Clustern eines Wirkungsraums: bündelt den ungeclusterten
 * Rest (wild rankende Keywords der Mitglieds-URLs) zu Themen — 1 SERP-Call je
 * Keyword, deshalb asynchron. Status läuft über SeoWirkungsraum.clustering_status.
 */
class ClusterWirkungsraumRestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(
        public int $wirkungsraumId,
        public int $minOverlap = 3,
        public ?int $minVolume = null,
    ) {}

    public function handle(SeoClusteringService $service): void
    {
        $service->autoClusterForWirkungsraum($this->wirkungsraumId, $this->minOverlap, $this->minVolume);
    }

    public function failed(\Throwable $e): void
    {
        SeoWirkungsraum::find($this->wirkungsraumId)?->markClustering('failed', ['error' => $e->getMessage()]);
    }
}
