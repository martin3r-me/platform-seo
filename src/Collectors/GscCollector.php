<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlGscData;

/**
 * Google Search Console data collector.
 *
 * Konsumiert den bestehenden GoogleSearchConsoleApiService (Integrations-Modul,
 * OAuth mit Auto-Token-Refresh) — kein eigener API-Client. Pro eigener Domain wird
 * die passende verifizierte GSC-Property automatisch gematcht und die Search
 * Analytics eines finalisierten Tages abgefragt.
 *
 * Persistenz je URL in seo_url_gsc_data:
 *  - eine Aggregat-Zeile pro Seite (keyword_id = null): Gesamt-Impressions/Clicks/
 *    CTR/Ø-Position — so bekommt jede URL immer ihre GSC-Gesamtwerte.
 *  - Detailzeilen nur für Queries, die bereits als SeoKeyword existieren
 *    (keine automatische Keyword-Anlage → respektiert die kuratierte Keyword-Liste).
 */
class GscCollector implements SeoCollectorInterface
{
    /** GSC-Daten reifen einige Tage nach — wir fragen einen finalisierten Tag ab. */
    protected const DATA_LAG_DAYS = 3;

    public function __construct(
        protected GoogleSearchConsoleApiService $gscApi,
        protected IntegrationConnectionResolver $connectionResolver,
    ) {}

    public function key(): string
    {
        return 'gsc';
    }

    public function name(): string
    {
        return 'Google Search Console';
    }

    public function estimateCost(Collection $urls): int
    {
        return 0; // GSC ist kostenfrei
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
        return (int) config('seo.refresh_intervals.gsc', 24);
    }

    public function collect(SeoTeamSettings $settings, Collection $urls): array
    {
        $errors = [];

        $team = $settings->team;
        if (! $team) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Kein Team für Settings']];
        }

        $connection = $this->connectionResolver->resolveForTeam('google_search_console', $team);
        if (! $connection) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['Keine aktive GSC-Connection für Team']];
        }

        $api = $this->gscApi->forConnection($connection->id);

        // Nur eigene URLs — für Wettbewerber haben wir keinen GSC-Zugriff.
        $ownUrls = $urls->filter(fn (SeoUrl $url) => $url->is_own);
        if ($ownUrls->isEmpty()) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => []];
        }

        // Verifizierte Properties einmalig laden, um Domain → siteUrl zu matchen.
        try {
            $sites = $api->getSites();
            $properties = collect($sites['siteEntry'] ?? [])->pluck('siteUrl')->filter()->all();
        } catch (\Throwable $e) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => ['getSites: '.$e->getMessage()]];
        }

        // Team-Keywords einmalig als lower(text) → id-Map für schnelles Query-Matching.
        $keywordMap = SeoKeyword::query()
            ->where('team_id', $settings->team_id)
            ->pluck('id', 'keyword')
            ->mapWithKeys(fn ($id, $keyword) => [mb_strtolower(trim($keyword)) => $id])
            ->all();

        $date = now()->subDays(self::DATA_LAG_DAYS)->toDateString();
        $processed = 0;

        $urlsByDomain = $ownUrls->groupBy(fn (SeoUrl $url) => $this->normalizeDomain($url->domain));

        foreach ($urlsByDomain as $domain => $domainUrls) {
            $siteUrl = $this->matchProperty($domain, $properties);
            if ($siteUrl === null) {
                continue; // keine verifizierte GSC-Property für diese Domain
            }

            // Pfad → SeoUrl-Lookup für schnelles Matching.
            $urlByPath = [];
            foreach ($domainUrls as $url) {
                $urlByPath[$this->normalizePath($url->path)] = $url;
            }

            $touchedUrlIds = [];

            // 1) Seiten-Aggregat (keyword_id = null).
            try {
                $pageRows = $api->querySearchAnalytics(null, $siteUrl, [
                    'startDate' => $date,
                    'endDate' => $date,
                    'dimensions' => ['page'],
                    'rowLimit' => 25000,
                    'type' => 'web',
                    'dataState' => 'final',
                ]);
            } catch (\Throwable $e) {
                $errors[] = "aggregate {$domain}: ".$e->getMessage();
                $pageRows = [];
            }

            foreach ($pageRows['rows'] ?? [] as $row) {
                $url = $this->matchUrl($row['keys'][0] ?? null, $urlByPath);
                if (! $url) {
                    continue;
                }

                $this->upsertRow($url->id, null, $date, $row);
                $touchedUrlIds[$url->id] = true;
            }

            // 2) Detailzeilen je Query — nur für bereits getrackte Keywords.
            try {
                $queryRows = $api->querySearchAnalytics(null, $siteUrl, [
                    'startDate' => $date,
                    'endDate' => $date,
                    'dimensions' => ['page', 'query'],
                    'rowLimit' => 25000,
                    'type' => 'web',
                    'dataState' => 'final',
                ]);
            } catch (\Throwable $e) {
                $errors[] = "queries {$domain}: ".$e->getMessage();
                $queryRows = [];
            }

            foreach ($queryRows['rows'] ?? [] as $row) {
                $url = $this->matchUrl($row['keys'][0] ?? null, $urlByPath);
                if (! $url) {
                    continue;
                }

                $query = mb_strtolower(trim((string) ($row['keys'][1] ?? '')));
                $keywordId = $keywordMap[$query] ?? null;
                if ($keywordId === null) {
                    continue; // untracked Query — keine automatische Keyword-Anlage
                }

                $this->upsertRow($url->id, $keywordId, $date, $row);
                $touchedUrlIds[$url->id] = true;
            }

            // Timestamp für alle URLs dieser Property, die Daten bekommen haben.
            foreach (array_keys($touchedUrlIds) as $urlId) {
                $url = $domainUrls->firstWhere('id', $urlId);
                $url?->setCollectorTimestamp($this->key());
                $processed++;
            }
        }

        if (! empty($errors)) {
            Log::warning('SEO: GscCollector Teilfehler', ['errors' => $errors]);
        }

        return ['processed' => $processed, 'cost_cents' => 0, 'errors' => $errors];
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function order(): int
    {
        return 15;
    }

    /**
     * Upsert einer GSC-Zeile (Aggregat wenn keywordId = null, sonst Query-Detail).
     */
    protected function upsertRow(int $urlId, ?int $keywordId, string $date, array $row): void
    {
        SeoUrlGscData::updateOrCreate(
            [
                'url_id' => $urlId,
                'keyword_id' => $keywordId,
                'date' => $date,
                'device' => 'all',
                'country' => 'all',
            ],
            [
                'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                'ctr' => (float) ($row['ctr'] ?? 0),           // Anteil 0–1
                'avg_position' => (float) ($row['position'] ?? 0),
            ]
        );
    }

    /**
     * Matcht eine GSC-Property (siteUrl) für eine Domain.
     * Bevorzugt die Domain-Property (sc-domain:), sonst URL-Präfix (https/http).
     */
    protected function matchProperty(string $domain, array $properties): ?string
    {
        // Statt Kandidaten aus UNSERER Domain zu bauen: alle GSC-Properties
        // auf ihre nackte Domain normalisieren und gegen unsere matchen.
        // So matchen sc-domain:, https://, http://, www. automatisch.
        $target = $this->bareDomain($domain);

        // Alias für echte Domain-Wechsel (unsere URL läuft unter anderer
        // registrierter Domain als die GSC-Property, z.B. broich.catering →
        // broichcatering.com). Kein Format-, sondern ein Domain-Unterschied.
        $aliases = config('seo.gsc_aliases', []);
        $targetAlias = isset($aliases[$target]) ? $this->bareDomain((string) $aliases[$target]) : null;

        foreach ($properties as $property) {
            $bare = $this->bareDomain((string) $property);
            if ($bare === $target || ($targetAlias !== null && $bare === $targetAlias)) {
                return $property;
            }
        }

        return null;
    }

    /**
     * Reduziert eine GSC-Property ODER unsere Domain auf die nackte Domain:
     * sc-domain:, Protokoll, www. und Trailing-Slash entfernt.
     */
    protected function bareDomain(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('#^sc-domain:#', '', $value);
        $value = preg_replace('#^https?://#', '', $value);
        $value = preg_replace('#^www\.#', '', $value);

        return rtrim($value, '/');
    }

    /**
     * Matcht eine GSC-Seiten-URL auf eine getrackte SeoUrl über den normalisierten Pfad.
     */
    protected function matchUrl(?string $pageUrl, array $urlByPath): ?SeoUrl
    {
        if ($pageUrl === null || $pageUrl === '') {
            return null;
        }

        $path = $this->normalizePath(parse_url($pageUrl, PHP_URL_PATH) ?: '/');

        return $urlByPath[$path] ?? null;
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
