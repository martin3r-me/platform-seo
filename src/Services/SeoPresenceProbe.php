<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoAnswerPresence;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoEntity;
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
    public function __construct(private LLMProviderRegistry $registry) {}

    /**
     * Aktives AI-Zitat-Probing je Entität: fragt die Antwort-Maschine nach dem
     * Thema und prüft, ob eine unserer Domains/Marken in der Antwort auftaucht →
     * present/cited (Surface chatgpt). EHRLICH: Modell-Wissen, kein Live-Web —
     * ein GEO-Frühindikator, nicht die endgültige Live-Zitat-Wahrheit.
     *
     * @param  string[]  $ownDomains
     * @return array{cited?:bool, error?:string}
     */
    public function probeAiCitation(SeoEntity $entity, array $ownDomains): array
    {
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['error' => 'Kein KI-Provider verfügbar.'];
        }

        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => "Thema/Frage: {$entity->name}\nNenne konkret die maßgeblichen Marken, Anbieter und Websites dazu."]],
                ['system' => 'Du bist eine Antwort-Maschine. Nenne die relevanten Marken/Anbieter/Websites konkret beim Namen.', 'max_tokens' => 400, 'tools' => false],
            );
            $text = mb_strtolower((string) ($resp['content'] ?? ''));
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }

        $cited = false;
        foreach ($ownDomains as $d) {
            $bare = preg_replace('/^www\./', '', strtolower(trim($d)));
            if ($bare === '') {
                continue;
            }
            $brand = explode('.', $bare)[0] ?? $bare;
            if (str_contains($text, $bare) || (strlen($brand) >= 4 && str_contains($text, $brand))) {
                $cited = true;
                break;
            }
        }

        $unit = SeoAnswerUnit::where('entity_id', $entity->id)->first();
        SeoAnswerPresence::create([
            'team_id' => $entity->team_id,
            'entity_id' => $entity->id,
            'answer_unit_id' => $unit?->id,
            'surface' => 'chatgpt',
            'present' => $cited,
            'cited' => $cited,
            'checked_at' => now(),
        ]);

        return ['cited' => $cited];
    }

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
