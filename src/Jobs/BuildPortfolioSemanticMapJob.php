<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Services\SeoSemanticMapService;

/**
 * Baut die semantische Karte eines Wirkungsraums im Hintergrund (N Qdrant-Suchen)
 * und legt sie auf dem Wirkungsraum ab. Kein SERP, keine DataForSeo-Kosten — nur
 * ein gebündelter Embed-Aufruf; die Keyword-Vektoren liegen bereits in Qdrant.
 */
class BuildPortfolioSemanticMapJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $portfolioId, public bool $includeCompetitors = false) {}

    public function handle(SeoSemanticMapService $service): void
    {
        $portfolio = SeoPortfolio::find($this->portfolioId);
        if (! $portfolio) {
            return;
        }

        $result = $service->build($this->portfolioId, $this->includeCompetitors);

        $portfolio->markSemantic(empty($result['error']) ? 'completed' : 'failed', $result);
    }

    public function failed(\Throwable $e): void
    {
        SeoPortfolio::find($this->portfolioId)?->markSemantic('failed', ['error' => $e->getMessage()]);
    }
}
