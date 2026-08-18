<?php

namespace Platform\Seo\Services;

use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoUrl;

/**
 * Der Steckbrief-Vorschlag: liest, was wir über eine Seite WISSEN (On-Page-Titel/
 * H1/Text, rankende Keywords + deren Intent, Cluster) und leitet das erklärte SOLL
 * ab — Seitentyp, Ziel-Intent, Funnel-Stufe, Seitenziel, Fokus-Thema.
 *
 * Nordstern: die KI schlägt vor, der Mensch bestätigt. Ausgabe ist strikt gegen
 * die standardisierten Vokabulare (config('seo.steckbrief')) validiert, damit die
 * Felder maschinen-nutzbar bleiben (Alignment-Checks, Priorisierung, schema.org).
 */
class SeoUrlMetaAdvisor
{
    public function __construct(private LLMProviderRegistry $registry) {}

    /**
     * @return array{page_type:?string,target_intent:?string,funnel_stage:?string,page_objective:?string,focus_keyword:?string,rationale:?string,error?:string}
     */
    public function propose(SeoUrl $url): array
    {
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['error' => 'Kein KI-Provider verfügbar (services.openai.api_key gesetzt?).'];
        }

        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($url)]],
                ['system' => $this->systemPrompt(), 'max_tokens' => 500, 'tools' => false],
            );
            $text = trim((string) ($resp['content'] ?? ''));
            if ($text === '') {
                return ['error' => 'Leere KI-Antwort.'];
            }

            return $this->parse($text);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /** Signale sammeln: On-Page + Top-Keywords + Cluster. */
    protected function signals(SeoUrl $url): array
    {
        $onPage = $url->onPage;

        $keywords = SeoKeyword::whereHas('urls', fn ($q) => $q->where('seo_url_keywords.url_id', $url->id))
            ->with(['urls' => fn ($q) => $q->where('seo_url_keywords.url_id', $url->id), 'cluster'])
            ->get();

        // Top nach Position (rankt gut zuerst), dann Volumen.
        $top = $keywords
            ->sortBy(fn ($kw) => $kw->urls->min('pivot.position') ?? 999)
            ->take(15)
            ->map(fn ($kw) => [
                'kw' => $kw->keyword,
                'pos' => $kw->urls->min('pivot.position'),
                'vol' => (int) $kw->search_volume,
                'intent' => $kw->search_intent,
            ])
            ->values()
            ->all();

        $clusters = $keywords->pluck('cluster.name')->filter()->unique()->take(5)->values()->all();

        return [
            'url' => $url->url,
            'title' => $onPage->title ?? null,
            'h1' => $onPage->h1 ?? null,
            'description' => $onPage->meta_description ?? null,
            'word_count' => $onPage->word_count ?? null,
            'keywords' => $top,
            'clusters' => $clusters,
        ];
    }

    protected function systemPrompt(): string
    {
        $st = config('seo.steckbrief');
        $types = collect($st['page_types'])->map(fn ($v, $k) => "$k ({$v['label']})")->implode(', ');
        $intents = collect($st['intents'])->map(fn ($v, $k) => "$k ($v)")->implode(', ');
        $funnels = collect($st['funnel_stages'])->map(fn ($v, $k) => "$k ($v)")->implode(', ');
        $objectives = collect($st['objectives'])->map(fn ($v, $k) => "$k ($v)")->implode(', ');

        return <<<TXT
Du bist SEO-Analyst. Du erstellst den STECKBRIEF einer Webseite — ihr erklärtes
SOLL — aus dem, was über die Seite bekannt ist (Titel, H1, Text, rankende Keywords).

Antworte AUSSCHLIESSLICH als JSON-Objekt, keine Erklärung davor/danach:
{"page_type": "...", "target_intent": "...", "funnel_stage": "...", "page_objective": "...", "focus_keyword": "...", "rationale": "..."}

Erlaubte Werte (nutze NUR den Schlüssel links, exakt):
- page_type: $types
- target_intent: $intents
- funnel_stage: $funnels
- page_objective: $objectives
- focus_keyword: das EINE Thema/Keyword, das diese Seite besitzen soll (kurzer String)
- rationale: 1 knapper deutscher Satz, warum diese Einordnung.

Regeln:
- Wähle den Seitentyp nach Inhalt/Zweck, nicht nach Ranking-Zufall.
- target_intent = die Absicht, die die Seite BEDIENEN soll (nicht zwingend der Ist-Ranking-Intent).
- Bei Unsicherheit die plausibelste Option wählen, nie einen Wert erfinden.
TXT;
    }

    protected function userPrompt(SeoUrl $url): string
    {
        $s = $this->signals($url);
        $kw = collect($s['keywords'])
            ->map(fn ($k) => "- {$k['kw']}".($k['pos'] ? " (Pos {$k['pos']}" : ' (—')." · Vol {$k['vol']}".($k['intent'] ? " · {$k['intent']}" : '').')')
            ->implode("\n");

        return "SEITE: {$s['url']}\n"
            ."TITEL: ".($s['title'] ?? '—')."\n"
            ."H1: ".($s['h1'] ?? '—')."\n"
            ."META-BESCHREIBUNG: ".($s['description'] ?? '—')."\n"
            ."WORTZAHL: ".($s['word_count'] ?? '—')."\n"
            .(! empty($s['clusters']) ? 'THEMEN-CLUSTER: '.implode(', ', $s['clusters'])."\n" : '')
            ."\nRANKENDE KEYWORDS (Top nach Position):\n".($kw ?: '— keine —');
    }

    /** JSON robust parsen + strikt gegen das Vokabular validieren. */
    protected function parse(string $text): array
    {
        // Code-Fences / Prosa entfernen: das erste {...} greifen.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }
        $data = json_decode($text, true);
        if (! is_array($data)) {
            return ['error' => 'KI-Antwort nicht als JSON lesbar.'];
        }

        $st = config('seo.steckbrief');
        $pick = function (?string $val, array $allowed): ?string {
            $val = is_string($val) ? trim($val) : null;

            return ($val !== null && $val !== '' && array_key_exists($val, $allowed)) ? $val : null;
        };

        return [
            'page_type' => $pick($data['page_type'] ?? null, $st['page_types']),
            'target_intent' => $pick($data['target_intent'] ?? null, $st['intents']),
            'funnel_stage' => $pick($data['funnel_stage'] ?? null, $st['funnel_stages']),
            'page_objective' => $pick($data['page_objective'] ?? null, $st['objectives']),
            'focus_keyword' => is_string($data['focus_keyword'] ?? null) ? trim($data['focus_keyword']) : null,
            'rationale' => is_string($data['rationale'] ?? null) ? trim($data['rationale']) : null,
        ];
    }
}
