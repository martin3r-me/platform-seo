<?php

namespace Platform\Seo\Services;

use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoPortfolio;

/**
 * KI-Anreicherung des Posteingangs: bekommt den Zustand eines Wirkungsraums
 * (Board, Entitäten, Präsenz, Share of Answer) und gibt STRUKTURIERTE, typisierte
 * Maßnahmen zurück — keine Text-Wolke, sondern Inbox-Items im Standard-Vokabular.
 * So fließt die KI-Aussteuerung durch dieselbe Pipe wie die deterministischen
 * Signale (annehmen → Queue → Flynk / ablehnen+Grund → Kontext).
 */
class SeoMeasureAiAdvisor
{
    public function __construct(private LLMProviderRegistry $registry) {}

    /**
     * @return array<int, array{type:string, target:string, rationale:string, value?:int}>
     */
    public function propose(SeoPortfolio $portfolio, array $facets): array
    {
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return [];
        }

        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($portfolio, $facets)]],
                ['system' => $this->systemPrompt(), 'max_tokens' => 900, 'tools' => false],
            );

            return $this->parse((string) ($resp['content'] ?? ''));
        } catch (\Throwable $e) {
            return [];
        }
    }

    protected function systemPrompt(): string
    {
        $types = collect(config('seo.measure_types'))->map(fn ($v, $k) => "$k ({$v['label']})")->implode(', ');

        return <<<TXT
Du bist SEO-Portfolio-Stratege für einen "Wirkungsraum" — einen Verbund
KONTROLLIERTER Web-Properties, die gemeinsam maximale Sichtbarkeit erreichen
sollen — klassisch UND in KI-Antworten (Share of Answer).

Aus dem gelieferten Zustand leitest du konkrete Maßnahmen ab. Antworte
AUSSCHLIESSLICH als JSON: {"measures": [ {"type": "...", "target": "...", "rationale": "...", "expected": "...", "value": 0-1000} ]}

- type: einer von $types
- target: WO — kurzer, konkreter Bezug (Thema/Entität/Property), max ~60 Zeichen
- rationale: WARUM — 1 knapper deutscher Satz, verweise auf den gelieferten Zustand
- expected: ERWARTETES ERGEBNIS — 1 knapper Satz, was die Maßnahme bewirken soll
- value: geschätzter Wert/Dringlichkeit 0–1000
- Max 6 Maßnahmen, die WERTVOLLSTEN. Nichts erfinden — nur aus dem Zustand ableiten.
- Priorisiere GEO-Chancen (klassisch präsent, aber KI-nicht-zitiert) und
  Kannibalisierung/Owner, wo der Zustand das zeigt.
TXT;
    }

    protected function userPrompt(SeoPortfolio $portfolio, array $facets): string
    {
        $lines = ['WIRKUNGSRAUM: '.$portfolio->name];
        if ($portfolio->goal) {
            $lines[] = 'ZIEL: '.$portfolio->goal;
        }

        $board = $facets['board'] ?? [];
        if (! empty($board)) {
            $lines[] = "\nTHEMEN (Cluster · Nachfrage · Kandidaten · Owner · Status):";
            foreach (array_slice($board, 0, 15) as $r) {
                $status = ! empty($r['conflict']) ? 'KONFLIKT('.($r['candidate_count'] ?? 0).')'
                    : (! empty($r['pillar_candidate']) ? 'PILLAR-KANDIDAT'
                    : (! empty($r['owner_id']) ? 'Owner gesetzt' : 'frei'));
                $lines[] = '- '.$r['name'].' · Vol '.($r['demand'] ?? 0).' · '.$status;
            }
        }

        $entities = $facets['entities']['rows'] ?? [];
        if (! empty($entities)) {
            $lines[] = "\nENTITÄTEN (Präsenz — SERP / AI):";
            foreach (array_slice($entities, 0, 20) as $e) {
                $lines[] = '- '.$e['name'].' · SERP '.($e['serp'] ? '#'.($e['serp_pos'] ?? '?') : 'nein').' · AI '.($e['ai'] ? 'zitiert' : 'NICHT zitiert');
            }
            if (($facets['entities']['share'] ?? null) !== null) {
                $lines[] = 'SHARE OF ANSWER: '.$facets['entities']['share'].'% ('.$facets['entities']['present'].'/'.$facets['entities']['total'].' präsent)';
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<int, array>
     */
    protected function parse(string $text): array
    {
        if ($text === '') {
            return [];
        }
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (! is_array($data) || ! isset($data['measures']) || ! is_array($data['measures'])) {
            return [];
        }

        return $data['measures'];
    }
}
