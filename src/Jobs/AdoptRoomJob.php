<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Services\SeoClusteringService;

/**
 * Manifestiert ein „Zimmer" der semantischen Karte: prüft den semantischen
 * Vorschlag (Keyword-Menge) per SERP und persistiert das Ergebnis als echte
 * Cluster. Nutzt dieselbe gehärtete, wiederaufsetzbare SERP-Pipeline wie das
 * Nach-Clustern — nur scoped auf genau diese Keywords (billig).
 */
class AdoptRoomJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1200;

    public int $tries = 1;

    /**
     * @param  int[]  $keywordIds
     */
    public function __construct(
        public int $portfolioId,
        public array $keywordIds,
        public int $minOverlap = 3,
    ) {}

    public function handle(SeoClusteringService $service): void
    {
        $deadlineTs = time() + 600;

        $result = $service->autoClusterForRoom(
            $this->portfolioId, $this->keywordIds, $this->minOverlap, $deadlineTs
        );

        // Noch nicht fertig (Deadline) und kein Fehler → Rest in einem Folgelauf holen.
        if (($result['complete'] ?? true) === false && empty($result['error'])) {
            self::dispatch($this->portfolioId, $this->keywordIds, $this->minOverlap);
        }
    }

    public function failed(\Throwable $e): void
    {
        \Platform\Seo\Models\SeoPortfolio::find($this->portfolioId)
            ?->markClustering('failed', ['error' => $e->getMessage()]);
    }
}
