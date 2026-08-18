<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoPresenceProbe;

/**
 * Presence-Collector (v2, docs/NORDSTERN-v2.md). Schreibt je Antwort-Einheit
 * einen Multi-Surface-Präsenz-Messpunkt (seo_answer_presence) — die Zeitreihe
 * für „Share of Answer". Schnellerer Tier als die Content-Extraktion, weil
 * Präsenz sich bewegt (~wöchentlich). Gratis: leitet aus bereits erhobenen
 * Daten (Rankings + llm_mentions) ab, kein Extra-API-Call. Nur eigene URLs.
 */
class AnswerPresenceCollector implements SeoCollectorInterface
{
    public function __construct(protected SeoPresenceProbe $probe) {}

    public function key(): string
    {
        return 'answer_presence';
    }

    public function name(): string
    {
        return 'Antwort-Präsenz';
    }

    public function estimateCost(Collection $urls): int
    {
        return 0; // leitet aus vorhandenen Daten ab
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
        return (int) config('seo.refresh_intervals.answer_presence', 168); // ~wöchentlich
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $processed = 0;
        $errors = [];

        foreach ($urls as $url) {
            if (! $url->is_own) {
                continue;
            }
            try {
                $this->probe->forUrl($url);
            } catch (\Throwable $e) {
                $errors[] = $url->url.': '.$e->getMessage();
            }
            $url->setCollectorTimestamp($this->key());
            $processed++;
        }

        return ['processed' => $processed, 'cost_cents' => 0, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 96; // nach der Antwort-Einheit-Extraktion
    }
}
