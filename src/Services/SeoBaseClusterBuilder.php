<?php

namespace Platform\Seo\Services;

use Platform\Integrations\Services\DataForSeoApiService;
use Platform\Seo\Models\SeoGeoLocation;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlDimension;

/**
 * Erzeugt aus den Dimensionen einer URL ihren gesperrten Basis-Cluster (den
 * Soll-Anker): Basis × GEO als Seed-Phrasen → DataForSEO expandiert sie zum
 * echten Keyword-Universum (Volumen/Intent/Saison/Difficulty) → Cluster mit
 * origin=base, pillar_url_id = die URL.
 *
 * Spine gesperrt / Membership wächst: der Cluster selbst (Name/Owner) bleibt
 * abgeleitet aus den Settings; re-run frischt nur die Keyword-Mitgliedschaft.
 * Es werden nur UNGECLUSTERTE Keywords angehängt — bestehende (geerntete)
 * Cluster werden nicht bestohlen.
 */
class SeoBaseClusterBuilder
{
    public function __construct(
        protected DataForSeoApiService $dataForSeoApi,
    ) {}

    /**
     * @return array{cluster?: SeoKeywordCluster, attached?: int, fetched?: int, potential?: int, seeds?: array<int,string>, error?: string}
     */
    public function build(SeoUrl $url): array
    {
        $teamId = (int) $url->team_id;
        $settings = SeoTeamSettings::where('team_id', $teamId)->first();
        if (! $settings) {
            return ['error' => 'Keine Team-Einstellungen gefunden.'];
        }

        $dims = SeoUrlDimension::where('url_id', $url->id)->get()->groupBy('dimension');
        $basis = $dims->get(SeoUrlDimension::DIM_BASIS, collect())->pluck('value')
            ->map(fn ($v) => trim((string) $v))->filter()->unique()->values()->all();
        if (empty($basis)) {
            return ['error' => 'Kein Basis-Begriff gesetzt — erst das SEO-Ziel definieren.'];
        }

        // Geo-fokussiert seeden: „catering düsseldorf" in NATIVER Umlaut-Schreibweise
        // (Katalog-ASCII „Dusseldorf"/„Munich" → nativ via SeoGeoLocation::nativeName).
        // Nur so liefert DataForSEO die lokale Long-Tail statt des nationalen
        // Universums; der Stadt-location_code wird vom Labs-Endpoint nicht
        // akzeptiert. Der accent-insensitive Filter unten bleibt als Sicherung.
        $geoDim = $dims->get(SeoUrlDimension::DIM_GEO, collect())->first();
        $geoLoc = ($geoDim && $geoDim->geo_location_id)
            ? SeoGeoLocation::find($geoDim->geo_location_id)
            : null;
        $geoShort = $geoLoc ? trim(explode(',', (string) $geoLoc->name)[0]) : null;
        $geoNative = SeoGeoLocation::nativeName($geoShort);

        $seeds = [];
        foreach ($basis as $b) {
            $b = mb_strtolower(trim($b));
            $seeds[] = $geoNative ? "{$b} {$geoNative}" : $b;
        }
        $seeds = array_values(array_unique($seeds));

        $connectionId = $settings->resolveConnectionId();
        if (! $connectionId) {
            return ['error' => 'Keine DataForSEO-Verbindung im Team.'];
        }

        try {
            $api = $this->dataForSeoApi->forConnection($connectionId);
            $results = $api->getLabsKeywordSuggestions(
                null,
                $seeds,
                $settings->location_code,
                $settings->resolveLanguageName(),
                100,
            );
        } catch (\Throwable $e) {
            return ['error' => 'DataForSEO-Fehler: '.$e->getMessage()];
        }

        // Basis-Cluster finden oder anlegen (1 URL : 1 Basis-Cluster).
        $cluster = SeoKeywordCluster::firstOrNew([
            'team_id' => $teamId,
            'pillar_url_id' => $url->id,
            'origin' => SeoKeywordCluster::ORIGIN_BASE,
        ]);
        $cluster->name = $this->clusterName($basis, $geoShort);
        if (! $cluster->exists) {
            $cluster->status = SeoKeywordCluster::STATUS_CANDIDATE;
        }
        $cluster->save();

        // Bei Neubau die Membership ersetzen (alte Seed-Artefakte lösen), dann
        // frisch anhängen — der Basis-Cluster ist das Soll aus den Dimensionen.
        // (Andocken von entdecktem Ranking ist ein späteres Feature.)
        SeoKeyword::where('cluster_id', $cluster->id)->update(['cluster_id' => null]);

        // Geo-Fokus: nur Keywords behalten, deren Text den Ort enthält (umlaut-/
        // akzent-insensitiv). Fallback auf alle, wenn <3 matchen (kein leerer
        // Cluster, und man sieht, dass der Fetch überhaupt lieferte).
        $use = $results;
        if ($geoShort) {
            $needle = $this->stripAccents(mb_strtolower($geoShort));
            $matched = array_values(array_filter(
                $results,
                fn ($r) => $needle !== '' && str_contains($this->stripAccents(mb_strtolower((string) $r->keyword)), $needle),
            ));
            $use = count($matched) >= 3 ? $matched : $results;
        }

        // Keywords upserten + nur ungeclusterte anhängen (kein Cluster-Diebstahl).
        $attached = 0;
        foreach ($use as $r) {
            $kwText = mb_strtolower(trim((string) $r->keyword));
            if ($kwText === '') {
                continue;
            }
            $season = SeoKeywordService::normalizeMonthlySearches($r->monthlySearches ?? null);

            $kw = SeoKeyword::firstOrNew(['team_id' => $teamId, 'keyword' => $kwText]);
            $kw->fill(array_filter([
                'search_volume' => $r->searchVolume,
                'cpc_cents' => $r->cpc !== null ? (int) round($r->cpc * 100) : null,
                'competition' => $r->competition,
                'keyword_difficulty' => $r->keywordDifficulty,
                'monthly_volumes' => $season['monthly_volumes'],
                'peak_month' => $season['peak_month'],
                'seasonality_index' => $season['seasonality_index'],
                'last_fetched_at' => now(),
            ], fn ($v) => $v !== null));

            // Nur anhängen, wenn frei oder schon in genau diesem Basis-Cluster.
            if ($kw->cluster_id === null || (int) $kw->cluster_id === (int) $cluster->id) {
                $kw->cluster_id = $cluster->id;
                $attached++;
            }
            $kw->save();
        }

        $cluster->keyword_count = SeoKeyword::where('cluster_id', $cluster->id)->count();
        $cluster->save();

        $potential = (int) SeoKeyword::where('cluster_id', $cluster->id)->sum('search_volume');

        return [
            'cluster' => $cluster,
            'attached' => $attached,
            'fetched' => count($results),
            'potential' => $potential,
            'seeds' => $seeds,
        ];
    }

    protected function clusterName(array $basis, ?string $geoShort): string
    {
        $core = implode(' / ', $basis);

        return $geoShort ? "{$core} · {$geoShort}" : $core;
    }

    /** Deutsche Umlaute/ß & Akzente auf ASCII abbilden — für den Ort-Filter. */
    protected function stripAccents(string $s): string
    {
        return strtr($s, [
            'ä' => 'a', 'ö' => 'o', 'ü' => 'u', 'ß' => 'ss',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'á' => 'a', 'à' => 'a', 'â' => 'a',
        ]);
    }
}
