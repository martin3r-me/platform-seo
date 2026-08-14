<?php

namespace Platform\Seo\Services;

use Carbon\Carbon;
use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoSignalDefinition;

/**
 * KI-Anreicherung berechneter Signale (Schritt 4, Rolle 1 — docs/SIGNALS-CONCEPT.md §6a).
 *
 * Nimmt ein berechnetes Signal + Kontext und lässt die generative KI (core's
 * LLMProviderRegistry) eine konkrete Handlungsanweisung schreiben — optional inkl.
 * Content-Brief-Umriss. Ergebnis landet in signal.context['ai']. Sparte B, klar
 * abgegrenzt: das berechnete Signal bleibt das Gerüst, die KI schreibt das „wie".
 */
class SeoSignalEnrichmentService
{
    public ?string $lastError = null;

    public function __construct(private LLMProviderRegistry $registry) {}

    /**
     * Reichert offene, noch nicht angereicherte Signale von enrich-aktiven Definitionen an.
     *
     * @return array{enriched:int, skipped:int, error?:string}
     */
    public function enrichTeam(int $teamId, int $limit = 20): array
    {
        $defIds = SeoSignalDefinition::where('team_id', $teamId)
            ->where('enrich_with_ai', true)
            ->pluck('id');

        if ($defIds->isEmpty()) {
            return ['enriched' => 0, 'skipped' => 0];
        }

        // Bewusst OpenAI (der Default zeigt bei uns auf Anthropic mit ungültigem Key).
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['enriched' => 0, 'skipped' => 0, 'error' => 'Kein OpenAI-Provider verfügbar (services.openai.api_key gesetzt?).'];
        }

        $signals = SeoSignal::with(['url', 'keyword'])
            ->where('team_id', $teamId)
            ->whereIn('signal_definition_id', $defIds)
            ->whereIn('status', ['new', 'acknowledged'])
            ->orderByDesc('detected_at')
            ->get()
            ->filter(fn ($s) => empty(($s->context['ai'] ?? null)))
            ->take($limit);

        $enriched = 0;
        foreach ($signals as $signal) {
            if ($this->enrichSignal($signal, $provider)) {
                $enriched++;
            }
        }

        $out = ['enriched' => $enriched, 'skipped' => $signals->count() - $enriched];
        if ($enriched === 0 && $signals->count() > 0 && $this->lastError) {
            $out['error'] = $this->lastError;
        }

        return $out;
    }

    public function enrichSignal(SeoSignal $signal, $provider): bool
    {
        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($signal)]],
                [
                    'system' => $this->systemPrompt(),
                    'max_tokens' => 700,
                    'tools' => false, // keine Tool-Orchestrierung — schlanker Direkt-Call
                ],
            );

            $ai = $this->parseJson($resp['content'] ?? '');
            if (! $ai) {
                $this->lastError = 'Antwort nicht als JSON parsebar: '.substr(trim((string) ($resp['content'] ?? '')), 0, 200);

                return false;
            }

            $ctx = $signal->context ?? [];
            $ctx['ai'] = array_merge($ai, [
                'model' => $resp['model'] ?? null,
                'enriched_at' => Carbon::now()->toIso8601String(),
            ]);
            $signal->context = $ctx;
            $signal->save();

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();

            return false;
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'TXT'
        Du bist ein erfahrener SEO-Stratege in einer Agentur. Zu einem bereits erkannten
        SEO-Signal lieferst du eine konkrete, sofort umsetzbare Handlungsempfehlung — kein
        Allgemeinplatz, sondern spezifisch für die genannte URL und das Keyword.

        Antworte AUSSCHLIESSLICH mit einem JSON-Objekt, ohne Markdown, mit den Feldern:
        {
          "recommendation": "ein Satz: der wichtigste nächste Schritt",
          "steps": ["konkrete Aktion", "konkrete Aktion", ...],   // 2–4 Schritte
          "reasoning": "kurz: warum das jetzt der Hebel ist",
          "brief_outline": ["H2-Vorschlag", ...]   // NUR bei Content-Themen (thin_content), sonst weglassen
        }
        Schreibe auf Deutsch, knapp und präzise.
        TXT;
    }

    protected function userPrompt(SeoSignal $signal): string
    {
        $ctx = $signal->context ?? [];
        $lines = [
            'Signal: '.$signal->title,
            'Muster: '.$signal->signal_type,
            'Beschreibung: '.($signal->description ?? '—'),
        ];
        if ($signal->url) {
            $lines[] = 'URL: '.$signal->url->url;
        }
        $kw = $ctx['keyword'] ?? ($signal->keyword->keyword ?? null);
        if ($kw) {
            $lines[] = 'Keyword: '.$kw;
        }
        foreach (['volume' => 'Suchvolumen', 'position' => 'Position', 'drop' => 'Positionsabfall', 'word_count' => 'Wortzahl', 'url_count' => 'Anzahl konkurrierender URLs'] as $key => $label) {
            if (isset($ctx[$key]) && $ctx[$key] !== null) {
                $lines[] = $label.': '.$ctx[$key];
            }
        }

        return implode("\n", $lines);
    }

    /** Robustes JSON-Parsing (entfernt evtl. Code-Fences). */
    protected function parseJson(string $content): ?array
    {
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?|```$/m', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);
        if (is_array($decoded) && isset($decoded['recommendation'])) {
            return $decoded;
        }

        return null;
    }
}
