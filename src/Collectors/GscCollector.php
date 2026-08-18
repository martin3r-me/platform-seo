<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\GoogleSearchConsoleApiService;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoGscSnapshot;
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
 *  - Detailzeilen für getrackte Keywords (Zeitreihe).
 *
 * Der 28-Tage-Summary-Pass (collectSummary) legt zusätzlich die denormalisierte
 * GSC-Schicht je URL ab UND promoviert entdeckte Queries (echte Google-Anfragen
 * ohne getracktes Keyword) generell zu SeoKeywords mit origin='gsc'. Ab dann
 * laufen sie wie jedes andere Keyword — der KeywordMetricsCollector zieht das
 * DataForSeo-Volumen nach. Rauschboden via seo.gsc_promote_min_impressions.
 */
class GscCollector implements SeoCollectorInterface
{
    /** GSC-Daten reifen einige Tage nach — wir fragen einen finalisierten Tag ab. */
    protected const DATA_LAG_DAYS = 3;

    /** Fenster für die denormalisierte Zusammenfassung (GSC-natives Standardfenster). */
    protected const WINDOW_DAYS = 28;

    /** Untertgrenze, ab der eine entdeckte Query zum Keyword promoviert wird (Rauschboden). */
    protected const PROMOTE_MIN_IMPRESSIONS = 3;

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
            return $url->is_own && ($url->gsc_enabled ?? true) && $url->isDueForCollector($this->key(), $intervalHours);
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

        // Nur eigene, GSC-aktivierte URLs — für Wettbewerber haben wir keinen GSC-Zugriff.
        $ownUrls = $urls->filter(fn (SeoUrl $url) => $url->is_own && ($url->gsc_enabled ?? true));
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
            // Explizite Property (gsc_property) überschreibt das Domain-Matching
            // (Alias-Fälle wie broich.catering ↔ broichcatering.com).
            $siteUrl = $domainUrls->pluck('gsc_property')->filter()->first()
                ?? $this->matchProperty($domain, $properties);
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

            // 3) 28-Tage-Zusammenfassung je URL (Denorm + Discovery + CTR-Chancen +
            //    Snapshot) — spiegelt das Plausible-Denorm-Muster, damit der Tab
            //    ohne Live-API rendert und die echte Google-Sichtbarkeit Richtung
            //    Wirkungsraum trägt.
            try {
                $this->collectSummary($api, (int) $settings->team_id, $siteUrl, $date, $urlByPath, $keywordMap, $touchedUrlIds);
            } catch (\Throwable $e) {
                $errors[] = "summary {$domain}: ".$e->getMessage();
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
     * Rollt ein 28-Tage-Fenster je URL auf und legt die denormalisierte
     * GSC-Schicht + einen Snapshot ab. Zwei Fenster-Queries pro Property:
     *  - [page]        → exakte Skalar-Rollups (Clicks/Impr./CTR/Position)
     *  - [page, query] → Query-Aufschlüsselung: Top-Begriffe, Discovery
     *                    (ungetrackte Ranking-Begriffe), CTR-Chancen (Seite 1,
     *                    schwache CTR).
     *
     * @param  array<string, SeoUrl>  $urlByPath
     * @param  array<string, int>     $keywordMap
     * @param  array<int, bool>       $touchedUrlIds  (per Referenz ergänzt)
     */
    protected function collectSummary($api, int $teamId, string $siteUrl, string $date, array $urlByPath, array $keywordMap, array &$touchedUrlIds): void
    {
        // Pfad-Map → id-Map für den Pivot-Attach der promovierten Keywords.
        $urlById = [];
        foreach ($urlByPath as $u) {
            $urlById[$u->id] = $u;
        }

        $endDate = $date;
        $startDate = now()->subDays(self::DATA_LAG_DAYS + self::WINDOW_DAYS - 1)->toDateString();
        $snapshotDate = now()->toDateString();

        // (a) Skalar-Rollup je Seite.
        $pageRows = $api->querySearchAnalytics(null, $siteUrl, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['page'],
            'rowLimit' => 25000,
            'type' => 'web',
            'dataState' => 'final',
        ]);

        /** @var array<int, array{clicks:int,impressions:int,ctr:float,position:float}> $scalars */
        $scalars = [];
        foreach ($pageRows['rows'] ?? [] as $row) {
            $url = $this->matchUrl($row['keys'][0] ?? null, $urlByPath);
            if (! $url) {
                continue;
            }
            $scalars[$url->id] = [
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => (float) ($row['position'] ?? 0),
            ];
        }

        // (b) Query-Aufschlüsselung je Seite.
        $queryRows = $api->querySearchAnalytics(null, $siteUrl, [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'dimensions' => ['page', 'query'],
            'rowLimit' => 25000,
            'type' => 'web',
            'dataState' => 'final',
        ]);

        /** @var array<int, array<int, array<string, mixed>>> $queriesByUrl */
        $queriesByUrl = [];
        foreach ($queryRows['rows'] ?? [] as $row) {
            $url = $this->matchUrl($row['keys'][0] ?? null, $urlByPath);
            if (! $url) {
                continue;
            }
            $query = trim((string) ($row['keys'][1] ?? ''));
            if ($query === '') {
                continue;
            }
            $queriesByUrl[$url->id][] = [
                'query' => $query,
                'clicks' => (int) round((float) ($row['clicks'] ?? 0)),
                'impressions' => (int) round((float) ($row['impressions'] ?? 0)),
                'ctr' => (float) ($row['ctr'] ?? 0),
                'position' => round((float) ($row['position'] ?? 0), 1),
                'tracked' => isset($keywordMap[mb_strtolower($query)]),
            ];
        }

        // Je URL: Denorm-Felder + Snapshot schreiben.
        foreach ($scalars as $urlId => $s) {
            $rows = $queriesByUrl[$urlId] ?? [];

            // nach Impressionen sortiert = Sichtbarkeits-Reihenfolge.
            usort($rows, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);

            $topQueries = array_slice($rows, 0, 20);

            $untracked = array_values(array_filter($rows, fn ($r) => ! $r['tracked']));

            // Promotion: entdeckte Queries generell als Keyword übernehmen
            // (Quelle gsc). Ab dann laufen sie wie jedes andere Keyword — der
            // KeywordMetricsCollector zieht das Volumen bei DataForSeo nach
            // (last_fetched_at bleibt null → wird beim nächsten Lauf gefüllt).
            // Rauschboden: nur Queries mit belastbaren Impressionen.
            $promoteFloor = (int) config('seo.gsc_promote_min_impressions', self::PROMOTE_MIN_IMPRESSIONS);
            $urlModel = $urlById[$urlId] ?? null;
            if ($urlModel) {
                foreach ($untracked as $u) {
                    if ($u['impressions'] < $promoteFloor) {
                        continue;
                    }

                    $kw = SeoKeyword::firstOrCreate(
                        ['team_id' => $teamId, 'keyword' => mb_strtolower($u['query'])],
                        ['origin' => 'gsc', 'search_volume' => 0],
                    );

                    $urlModel->keywords()->syncWithoutDetaching([
                        $kw->id => [
                            'position' => min(65535, max(1, (int) round($u['position']))),
                            'position_updated_at' => now(),
                        ],
                    ]);
                }
            }

            // Für die Tab-Anzeige die Top-25 entdeckten (noch ungetrackten) Begriffe.
            $discovered = array_slice($untracked, 0, 25);

            // CTR-Chancen: Seite 1 (Pos ≤ 10), genug Impressionen, CTR deutlich
            // unter der positionsüblichen Erwartung → Title/Snippet-Hebel.
            $opps = array_values(array_filter($rows, function ($r) {
                if ($r['position'] > 10 || $r['position'] < 1 || $r['impressions'] < 30) {
                    return false;
                }

                return $r['ctr'] < 0.5 * $this->expectedCtr($r['position']);
            }));
            usort($opps, fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
            $opps = array_slice($opps, 0, 15);

            // Direkt per Query aktualisieren (kein Model-Reload nötig).
            SeoUrl::whereKey($urlId)->update([
                'gsc_clicks_28d' => $s['clicks'],
                'gsc_impressions_28d' => $s['impressions'],
                'gsc_ctr_28d' => $s['ctr'],
                'gsc_avg_position' => round($s['position'], 2),
                'gsc_top_queries' => $topQueries,
                'gsc_discovered_queries' => $discovered,
                'gsc_ctr_opportunities' => $opps,
                'gsc_fetched_at' => now(),
            ]);

            SeoGscSnapshot::updateOrCreate(
                ['url_id' => $urlId, 'snapshot_date' => $snapshotDate],
                [
                    'clicks_28d' => $s['clicks'],
                    'impressions_28d' => $s['impressions'],
                    'ctr' => $s['ctr'],
                    'avg_position' => round($s['position'], 2),
                ]
            );

            $touchedUrlIds[$urlId] = true;
        }
    }

    /**
     * Grobe positionsübliche CTR-Erwartung (organisch) — Baseline für die
     * CTR-Chancen-Erkennung, nicht als exakte Prognose gedacht.
     */
    protected function expectedCtr(float $position): float
    {
        return match (true) {
            $position <= 1.5 => 0.28,
            $position <= 2.5 => 0.15,
            $position <= 3.5 => 0.10,
            $position <= 5.5 => 0.07,
            default => 0.03,
        };
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
