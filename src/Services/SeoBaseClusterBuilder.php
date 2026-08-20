<?php

namespace Platform\Seo\Services;

use Platform\Integrations\Services\DataForSeoApiService;
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

        // GEO in den Seed nehmen (Weg 1): Kurzname = erster Teil vor dem Komma
        // („Dusseldorf,North Rhine-Westphalia,Germany" → „Dusseldorf").
        $geoDim = $dims->get(SeoUrlDimension::DIM_GEO, collect())->first();
        $geoShort = $geoDim ? trim(explode(',', (string) $geoDim->value)[0]) : null;

        // Seeds = Basis × GEO (die starken Achsen). DataForSEO liefert die
        // Typ/Anlass/Zielgruppen-Varianten von selbst zurück — Kosten bounded.
        $seeds = [];
        foreach ($basis as $b) {
            $seeds[] = $geoShort ? mb_strtolower("{$b} {$geoShort}") : mb_strtolower($b);
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

        // Keywords upserten + nur ungeclusterte anhängen (kein Cluster-Diebstahl).
        $attached = 0;
        foreach ($results as $r) {
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
}
