<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\Http;
use Platform\Core\Services\LLMProviderRegistry;
use Platform\Seo\Models\SeoAnswerUnit;
use Platform\Seo\Models\SeoEntity;
use Platform\Seo\Models\SeoUrl;

/**
 * Content → Antwort-Einheit-Extraktion (v2, docs/NORDSTERN-v2.md). Holt den
 * echten Seiteninhalt, liest strukturierte Daten (JSON-LD) + Fließtext und lässt
 * die KI daraus atomare Antwort-Einheiten ableiten: je Entität/Frage ein Claim
 * mit schema_type. Das füllt die v2-Spine (seo_entities + seo_answer_units) —
 * die Vergleichsbasis, gegen die später optimiert/experimentiert wird.
 */
class SeoAnswerExtractor
{
    private const TEXT_CAP = 6000;

    public function __construct(private LLMProviderRegistry $registry) {}

    /**
     * @return array{created?:int, entities?:int, error?:string}
     */
    public function extractForUrl(SeoUrl $url, ?int $portfolioId = null): array
    {
        // 1 · Seite holen
        try {
            $resp = Http::withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; BHG-SEO/1.0; +https://bhgdigital.de)'])
                ->timeout(15)->get($url->url);
            $html = $resp->successful() ? (string) $resp->body() : '';
        } catch (\Throwable $e) {
            return ['error' => 'Seite nicht abrufbar: '.$e->getMessage()];
        }
        if (trim($html) === '') {
            return ['error' => 'Leere/unerreichbare Antwort von der Seite.'];
        }

        // 2 · Parsen: strukturierte Daten (JSON-LD) + Fließtext
        $schemaTypes = $this->jsonLdTypes($html);
        $text = $this->plainText($html);
        if ($text === '') {
            return ['error' => 'Kein lesbarer Textinhalt gefunden (evtl. JS-gerendert).'];
        }

        // 3 · KI leitet Antwort-Einheiten ab
        $provider = $this->registry->get('openai') ?? $this->registry->getDefaultProvider();
        if (! $provider || ! $provider->isAvailable()) {
            return ['error' => 'Kein KI-Provider verfügbar (services.openai.api_key gesetzt?).'];
        }

        try {
            $out = $provider->chat(
                [['role' => 'user', 'content' => $this->userPrompt($url, $text, $schemaTypes)]],
                ['system' => $this->systemPrompt(), 'max_tokens' => 900, 'tools' => false],
            );
            $units = $this->parse((string) ($out['content'] ?? ''));
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
        if (isset($units['error'])) {
            return $units;
        }
        if (empty($units)) {
            return ['created' => 0, 'entities' => 0];
        }

        // 4 · Speichern (Entität upsert + Antwort-Einheit je URL×Entität)
        return $this->store($url, $portfolioId, $units, md5($text));
    }

    /** @return string[] schema.org @type-Werte aus JSON-LD */
    protected function jsonLdTypes(string $html): array
    {
        $types = [];
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/is', $html, $m)) {
            foreach ($m[1] as $block) {
                $data = json_decode(trim($block), true);
                if (! is_array($data)) {
                    continue;
                }
                array_walk_recursive($data, function ($v, $k) use (&$types) {
                    if ($k === '@type' && is_string($v)) {
                        $types[] = $v;
                    }
                });
            }
        }

        return array_values(array_unique($types));
    }

    protected function plainText(string $html): string
    {
        // Skripte/Styles raus, Tags strippen, Whitespace kollabieren, kappen.
        $html = preg_replace('/<(script|style|noscript|svg)\b[^>]*>.*?<\/\1>/is', ' ', $html) ?? $html;
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($html)) ?? '');

        return mb_substr($text, 0, self::TEXT_CAP);
    }

    protected function systemPrompt(): string
    {
        $types = collect(config('seo.entity_types'))->map(fn ($v, $k) => "$k ($v)")->implode(', ');

        return <<<TXT
Du zerlegst eine Webseite in atomare ANTWORT-EINHEITEN — je eine Frage/Entität,
die die Seite beantwortet, mit dem Kern-Claim. Nicht die ganze Seite paraphrasieren:
nur die eigenständig zitierfähigen Wissens-Bausteine (das, was eine KI als Antwort
übernehmen würde).

Antworte AUSSCHLIESSLICH als JSON: {"answer_units": [ {"entity": "...", "entity_type": "...", "intent": "...", "claim": "...", "schema_type": "..."} ]}

Regeln:
- entity = die Frage/Entität in wenigen Worten (z. B. "Preis Eventcatering pro Person", "Broich Catering").
- entity_type: einer von $types.
- intent: informational | commercial | transactional | navigational.
- claim: die Kern-Antwort in 1–2 Sätzen, faktisch, aus dem Seiteninhalt.
- schema_type: passendes schema.org (FAQPage, HowTo, Product, LocalBusiness, Article) oder null.
- Max 8 Einheiten, die wertvollsten. Nichts erfinden — nur was im Text steht.
TXT;
    }

    protected function userPrompt(SeoUrl $url, string $text, array $schemaTypes): string
    {
        return "SEITE: {$url->url}\n"
            .(! empty($schemaTypes) ? 'VORHANDENE STRUKTURDATEN: '.implode(', ', $schemaTypes)."\n" : '')
            ."\nINHALT (gekürzt):\n".$text;
    }

    /** @return array<int, array>|array{error:string} */
    protected function parse(string $textOut): array
    {
        if ($textOut === '') {
            return ['error' => 'Leere KI-Antwort.'];
        }
        if (preg_match('/\{.*\}/s', $textOut, $m)) {
            $textOut = $m[0];
        }
        $data = json_decode($textOut, true);
        if (! is_array($data) || ! isset($data['answer_units']) || ! is_array($data['answer_units'])) {
            return ['error' => 'KI-Antwort nicht als JSON lesbar.'];
        }

        $entityTypes = array_keys((array) config('seo.entity_types', []));
        $units = [];
        foreach ($data['answer_units'] as $u) {
            $entity = is_string($u['entity'] ?? null) ? trim($u['entity']) : '';
            $claim = is_string($u['claim'] ?? null) ? trim($u['claim']) : '';
            if ($entity === '' || $claim === '') {
                continue;
            }
            $et = $u['entity_type'] ?? null;
            $units[] = [
                'entity' => mb_substr($entity, 0, 255),
                'entity_type' => in_array($et, $entityTypes, true) ? $et : null,
                'intent' => is_string($u['intent'] ?? null) ? trim($u['intent']) : null,
                'claim' => $claim,
                'schema_type' => is_string($u['schema_type'] ?? null) ? trim($u['schema_type']) : null,
            ];
        }

        return $units;
    }

    protected function store(SeoUrl $url, ?int $portfolioId, array $units, string $hash): array
    {
        $entitiesTouched = [];
        $created = 0;

        foreach ($units as $u) {
            $entity = SeoEntity::firstOrCreate(
                ['team_id' => $url->team_id, 'name' => $u['entity']],
                ['entity_type' => $u['entity_type'], 'intent' => $u['intent']],
            );
            $entitiesTouched[$entity->id] = true;

            // Eine Antwort-Einheit je URL × Entität (aktualisieren statt duplizieren).
            $unit = SeoAnswerUnit::firstOrNew([
                'url_id' => $url->id,
                'entity_id' => $entity->id,
            ]);
            $wasNew = ! $unit->exists;
            $unit->fill([
                'team_id' => $url->team_id,
                'portfolio_id' => $portfolioId,
                'claim' => $u['claim'],
                'schema_type' => $u['schema_type'],
                'status' => SeoAnswerUnit::STATUS_LIVE,
                'verified_at' => now(),
                'content_hash' => $hash,
            ])->save();

            if ($wasNew) {
                $created++;
            }
        }

        return ['created' => $created, 'entities' => count($entitiesTouched)];
    }
}
