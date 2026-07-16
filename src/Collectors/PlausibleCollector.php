<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlTraffic;

/**
 * Traffic-Collector: holt Besucher/Pageviews pro URL aus Plausible Analytics.
 *
 * Konsumiert den bestehenden PlausibleApiService (Integrations-Modul) — kein
 * eigener API-Client. Plausibles event:page-Breakdown liefert Metriken pro Pfad;
 * jeder Pfad wird auf die passende SeoUrl (Domain+Path) gemappt und als Tageszeile
 * in seo_url_traffic persistiert. So konsolidiert sich der Traffic auf der URL.
 */
class PlausibleCollector implements SeoCollectorInterface
{
    public function __construct(
        protected PlausibleApiService $plausibleApi,
        protected IntegrationConnectionResolver $connectionResolver,
    ) {}

    public function key(): string
    {
        return 'plausible';
    }

    public function name(): string
    {
        return 'Plausible Traffic';
    }

    public function estimateCost(Collection $urls): int
    {
        return 0; // Plausible: eigener Server / kostenfrei
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
        return (int) config('seo.refresh_intervals.plausible', 24);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $errors = [];

        $team = $settings->team;
        if (! $team) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Kein Team für Settings']];
        }

        $connection = $this->connectionResolver->resolveForTeam('plausible', $team);
        if (! $connection) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Keine aktive Plausible-Connection für Team']];
        }

        $api = $this->plausibleApi->forConnection($connection->id);

        // Nur eigene URLs — für Wettbewerber haben wir keinen Plausible-Zugriff.
        $ownUrls = $urls->filter(fn (SeoUrl $url) => $url->is_own);
        if ($ownUrls->isEmpty()) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => []];
        }

        // Vortag (letzter vollständiger Tag) — baut die Tages-Historie vorwärts auf.
        $date = now()->subDay()->toDateString();
        $processed = 0;

        // Nach Domain gruppieren; der Breakdown wird pro Domain (= Plausible site_id)
        // direkt versucht. Domains ohne Plausible-Site scheitern und werden übersprungen.
        $urlsByDomain = $ownUrls->groupBy(fn (SeoUrl $url) => $this->normalizeDomain($url->domain));

        foreach ($urlsByDomain as $domain => $domainUrls) {
            // Pfad → SeoUrl-Lookup für schnelles Matching.
            $urlByPath = [];
            foreach ($domainUrls as $url) {
                $urlByPath[$this->normalizePath($url->path)] = $url;
            }

            try {
                $breakdown = $api->getBreakdown(null, [
                    'site_id' => $domain,
                    'property' => 'event:page',
                    'period' => 'day',
                    'date' => $date,
                    'metrics' => 'visitors,pageviews,bounce_rate,visit_duration',
                    'limit' => 1000,
                ]);
            } catch (\Throwable $e) {
                $errors[] = "breakdown {$domain}: ".$e->getMessage();
                continue;
            }

            foreach ($breakdown['results'] ?? [] as $row) {
                $path = $this->normalizePath($row['page'] ?? null);
                if ($path === null || ! isset($urlByPath[$path])) {
                    continue; // Seite ohne getrackte SeoUrl
                }

                /** @var SeoUrl $url */
                $url = $urlByPath[$path];

                SeoUrlTraffic::updateOrCreate(
                    ['url_id' => $url->id, 'date' => $date, 'source' => 'plausible'],
                    [
                        'visitors' => (int) ($row['visitors'] ?? 0),
                        'pageviews' => (int) ($row['pageviews'] ?? 0),
                        'bounce_rate' => (float) ($row['bounce_rate'] ?? 0),
                        'visit_duration' => (int) round((float) ($row['visit_duration'] ?? 0)),
                    ]
                );

                $this->updateDenormalizedTraffic($url);
                $url->setCollectorTimestamp($this->key());
                $processed++;
            }
        }

        if (! empty($errors)) {
            Log::warning('SEO: PlausibleCollector Teilfehler', ['errors' => $errors]);
        }

        return ['processed' => $processed, 'cost_cents' => 0, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 20;
    }

    /**
     * Aktualisiert die denormalisierten 30-Tage-Werte auf der URL aus der Zeitreihe.
     */
    protected function updateDenormalizedTraffic(SeoUrl $url): void
    {
        $since = now()->subDays(30)->toDateString();

        $agg = SeoUrlTraffic::query()
            ->where('url_id', $url->id)
            ->where('source', 'plausible')
            ->where('date', '>=', $since)
            ->selectRaw('COALESCE(SUM(visitors), 0) as v, COALESCE(SUM(pageviews), 0) as p')
            ->first();

        $url->update([
            'visitors_30d' => (int) ($agg->v ?? 0),
            'pageviews_30d' => (int) ($agg->p ?? 0),
            'traffic_fetched_at' => now(),
        ]);
    }

    protected function normalizeDomain(?string $domain): string
    {
        $domain = strtolower(trim((string) $domain));

        return preg_replace('/^www\./', '', $domain);
    }

    protected function normalizePath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
