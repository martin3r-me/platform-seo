<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoUrl;

/**
 * Portfolio-Reifegrad — der Optimierungs-Trichter als Gate-Modell (kein
 * konfigurierbares Regelwerk, opinionated Defaults). Die Phase eines Portfolios
 * ist das ERSTE Gate, das reißt; die empfohlene Aktion adressiert genau dieses.
 *
 *   Messen → Ordnen → Verteilen → Vertiefen → Konvertieren
 *
 * Kalibrierung: weich führen (Phase + nächster Zug immer sichtbar), hart sperren
 * nur, wo die Aktion aktiv Müll produziert — die KI-Verteilung auf ungeordneten
 * Daten. Baut auf SeoScopeMetrics (gemeinsamer Kennzahlen-Kern) auf. Skelett:
 * Ordnung + Ranking (Daten, die wir haben); GSC/On-Page/Wirkung folgen, sobald
 * die Datenbasis breiter ist.
 */
class SeoPortfolioHealth
{
    public function __construct(private SeoScopeMetrics $scope) {}

    public function evaluate(SeoPortfolio $portfolio): array
    {
        $ordnungsgradMin = (int) config('seo.portfolio_gates.ordnungsgrad_min', 70);
        $durchdringungMin = (int) config('seo.portfolio_gates.durchdringung_min', 50);

        $ids = $portfolio->effectiveUrlIds();
        $m = $this->scope->forUrlIds((int) $portfolio->team_id, $ids);
        $cov = $m['coverage'];
        $clusters = $m['clusters'];

        // Dimensions-Scores (0–100) aus den Daten, die wir HEUTE haben.
        $ordnung = (int) ($cov['pct'] ?? 0);                              // Ordnungsgrad
        $durchdringung = $clusters->isNotEmpty() ? (int) round($clusters->avg('pct')) : 0;

        // Owner-Zuordnung (pillar_url_id) je Cluster im Scope.
        $clusterIds = $clusters->pluck('cluster_id')->filter()->all();
        $clustersWithoutOwner = empty($clusterIds) ? 0
            : SeoKeywordCluster::whereIn('id', $clusterIds)->whereNull('pillar_url_id')->count();

        // Wirkung: Conversions/Goals über den Scope (Plausible, Site-Level).
        $conv = SeoUrl::whereIn('id', $ids)
            ->selectRaw('COALESCE(SUM(conversions_30d),0) as c, COALESCE(SUM(organic_conversions_30d),0) as oc, MAX(conversion_rate) as r, COUNT(conversions_fetched_at) as fetched')
            ->first();
        $conversions = (int) ($conv->c ?? 0);
        $organicConversions = (int) ($conv->oc ?? 0);
        $bestRate = (float) ($conv->r ?? 0);
        $hasConversionData = ((int) ($conv->fetched ?? 0)) > 0;

        // Gates (geordnet). met = Bedingung erfüllt.
        $hasData = ($cov['ranking'] ?? 0) > 0;
        $ordnungOk = $ordnung >= $ordnungsgradMin;
        $ownersOk = ! empty($clusterIds) && $clustersWithoutOwner === 0;
        $durchOk = $durchdringung >= $durchdringungMin;
        $wirkungOk = $hasConversionData && $conversions > 0;

        $defs = [
            [
                'key' => 'messen', 'label' => 'Daten', 'met' => $hasData,
                'action' => 'Rankings/Keywords ziehen',
                'why' => 'Noch keine Ranking-Daten — erst messen.',
            ],
            [
                'key' => 'ordnen', 'label' => 'Ordnen', 'met' => $ordnungOk,
                'action' => 'Ungeclusterten Rest clustern',
                'why' => "Erst ordnen — {$ordnung}% geordnet (Ziel ≥ {$ordnungsgradMin}%).",
            ],
            [
                'key' => 'verteilen', 'label' => 'Verteilen', 'met' => $ownersOk,
                'action' => 'Verteilung vorschlagen · Cluster-Owner zuweisen',
                'why' => $clustersWithoutOwner > 0
                    ? "{$clustersWithoutOwner} Cluster ohne Owner — zuweisen, entkannibalisieren."
                    : 'Themen auf Owner-Seiten verteilen.',
            ],
            [
                'key' => 'vertiefen', 'label' => 'Maßnahmen', 'met' => $durchOk,
                'action' => 'Lücken schließen (Content-Briefs)',
                'why' => "Durchdringung Ø {$durchdringung}% (Ziel ≥ {$durchdringungMin}%) — IST/SOLL-Lücken schließen.",
            ],
            [
                'key' => 'konvertieren', 'label' => 'Wirkung', 'met' => $wirkungOk, 'future' => ! $hasConversionData,
                'action' => $hasConversionData
                    ? 'Conversion-schwache Landingpages heben, starke ausbauen'
                    : 'Wirkungsdaten erschließen (Plausible-Ziele aktivieren)',
                'why' => $hasConversionData
                    ? "{$conversions} Conversions/30T, beste Rate {$bestRate}% — auf die wandelnden Seiten steuern."
                    : 'Conversion-/Wirkungsdaten noch nicht erhoben.',
            ],
        ];

        // Phase = erstes Gate, das reißt.
        $currentIndex = count($defs) - 1;
        foreach ($defs as $i => $d) {
            if (! $d['met']) {
                $currentIndex = $i;
                break;
            }
        }

        $phases = [];
        foreach ($defs as $i => $d) {
            $phases[] = [
                'key' => $d['key'],
                'label' => $d['label'],
                'status' => $i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'locked'),
                'future' => $d['future'] ?? false,
            ];
        }

        $current = $defs[$currentIndex];

        // Hartes Gate: KI-Verteilung erst, wenn gemessen UND geordnet.
        $canDistribute = $hasData && $ordnungOk;
        $blockReason = $canDistribute ? null : (! $hasData ? $defs[0]['why'] : $defs[1]['why']);

        return [
            'phases' => $phases,
            'current' => $current['key'],
            'current_label' => $current['label'],
            'next_action' => $current['action'],
            'reason' => $current['why'],
            'can_distribute' => $canDistribute,
            'block_reason' => $blockReason,
            'dimensions' => [
                'ordnung' => $ordnung,
                'durchdringung' => $durchdringung,
            ],
            'wirkung' => [
                'has_data' => $hasConversionData,
                'conversions' => $conversions,
                'organic_conversions' => $organicConversions,
                'best_rate' => $bestRate,
            ],
        ];
    }
}
