<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoPortfolio;
use Platform\Seo\Models\SeoPortfolioMeasure;

/**
 * Erzeugt standardisierte Maßnahmen aus den deterministischen Signalen eines
 * Wirkungsraums (v1: Orchestrierungs-Board — Kannibalisierungs-Konflikte +
 * Pillar-Kandidaten). Idempotent über source_key: existiert eine Maßnahme zu
 * demselben Signal bereits (auch abgelehnt), wird sie NICHT neu vorgeschlagen —
 * so bleibt die Entscheidung als Wirkungsraum-Kontext erhalten.
 *
 * Später: weitere Quellen (Seiten-Gesundheit, Weißraum, Disposition) + KI-Lauf.
 */
class SeoMeasureGenerator
{
    /**
     * @param  array<int, array>  $boardRows  Zeilen aus SeoPortfolioDetail::orchestrationBoard
     * @return int  Anzahl neu erzeugter Maßnahmen
     */
    public function fromBoard(SeoPortfolio $portfolio, array $boardRows): int
    {
        $created = 0;

        foreach ($boardRows as $row) {
            if (! empty($row['conflict'])) {
                $created += $this->upsert($portfolio, [
                    'type' => 'structure_owner',
                    'target_cluster_id' => $row['cluster_id'],
                    'title' => 'Owner küren: '.$row['name'],
                    'rationale' => ($row['candidate_count'] ?? 0).' eigene Seiten konkurrieren um dieses Thema — einen Owner bestimmen, den Rest differenzieren (Anti-Kannibalisierung).',
                    'source_key' => 'conflict:cluster:'.$row['cluster_id'],
                    'score' => (int) ($row['demand'] ?? 0),
                    'route' => 'internal',
                ]);
            }

            if (! empty($row['pillar_candidate'])) {
                $created += $this->upsert($portfolio, [
                    'type' => 'new_property',
                    'target_cluster_id' => $row['cluster_id'],
                    'title' => 'Zentrale Seite prüfen: '.$row['name'],
                    'rationale' => 'Hohe Kopf-Nachfrage ('.number_format((int) ($row['demand'] ?? 0)).'), kein natürlicher Owner, mehrere Brands zersplittert — eine zentrale Pillar-Seite könnte die Nachfrage einsammeln und nach unten verlinken.',
                    'source_key' => 'pillar:cluster:'.$row['cluster_id'],
                    'score' => (int) ($row['demand'] ?? 0),
                    'route' => 'human',
                ]);
            }
        }

        return $created;
    }

    protected function upsert(SeoPortfolio $portfolio, array $attrs): int
    {
        $exists = SeoPortfolioMeasure::where('portfolio_id', $portfolio->id)
            ->where('source_key', $attrs['source_key'])
            ->exists();

        if ($exists) {
            return 0; // Entscheidung respektieren — nicht neu vorschlagen
        }

        SeoPortfolioMeasure::create(array_merge([
            'team_id' => $portfolio->team_id,
            'portfolio_id' => $portfolio->id,
            'source' => 'signal',
            'status' => SeoPortfolioMeasure::STATUS_PROPOSED,
        ], $attrs));

        return 1;
    }
}
