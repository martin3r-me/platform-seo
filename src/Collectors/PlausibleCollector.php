<?php

namespace Platform\Seo\Collectors;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Platform\Integrations\Services\IntegrationConnectionResolver;
use Platform\Integrations\Services\PlausibleApiService;
use Platform\Seo\Contracts\SeoCollectorInterface;
use Platform\Seo\Models\SeoConversionSnapshot;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;
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

        // Manuelles Opt-in: nur Domains sammeln, die am Parent aktiviert wurden.
        // Kein Blind-Probing (das lieferte für die meisten Domains 401). Map:
        // normalisierte Domain => aktivierte Root-URL (dient als Parent für neu
        // entdeckte Pfade). site_id = plausible_site_id ?? Domain.
        $enabledRoots = SeoUrl::where('team_id', $team->id)
            ->where('is_own', true)
            ->where('plausible_enabled', true)
            ->get()
            ->keyBy(fn (SeoUrl $u) => $this->normalizeDomain($u->domain));

        if ($enabledRoots->isEmpty()) {
            return ['processed' => 0, 'cost_cents' => 0, 'errors' => []];
        }

        // Vortag (letzter vollständiger Tag) — baut die Tages-Historie vorwärts auf.
        $date = now()->subDay()->toDateString();
        $processed = 0;

        // Nach Domain gruppieren; der Breakdown wird pro Domain (= Plausible site_id)
        // direkt versucht. Domains ohne Plausible-Site scheitern und werden übersprungen.
        $urlsByDomain = $ownUrls->groupBy(fn (SeoUrl $url) => $this->normalizeDomain($url->domain));

        foreach ($urlsByDomain as $domain => $domainUrls) {
            // Nur aktivierte Domains — der Rest wird still übersprungen.
            $root = $enabledRoots->get($domain);
            if (! $root) {
                continue;
            }
            $siteId = $root->plausible_site_id ?: $domain;

            // Pfad → SeoUrl-Lookup über den GESAMTEN eigenen Bestand der Domain
            // (nicht nur die aktuelle Batch), damit bestehende Pfade sicher erkannt
            // und nicht fälschlich als "neu entdeckt" angelegt/getaggt werden.
            $urlByPath = [];
            foreach (SeoUrl::where('team_id', $team->id)->where('is_own', true)->where('domain', $domain)->get() as $url) {
                $urlByPath[$this->normalizePath($url->path)] = $url;
            }

            try {
                $breakdown = $api->getBreakdown(null, [
                    'site_id' => $siteId,
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

            // Organischen Anteil separat holen (Channel-Filter) — DAS ist die
            // SEO-relevante Zahl. Fällt der gefilterte Call (ältere Plausible-
            // Version o.ä.), bleibt Organic leer; der Gesamt-Traffic bleibt intakt.
            $organicByPath = [];
            try {
                $organicBreakdown = $api->getBreakdown(null, [
                    'site_id' => $siteId,
                    'property' => 'event:page',
                    'period' => 'day',
                    'date' => $date,
                    'metrics' => 'visitors,pageviews',
                    'filters' => config('seo.plausible.organic_filter', 'visit:channel==Organic Search'),
                    'limit' => 1000,
                ]);
                foreach ($organicBreakdown['results'] ?? [] as $orow) {
                    $opath = $this->normalizePath($orow['page'] ?? null);
                    if ($opath !== null) {
                        $organicByPath[$opath] = [
                            'visitors' => (int) ($orow['visitors'] ?? 0),
                            'pageviews' => (int) ($orow['pageviews'] ?? 0),
                        ];
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = "organic breakdown {$domain}: ".$e->getMessage();
            }

            foreach ($breakdown['results'] ?? [] as $row) {
                $path = $this->normalizePath($row['page'] ?? null);
                if ($path === null) {
                    continue;
                }

                // Von Plausible entdeckte Seite, die wir (z.B. mangels Indexierung)
                // noch nicht kennen → als Kind-URL mit Parent-Bezug anlegen.
                /** @var SeoUrl $url */
                $url = $urlByPath[$path] ?? null;
                if (! $url) {
                    $url = $this->ensureChildUrl($root, $domain, $path);
                    $urlByPath[$path] = $url;
                }

                SeoUrlTraffic::updateOrCreate(
                    ['url_id' => $url->id, 'date' => $date, 'source' => 'plausible'],
                    [
                        'visitors' => (int) ($row['visitors'] ?? 0),
                        'pageviews' => (int) ($row['pageviews'] ?? 0),
                        'organic_visitors' => (int) ($organicByPath[$path]['visitors'] ?? 0),
                        'organic_pageviews' => (int) ($organicByPath[$path]['pageviews'] ?? 0),
                        'bounce_rate' => (float) ($row['bounce_rate'] ?? 0),
                        'visit_duration' => (int) round((float) ($row['visit_duration'] ?? 0)),
                    ]
                );

                $this->updateDenormalizedTraffic($url);
                $url->setCollectorTimestamp($this->key());
                $processed++;
            }

            // Goals/Conversions (Site-Level, 30-Tage-Snapshot) → auf die Root-URL.
            // Die Wirkung-Zahl: CTA-Clicks, Formular-Submits usw. liegen in
            // Plausible und werden hier endlich abgeholt. Bricht der Call (keine
            // Goals konfiguriert o.ä.), bleibt der Traffic unberührt.
            try {
                $goals = $api->getBreakdown(null, [
                    'site_id' => $siteId,
                    'property' => 'event:goal',
                    'period' => '30d',
                    'metrics' => 'visitors,events,conversion_rate',
                    'limit' => 20,
                ]);
                $this->storeGoals($root, $goals['results'] ?? []);
                $this->storeConversionPages($root, $siteId, $api, $goals['results'] ?? []);
            } catch (\Throwable $e) {
                $errors[] = "goals {$domain}: ".$e->getMessage();
            }

            // Organische Landingpages + Engagement (Verweildauer/Bounce je Einstiegsseite).
            try {
                $this->storeOrganicLandingPages($root, $siteId, $api);
            } catch (\Throwable $e) {
                $errors[] = "landing {$domain}: ".$e->getMessage();
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
            ->selectRaw('COALESCE(SUM(visitors), 0) as v, COALESCE(SUM(pageviews), 0) as p, COALESCE(SUM(organic_visitors), 0) as ov, COALESCE(SUM(organic_pageviews), 0) as op')
            ->first();

        $url->update([
            'visitors_30d' => (int) ($agg->v ?? 0),
            'pageviews_30d' => (int) ($agg->p ?? 0),
            'organic_visitors_30d' => (int) ($agg->ov ?? 0),
            'organic_pageviews_30d' => (int) ($agg->op ?? 0),
            'traffic_fetched_at' => now(),
        ]);
    }

    /**
     * Speichert den Goal/Conversion-Snapshot (30 Tage) auf der Root-URL:
     * Summe der Conversion-Events, primäres Goal (nach Besuchern) samt Rate,
     * und die Top-Goals als JSON-Detail. Site-Level (nicht je Pfad).
     *
     * @param  array<int,array<string,mixed>>  $results
     */
    protected function storeGoals(SeoUrl $root, array $results): void
    {
        if (empty($results)) {
            $root->update(['conversions_fetched_at' => now()]);

            return;
        }

        $goals = collect($results)->map(fn ($g) => [
            'goal' => (string) ($g['goal'] ?? '?'),
            'visitors' => (int) ($g['visitors'] ?? 0),
            'events' => (int) ($g['events'] ?? 0),
            'rate' => (float) ($g['conversion_rate'] ?? 0),
        ])->sortByDesc('visitors')->values();

        $primary = $goals->first();

        $conversions30d = (int) $goals->sum('events');
        $rate = $primary ? $primary['rate'] : null;

        $root->update([
            'conversions_30d' => $conversions30d,
            'conversion_rate' => $rate,
            'primary_goal' => $primary['goal'] ?? null,
            'top_goals' => $goals->take(8)->all(),
            'conversions_fetched_at' => now(),
        ]);

        // Verlauf: Snapshot je Tag (event-getrieben, im Takt der Datensammlung).
        SeoConversionSnapshot::updateOrCreate(
            ['url_id' => $root->id, 'snapshot_date' => now()->toDateString()],
            ['conversions_30d' => $conversions30d, 'conversion_rate' => $rate],
        );
    }

    /**
     * Conversion-Attribution je Landingpage: je Goal (Top-Goals) die konvertie-
     * renden Seiten mit Rate — „welche SEO-Seite bringt die Bewerbungen". Ein
     * event:page-Breakdown je Goal, gefiltert auf dieses Goal. Auf der Root-URL
     * als JSON abgelegt (Site-Level). Der stärkste SEO→Wert-Hebel.
     *
     * @param  array<int,array<string,mixed>>  $goalResults
     */
    protected function storeConversionPages(SeoUrl $root, string $siteId, $api, array $goalResults): void
    {
        if (empty($goalResults)) {
            return;
        }

        // Top-Goals nach Besuchern (die relevantesten), max 5 — begrenzt die Calls.
        $topGoals = collect($goalResults)
            ->sortByDesc(fn ($g) => (int) ($g['visitors'] ?? 0))
            ->take(5);

        $conversionPages = [];

        foreach ($topGoals as $g) {
            $goalName = (string) ($g['goal'] ?? '');
            if ($goalName === '') {
                continue;
            }

            try {
                $bd = $api->getBreakdown(null, [
                    'site_id' => $siteId,
                    'property' => 'event:page',
                    'period' => '30d',
                    'metrics' => 'visitors,events,conversion_rate',
                    'filters' => 'event:goal==' . $goalName,
                    'limit' => 6,
                ]);
            } catch (\Throwable $e) {
                continue; // Goal-Namen mit Sonderzeichen o.ä. — still überspringen.
            }

            $pages = collect($bd['results'] ?? [])
                ->map(fn ($r) => [
                    'page' => (string) ($r['page'] ?? ''),
                    'visitors' => (int) ($r['visitors'] ?? 0),
                    'events' => (int) ($r['events'] ?? 0),
                    'rate' => (float) ($r['conversion_rate'] ?? 0),
                ])
                ->filter(fn ($p) => $p['events'] > 0 && $p['page'] !== '')
                ->values()
                ->all();

            if (! empty($pages)) {
                $conversionPages[] = [
                    'goal' => $goalName,
                    'visitors' => (int) ($g['visitors'] ?? 0),
                    'rate' => (float) ($g['conversion_rate'] ?? 0),
                    'pages' => $pages,
                ];
            }
        }

        $root->update(['conversion_pages' => $conversionPages ?: null]);
    }

    /**
     * Organische Landingpages + Engagement: je organischer Einstiegsseite die
     * Besucher, Verweildauer (Sek.) und Bounce-Rate (%). Zeigt, welche SEO-Türen
     * den Traffic halten — das Bindeglied Ranking → Conversion. Auf der Root-URL.
     */
    protected function storeOrganicLandingPages(SeoUrl $root, string $siteId, $api): void
    {
        $bd = $api->getBreakdown(null, [
            'site_id' => $siteId,
            'property' => 'visit:entry_page',
            'period' => '30d',
            'metrics' => 'visitors,visit_duration,bounce_rate',
            'filters' => config('seo.plausible.organic_filter', 'visit:channel==Organic Search'),
            'limit' => 15,
        ]);

        $pages = collect($bd['results'] ?? [])
            ->map(fn ($r) => [
                'page' => (string) ($r['entry_page'] ?? ''),
                'visitors' => (int) ($r['visitors'] ?? 0),
                'duration' => (int) round((float) ($r['visit_duration'] ?? 0)),
                'bounce' => (int) round((float) ($r['bounce_rate'] ?? 0)),
            ])
            ->filter(fn ($p) => $p['page'] !== '' && $p['visitors'] > 0)
            ->values()
            ->all();

        $root->update(['organic_landing_pages' => $pages ?: null]);
    }

    /**
     * Legt eine von Plausible entdeckte Seite als eigene Kind-URL an und hängt
     * sie an die aktivierte Root-URL (parent_child). Herkunft wird als
     * source_module="plausible" registriert. Idempotent via firstOrCreate.
     */
    protected function ensureChildUrl(SeoUrl $root, string $domain, string $path): SeoUrl
    {
        $normalizedUrl = SeoUrl::normalizeUrl($domain . $path);

        $child = SeoUrl::firstOrCreate(
            [
                'team_id' => $root->team_id,
                'url_hash' => SeoUrl::hashUrl($normalizedUrl),
            ],
            [
                'url' => $normalizedUrl,
                'domain' => $domain,
                'is_own' => true,
                'status' => 'active',
                'priority' => 50,
            ],
        );

        // Herkunft kennzeichnen: von Plausible entdeckt.
        SeoUrlRegistration::firstOrCreate(
            [
                'url_id' => $child->id,
                'source_module' => 'plausible',
                'source_type' => 'traffic',
            ],
            [
                'reason' => 'auto_discovery',
            ],
        );

        // Parent-Child-Beziehung zur aktivierten Root-URL.
        if ($child->id !== $root->id) {
            SeoUrlRelationship::firstOrCreate(
                [
                    'source_url_id' => $root->id,
                    'target_url_id' => $child->id,
                    'type' => 'parent_child',
                ],
                [
                    'team_id' => $root->team_id,
                    'detected_at' => now(),
                ],
            );
        }

        return $child;
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
