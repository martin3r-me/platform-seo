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
    public function enrichTeam(int $teamId, int $limit = 20, bool $force = false): array
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

        $signals = SeoSignal::with(['url.onPage', 'keyword'])
            ->where('team_id', $teamId)
            ->whereIn('signal_definition_id', $defIds)
            ->whereIn('status', ['new', 'acknowledged'])
            ->orderByDesc('detected_at')
            ->get()
            ->filter(fn ($s) => $force || empty(($s->context['ai'] ?? null)))
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
        // Kein Live-Sprung auf die Seite — nur der (frische) gespeicherte Crawl erdet.
        $pageBlock = $signal->url ? $this->pageState($signal->url) : null;

        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($signal, $pageBlock)]],
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
                'grounded' => $pageBlock !== null,
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

        Wenn der aktuelle Seitenstand (Title, H1, Überschriften, Meta) mitgegeben ist, beziehe
        dich KONKRET darauf: nenne, was bereits passt und was genau zu ändern ist — z. B. ein
        konkreter Title-/H1-Vorschlag statt allgemeiner Ratschläge. Erfinde keine Inhalte, die
        nicht plausibel zur Seite passen. Ohne Seitenstand gib die beste Empfehlung auf Basis
        von Keyword und Position.

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

    protected function userPrompt(SeoSignal $signal, ?string $pageBlock = null): string
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

        if ($pageBlock) {
            $lines[] = $pageBlock;
        }

        return implode("\n", $lines);
    }

    /**
     * Realer Seitenstand aus dem Crawl (seo_url_on_page) — erdet die KI-Empfehlung.
     * Nur FRISCHE Crawl-Daten (analyzed_at innerhalb onpage_fresh_days); sonst null
     * → kein Seitenbezug, statt auf veraltetem Stand zu raten.
     */
    protected function pageState($url): ?string
    {
        $op = $url->onPage ?? null;
        if (! $op) {
            return null;
        }

        $freshDays = (int) config('seo.signals.onpage_fresh_days', 21);
        if (! $op->analyzed_at || $op->analyzed_at->lt(Carbon::now()->subDays($freshDays))) {
            return null; // Crawl zu alt → nicht erden
        }

        $lines = ['', 'Aktueller Seitenstand (Crawl vom '.$op->analyzed_at->format('d.m.Y').'):'];
        if ($op->title) {
            $lines[] = 'Title: '.$op->title;
        }
        if ($op->h1) {
            $lines[] = 'H1: '.$op->h1;
        }
        if ($op->meta_description) {
            $lines[] = 'Meta-Description: '.$op->meta_description;
        }
        if (! empty($op->headings) && is_array($op->headings)) {
            $hs = array_slice(array_map(
                fn ($h) => 'H'.($h['level'] ?? '?').': '.($h['text'] ?? ''),
                $op->headings
            ), 0, 15);
            $lines[] = 'Überschriften: '.implode(' | ', $hs);
        }
        if ($op->word_count !== null) {
            $lines[] = 'Wortzahl: '.$op->word_count;
        }
        if (! empty($op->issues) && is_array($op->issues)) {
            $lines[] = 'Erkannte Issues: '.count($op->issues);
        }

        return count($lines) > 2 ? implode("\n", $lines) : null;
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
