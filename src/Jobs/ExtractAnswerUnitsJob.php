<?php

namespace Platform\Seo\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoAnswerExtractor;

/**
 * Extrahiert die Antwort-Einheiten einer URL im Hintergrund (v2). Für die
 * Breiten-Extraktion über alle Mitglieder eines Wirkungsraums — hash-gegatet,
 * damit unveränderte Seiten keine KI verbrennen.
 */
class ExtractAnswerUnitsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;

    public function __construct(public int $urlId, public ?int $portfolioId = null) {}

    public function handle(SeoAnswerExtractor $extractor): void
    {
        $url = SeoUrl::find($this->urlId);
        if ($url && $url->is_own) {
            $extractor->extractForUrl($url, $this->portfolioId, true); // hash-gated
        }
    }
}
