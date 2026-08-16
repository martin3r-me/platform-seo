<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Core\Services\EmbeddingService;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;

/**
 * Embeddet Keywords zu semantischen Vektoren (OpenAI → Qdrant) — die Grundlage
 * für die semantische Ansicht, Ausreißer-/Schrott-Erkennung und Weißraum.
 *
 * Nächtlich, aber billig: EmbeddingService macht Skip-if-unchanged über den
 * source_hash (Keyword-Text) — nur NEUE/geänderte Keywords kosten einen API-Call.
 * Team-weit, weil ein Keyword-Vektor die Bedeutung trägt (überall gleich, in
 * jedem Wirkungsraum wiederverwendbar) — nicht je Wirkungsraum doppelt.
 */
class SeoEmbedKeywords extends Command
{
    protected $signature = 'seo:embed-keywords
                            {--team= : Nur ein bestimmtes Team}
                            {--chunk=500 : Keywords pro Batch}';

    protected $description = 'Embeddet Keywords (semantische Vektoren, OpenAI→Qdrant) — skip-if-unchanged, für Weißraum/Cluster';

    public function handle(EmbeddingService $embeddings): int
    {
        $teamIds = $this->resolveTeamIds();
        if (empty($teamIds)) {
            $this->warn('Keine Teams mit Keywords gefunden.');

            return self::SUCCESS;
        }

        $chunk = max(50, (int) $this->option('chunk'));
        $grandTotal = 0;
        $failed = false;

        foreach ($teamIds as $teamId) {
            $checked = 0;

            SeoKeyword::where('team_id', $teamId)
                ->orderBy('id')
                ->chunkById($chunk, function ($keywords) use ($embeddings, $teamId, &$checked, &$failed) {
                    $entries = $keywords
                        ->map(fn ($k) => [
                            'id' => $k->id,
                            'text' => trim((string) $k->keyword),
                            'metadata' => [
                                'keyword' => (string) $k->keyword,
                                'search_volume' => (int) ($k->search_volume ?? 0),
                                'cluster_id' => $k->cluster_id, // null = ungeclustert (Weißraum-Kandidat)
                            ],
                        ])
                        ->filter(fn ($e) => $e['text'] !== '')
                        ->values()
                        ->all();

                    if (empty($entries)) {
                        return true;
                    }

                    try {
                        $embeddings->embedAndStoreBatch($teamId, 'seo_keyword', $entries);
                    } catch (\Throwable $e) {
                        $this->error("Team {$teamId}: ".$e->getMessage());
                        $failed = true;

                        return false; // Chunking für dieses Team abbrechen
                    }

                    $checked += count($entries);

                    return true;
                });

            $this->info("Team {$teamId}: {$checked} Keywords geprüft (neue/geänderte embedded, unveränderte übersprungen).");
            $grandTotal += $checked;
        }

        $this->info("Fertig — {$grandTotal} Keywords über ".count($teamIds).' Team(s).');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return int[]
     */
    protected function resolveTeamIds(): array
    {
        if ($teamId = $this->option('team')) {
            return [(int) $teamId];
        }

        // Teams mit SEO-Einstellungen; Fallback auf alle Teams, die Keywords haben.
        $ids = SeoTeamSettings::query()->pluck('team_id')->all();
        if (empty($ids)) {
            $ids = SeoKeyword::query()->distinct()->pluck('team_id')->all();
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }
}
