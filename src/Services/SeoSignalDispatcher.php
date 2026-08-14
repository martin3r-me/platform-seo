<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoContentBriefNote;
use Platform\Seo\Models\SeoContentBriefSection;
use Platform\Seo\Models\SeoSignal;

/**
 * Routing-Dispatcher (docs/SIGNALS-CONCEPT.md §4 Arbeitskette): macht aus einem
 * zugelassenen Signal das richtige Arbeitsobjekt.
 *
 * Bewusst getrennt vom Evaluator (Detect ≠ Dispatch). Idempotent über context['work'].
 *  - content → Content-Brief (SEO-intern, mit Sections aus dem KI-Outline)
 *  - page_edit/structural → fließen über den Flynk-Push (SeoFlynkContextProvider);
 *    ausgehende Task-Erzeugung bewusst noch nicht hier (erst wenn geklärt, wie Flynk
 *    Pushes zu Aufgaben macht).
 */
class SeoSignalDispatcher
{
    /**
     * @return array{dispatched:int, considered:int, by_target:array<string,int>}
     */
    public function dispatchTeam(int $teamId, int $limit = 20): array
    {
        $signals = SeoSignal::with(['url', 'keyword'])
            ->where('team_id', $teamId)
            ->whereNotNull('signal_definition_id')
            ->whereIn('status', ['new', 'acknowledged'])
            ->orderByDesc('detected_at')
            ->get()
            ->filter(fn ($s) => empty($s->context['work'] ?? null)) // noch nicht dispatcht
            ->take($limit);

        $created = 0;
        $byTarget = [];
        foreach ($signals as $signal) {
            $target = SeoSignalRouting::targetForPattern($signal->signal_type);

            if ($target === SeoSignalRouting::TARGET_CONTENT_BRIEF) {
                $brief = $this->createBrief($signal);
                if ($brief) {
                    $this->markWork($signal, 'content_brief', $brief->id, $brief->uuid);
                    $created++;
                    $byTarget['content_brief'] = ($byTarget['content_brief'] ?? 0) + 1;
                }
            }
            // page_edit / structural: über den Flynk-Push, hier (noch) keine Erzeugung.
        }

        return ['dispatched' => $created, 'considered' => $signals->count(), 'by_target' => $byTarget];
    }

    protected function createBrief(SeoSignal $signal): ?SeoContentBrief
    {
        $ctx = $signal->context ?? [];
        $ai = $ctx['ai'] ?? [];
        $kw = $ctx['keyword'] ?? ($signal->keyword->keyword ?? null);
        $teamId = (int) $signal->team_id;

        $brief = SeoContentBrief::create([
            'team_id' => $teamId,
            'name' => $kw ? "Content-Brief: {$kw}" : ('Content-Brief zu Signal #'.$signal->id),
            'description' => $ai['recommendation'] ?? $signal->description,
            'content_type' => 'guide',
            'search_intent' => 'informational',
            'status' => 'briefed',
            'target_url' => $signal->url?->url,
        ]);

        // Sections aus dem KI-Brief-Umriss (H2-Vorschläge).
        $outline = (isset($ai['brief_outline']) && is_array($ai['brief_outline'])) ? array_values($ai['brief_outline']) : [];
        foreach ($outline as $i => $h2) {
            $heading = is_string($h2) ? $h2 : (string) ($h2['heading'] ?? $h2['text'] ?? '');
            if ($heading === '') {
                continue;
            }
            SeoContentBriefSection::create([
                'content_brief_id' => $brief->id,
                'team_id' => $teamId,
                'order' => $i,
                'heading' => $heading,
                'heading_level' => 'h2',
                'target_keywords' => $kw ? [$kw] : null,
            ]);
        }

        // Instruktion aus KI-Empfehlung + Schritten.
        $lines = [];
        if (! empty($ai['recommendation'])) {
            $lines[] = $ai['recommendation'];
        }
        foreach (($ai['steps'] ?? []) as $step) {
            if (is_string($step)) {
                $lines[] = '• '.$step;
            }
        }
        if (! empty($lines)) {
            SeoContentBriefNote::create([
                'content_brief_id' => $brief->id,
                'team_id' => $teamId,
                'note_type' => 'instruction',
                'content' => implode("\n", $lines),
                'order' => 0,
            ]);
        }

        return $brief;
    }

    protected function markWork(SeoSignal $signal, string $type, ?int $id, ?string $uuid): void
    {
        $ctx = $signal->context ?? [];
        $ctx['work'] = array_filter([
            'type' => $type,
            'id' => $id,
            'uuid' => $uuid,
            'dispatched_at' => Carbon::now()->toIso8601String(),
        ], fn ($v) => $v !== null);
        $signal->context = $ctx;
        $signal->save();
    }
}
