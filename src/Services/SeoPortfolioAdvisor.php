<?php

namespace Platform\Seo\Services;

use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoPortfolio;

/**
 * Die KI-Klammer: liest den Zustand eines Wirkungsraums (Mitglieder,
 * Durchdringung IST/SOLL, ungeclusterter Rest, Wettbewerber) und schlägt die
 * Aussteuerung vor — wer besetzt welches Thema, wo splitten (Anti-Kannibali-
 * sierung), was cross-linken, was clustern, wo Lücken schließen. Nordstern:
 * maximale gemeinsame Sichtbarkeit im Verbund. Siehe docs/WIRKUNGSRAUM-CONCEPT.md.
 */
class SeoPortfolioAdvisor
{
    public function __construct(private LLMProviderRegistry $registry) {}

    public function advise(SeoPortfolio $portfolio, array $facets): array
    {
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['error' => 'Kein KI-Provider verfügbar (services.openai.api_key gesetzt?).'];
        }

        try {
            $resp = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($portfolio, $facets)]],
                ['system' => $this->systemPrompt(), 'max_tokens' => 1300, 'tools' => false],
            );

            $text = trim((string) ($resp['content'] ?? ''));
            if ($text === '') {
                return ['error' => 'Leere KI-Antwort.'];
            }

            return ['text' => $text, 'model' => $resp['model'] ?? null];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'TXT'
Du bist SEO-Portfolio-Stratege für einen "Wirkungsraum" — einen Verbund
KONTROLLIERTER eigener Web-Properties, die gemeinsam ausgesteuert werden.

Nordstern: MAXIMALE GEMEINSAME SICHTBARKEIT im Verbund (nicht je Einzelseite).

Eherne Regeln:
- Ein Thema (Cluster) = EINE Owner-Seite. Zwei eigene Seiten im selben Thema =
  Kannibalisierung → auflösen (differenzieren oder konsolidieren).
- Ein gemeinsames Thema in differenzierte Einzel-Owner-Cluster SPLITTEN und
  untereinander VERLINKEN (Pillar↔Spokes über die Seiten) → gemeinsam dominieren.
- SOLL ohne IST (Ziel, rankt nicht) = Lücke → Content/Owner. IST ohne Cluster
  (wild rankend) = ungeordnet → clustern. IST hoch = verteidigen.
- Immer gegen die Wettbewerber messen: wo überholen sie uns, wo ist Boden gut zu machen.

Antworte auf Deutsch, in knappem Markdown. Struktur:
1. **Lage** (2-3 Sätze: wo steht der Verbund?)
2. **Aussteuerung** (priorisierte, konkrete Maßnahmen — je Punkt: was, welche URL/Cluster, warum)
3. **Ordnen** (was aus dem ungeclusterten Rest zu clustern ist)
Keine Floskeln, keine generischen SEO-Tipps. Nur was aus DIESEN Daten folgt.
TXT;
    }

    protected function userPrompt(SeoPortfolio $portfolio, array $facets): string
    {
        $out = "WIRKUNGSRAUM: {$portfolio->name}\n";
        $out .= 'ZIEL: ' . ($portfolio->goal ?: '(kein Ziel gesetzt)') . "\n\n";

        $out .= "MITGLIEDER (kontrollierte URLs, mit eigener Sichtbarkeit):\n";
        foreach (($facets['members'] ?? []) as $m) {
            $out .= "- {$m['url']} — {$m['keywords']} KW, Sichtbarkeit {$m['visibility']}\n";
        }

        $out .= "\nDURCHDRINGUNG je Cluster (SOLL = Ziel-KW, IST = davon rankend):\n";
        if (empty($facets['penetration'])) {
            $out .= "(keine Cluster zugeordnet)\n";
        }
        foreach (($facets['penetration'] ?? []) as $c) {
            $out .= "- {$c['name']}: SOLL {$c['soll']}, IST {$c['ist']} ({$c['pct']}%), Volumen {$c['volume']}\n";
        }

        if (! empty($facets['unclustered'])) {
            $u = $facets['unclustered'];
            $out .= "\nUNGECLUSTERTER REST: {$u['soll']} Keywords, davon {$u['ist']} wild rankend (Volumen {$u['volume']}).\n";
        }

        $out .= "\nWETTBEWERBER (gemeinsame Keywords / deren Sichtbarkeit; Verbund-Sichtbarkeit = {$facets['own_visibility']}):\n";
        foreach (($facets['competitors'] ?? []) as $c) {
            $out .= "- {$c['domain']} — {$c['shared']} gemeinsame KW, Sichtbarkeit {$c['visibility']}\n";
        }

        $out .= "\nSchlage die Aussteuerung vor.";

        return $out;
    }
}
