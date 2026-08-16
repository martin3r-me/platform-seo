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

    public int $timeout = 1200;

    public int $tries = 1;

    public function __construct(
        public int $portfolioId,
        public int $minOverlap = 3,
        public ?int $minVolume = null,
    ) {}

    public function handle(SeoClusteringService $service): void
    {
        // Soft-Deadline weit unter dem harten $timeout: bis dahin SERP holen
        // (persistent gecacht), dann sauber aufhören. Bleibt was offen, setzt
        // sich der Job selbst fort — so kann kein einzelner Lauf im Timeout
        // hängen bleiben und dabei Geld verbrennen.
        $deadlineTs = time() + 600;

        $result = $service->autoClusterForPortfolio(
            $this->portfolioId, $this->minOverlap, $this->minVolume, $deadlineTs
        );

        // Noch nicht fertig (und kein Fehler) → Rest in einem Folgelauf holen.
        if (($result['complete'] ?? true) === false && empty($result['error'])) {
            self::dispatch($this->portfolioId, $this->minOverlap, $this->minVolume);
        }
    }

    public function failed(\Throwable $e): void
    {
        SeoPortfolio::find($this->portfolioId)?->markClustering('failed', ['error' => $e->getMessage()]);
    }
}
