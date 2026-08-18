<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Seo\Models\SeoAnswerPresence;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoUrl;

/**
 * Presence-Probe (v2, docs/NORDSTERN-v2.md). Schreibt je Antwort-Einheit einen
 * Präsenz-Messpunkt pro Surface — als Zeitreihe (seo_answer_presence).
 *
 * v1 leitet ehrlich aus Daten ab, die wir schon zuverlässig haben:
 *  - SERP  = beste Ranking-Position der Host-URL (Pivot; sonst GSC-Ø-Position).
 *  - AI    = llm_mentions der Host-URL (echte AI-Sichtbarkeit vom bestehenden
 *            LlmMentions-Collector) → present/cited.
 * Aktives Per-Engine-Zitat-Probing je Entität (der „neue Rank-Check") ist die
 * nächste Tiefenstufe; share_of_answer (Wettbewerbs-Vergleich) folgt dann.
 */
class SeoPresenceProbe
{
    /** @return int geschriebene Messpunkte */
    public function forUrl(SeoUrl $url): int
    {
        $units = SeoAnswerUnit::where('url_id', $url->id)->get(['id', 'entity_id']);
        if ($units->isEmpty()) {
            return 0;
        }

        // SERP: beste Position der URL (Ranking-Pivot), sonst GSC-Ø-Position.
        $bestPos = DB::table('seo_url_keywords')
            ->where('url_id', $url->id)
            ->whereNotNull('position')
            ->min('position');
        $serpPos = $bestPos !== null
            ? (int) $bestPos
            : ($url->gsc_avg_position ? (int) round((float) $url->gsc_avg_position) : null);
        $serpPresent = $serpPos !== null || (float) $url->visibility_score > 0;

        // AI: aus llm_mentions (echte AI-Sichtbarkeit).
        $aiCited = (int) $url->llm_mentions > 0;

        $now = now();
        $written = 0;

        foreach ($units as $u) {
            SeoAnswerPresence::create([
                'team_id' => $url->team_id,
                'entity_id' => $u->entity_id,
                'answer_unit_id' => $u->id,
                'surface' => 'serp',
                'present' => $serpPresent,
                'position' => $serpPos,
                'cited' => false,
                'checked_at' => $now,
            ]);
            SeoAnswerPresence::create([
                'team_id' => $url->team_id,
                'entity_id' => $u->entity_id,
                'answer_unit_id' => $u->id,
                'surface' => 'ai_overview',
                'present' => $aiCited,
                'cited' => $aiCited,
                'checked_at' => $now,
            ]);
            $written += 2;
        }

        return $written;
    }
}
