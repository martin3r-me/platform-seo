<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoAnswerPresence;
use Platform\Seo\Models\SeoAnswerUnit;
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

    /**
     * v2-Signale aus Antwort-Einheiten + Präsenz: veraltete Antworten
     * (change_page) und GEO-Lücken (klassisch präsent, aber KI-nicht-zitiert →
     * change_page). Der differenzierende Treibstoff des Posteingangs.
     */
    public function fromV2(SeoPortfolio $portfolio, array $memberIds): int
    {
        if (empty($memberIds)) {
            return 0;
        }

        $units = SeoAnswerUnit::whereIn('url_id', $memberIds)->with('entity')->get();
        if ($units->isEmpty()) {
            return 0;
        }

        $presence = [];
        foreach (SeoAnswerPresence::whereIn('answer_unit_id', $units->pluck('id'))
            ->orderByDesc('checked_at')->get() as $p) {
            $presence[$p->answer_unit_id][$p->surface] ??= $p;
        }

        $staleDays = (int) config('seo.answer_stale_days', 90);
        $created = 0;

        foreach ($units as $u) {
            $label = $u->entity->name ?? 'Baustein';

            if ($u->verified_at && $u->verified_at->lt(now()->subDays($staleDays))) {
                $created += $this->upsert($portfolio, [
                    'type' => 'change_page',
                    'target_url_id' => $u->url_id,
                    'title' => 'Antwort auffrischen: '.$label,
                    'rationale' => 'Antwort-Einheit älter als '.$staleDays.' Tage — Aktualität zählt beim KI-Zitat, auffrischen.',
                    'source_key' => 'stale:au:'.$u->id,
                    'score' => 200,
                    'route' => 'flynk',
                ]);
            }

            $serp = $presence[$u->id]['serp'] ?? null;
            $aiCited = (($presence[$u->id]['ai_overview'] ?? null)?->cited)
                || (($presence[$u->id]['chatgpt'] ?? null)?->cited);
            if ($serp && $serp->present && ! $aiCited) {
                $created += $this->upsert($portfolio, [
                    'type' => 'change_page',
                    'target_url_id' => $u->url_id,
                    'title' => 'KI-Präsenz aufbauen: '.$label,
                    'rationale' => 'Klassisch präsent (SERP #'.($serp->position ?? '?').'), aber in der KI-Antwort nicht zitiert — Antwort zitierfähiger machen (schema.org, klare Claims).',
                    'source_key' => 'geo:au:'.$u->id,
                    'score' => 300,
                    'route' => 'flynk',
                ]);
            }
        }

        return $created;
    }

    /**
     * KI-vorgeschlagene, typisierte Maßnahmen in den Posteingang (source=ai).
     *
     * @param  array<int, array>  $aiMeasures
     */
    public function fromAi(SeoPortfolio $portfolio, array $aiMeasures): int
    {
        $validTypes = array_keys((array) config('seo.measure_types', []));
        $created = 0;

        foreach ($aiMeasures as $m) {
            $type = $m['type'] ?? null;
            $target = trim((string) ($m['target'] ?? ''));
            if (! in_array($type, $validTypes, true) || $target === '') {
                continue;
            }
            $created += $this->upsert($portfolio, [
                'type' => $type,
                'title' => mb_substr($target, 0, 200),
                'rationale' => trim((string) ($m['rationale'] ?? '')),
                'source' => 'ai',
                'source_key' => 'ai:'.md5($type.'|'.mb_strtolower($target)),
                'score' => (int) ($m['value'] ?? 100),
                'route' => (string) config('seo.measure_types.'.$type.'.route', 'internal'),
            ]);
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
