<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

/**
 * AI-Auffindbarkeit (LLM Mentions) Collector.
 *
 * Konsumiert den DataForSeoApiService (AI-Optimization-API). Pro eigener Domain
 * wird ein target_metrics-Call gemacht (ChatGPT + Google AI Overview) und die
 * aggregierte Sichtbarkeit (Mentions, AI-Suchvolumen, Plattform-Breakdown)
 * denormalisiert auf der Root-URL der Domain gespeichert.
 *
 * Kostenintensiver als klassische SEO-Calls und weniger volatil → langes
 * Refresh-Intervall. Läuft nur für eigene URLs.
 */
class LlmMentionsCollector implements SeoCollectorInterface
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

    public function key(): string
    {
        return 'llm_mentions';
    }

    public function name(): string
    {
        return 'LLM Mentions';
    }

    public function estimateCost(Collection $urls): int
    {
        $costPerDomain = (int) config('seo.cost_estimates.llm_mentions', 40);

        // Kosten fallen je Domain an, nicht je URL.
        $domains = $urls->filter(fn (SeoUrl $u) => $u->is_own)
            ->map(fn (SeoUrl $u) => $this->normalizeDomain($u->domain))
            ->unique();

        return (int) ($domains->count() * $costPerDomain);
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
        return (int) config('seo.refresh_intervals.llm_mentions', 720); // ~monatlich
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $connectionId = $settings->resolveConnectionId();
        if (! $connectionId) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Keine DataForSEO-Connection für Team']];
        }

        $api = $this->dataForSeoApi->forConnection($connectionId);
        $costPerDomain = (int) config('seo.cost_estimates.llm_mentions', 40);

        $processed = 0;
        $totalCost = 0;
        $errors = [];

        // Ein Call je Domain; Ergebnis auf die Root-URL der Domain schreiben.
        $ownUrls = $urls->filter(fn (SeoUrl $url) => $url->is_own);
        $byDomain = $ownUrls->groupBy(fn (SeoUrl $url) => $this->normalizeDomain($url->domain));

        foreach ($byDomain as $domain => $domainUrls) {
            $root = $domainUrls->firstWhere('path', '/') ?? $domainUrls->first();

            try {
                $result = $api->getLlmMentionsTargetMetrics(null, $domain);
            } catch (\Throwable $e) {
                $errors[] = "{$domain}: ".$e->getMessage();
                continue;
            }

            $total = $result['aggregated_metrics']['total'] ?? [];
            $mentions = $total['mentions'] ?? $total['mentions_count'] ?? null;
            $aiVolume = $total['ai_search_volume'] ?? $total['search_volume'] ?? null;

            $root->update([
                'llm_mentions' => is_numeric($mentions) ? (int) $mentions : null,
                'llm_ai_search_volume' => is_numeric($aiVolume) ? (int) $aiVolume : null,
                'llm_mentions_data' => $result['aggregated_metrics'] ?? $result,
                'llm_mentions_fetched_at' => now(),
            ]);
            $root->setCollectorTimestamp($this->key());

            $processed++;
            $totalCost += $costPerDomain;
        }

        if (! empty($errors)) {
            Log::warning('SEO: LlmMentionsCollector Teilfehler', ['errors' => $errors]);
        }

        return ['processed' => $processed, 'cost_cents' => $totalCost, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 40;
    }

    protected function normalizeDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));

        return preg_replace('/^www\./', '', $domain);
    }
}
