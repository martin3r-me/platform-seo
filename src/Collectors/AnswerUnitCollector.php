<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoAnswerExtractor;

/**
 * Content → Antwort-Einheit-Collector (v2, docs/NORDSTERN-v2.md). Zerlegt den
 * echten Seiteninhalt eigener URLs in atomare Antwort-Einheiten (seo_entities +
 * seo_answer_units). Teure KI-Extraktion, wenig volatil → langes Intervall
 * (~monatlich), zusätzlich HASH-GATE: unveränderter Inhalt überspringt die KI
 * (kein verbrannter Token). Läuft über seo:pipeline + Daten-Profile (standard/
 * tief). Nur eigene URLs.
 */
class AnswerUnitCollector implements SeoCollectorInterface
{
    public function __construct(protected SeoAnswerExtractor $extractor) {}

    public function key(): string
    {
        return 'answer_units';
    }

    public function name(): string
    {
        return 'Antwort-Einheiten';
    }

    public function estimateCost(Collection $urls): int
    {
        $per = (int) config('seo.cost_estimates.answer_units', 3);

        return $urls->filter(fn (SeoUrl $u) => $u->is_own)->count() * $per;
    }

    public function urlsDueForRefresh(Collection $urls): Collection
    {
        $intervalHours = $this->refreshIntervalHours();

        return $urls->filter(function (SeoUrl $url) use ($intervalHours) {
            return $url->is_own && $url->isDueForCollector($this->key(), $intervalHours);
        });
    }

    public function refreshIntervalHours(): int
    {
        return (int) config('seo.refresh_intervals.answer_units', 720); // ~monatlich
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $errors = [];
        $processed = 0;
        $costCents = 0;
        $costPer = (int) config('seo.cost_estimates.answer_units', 3);

        foreach ($urls as $url) {
            if (! $url->is_own) {
                continue;
            }
            try {
                $res = $this->extractor->extractForUrl($url, null, true); // hash-gated
                if (! empty($res['error'])) {
                    $errors[] = $url->url.': '.$res['error'];
                } elseif (empty($res['skipped'])) {
                    $costCents += $costPer; // KI lief nur bei geändertem Inhalt
                }
            } catch (\Throwable $e) {
                $errors[] = $url->url.': '.$e->getMessage();
            }

            // Geprüft — Fälligkeit vorrücken, auch wenn Inhalt unverändert war.
            $url->setCollectorTimestamp($this->key());
            $processed++;
        }

        return ['processed' => $processed, 'cost_cents' => $costCents, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 95; // spät — braucht die Seite (nach On-Page)
    }
}
