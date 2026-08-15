<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoUrl;

/**
 * Einmalige Bestands-Migration auf Daten-Profile (vereinbarte Regel):
 *  - eigene, aktiv, mit Backlink-/LLM-Daten → tief (Tiefe erhalten)
 *  - eigene, aktiv, sonst                    → standard
 *  - eigene, inaktiv/archiviert              → aus
 *  - Wettbewerber, aktiv                     → beobachten
 *  - Wettbewerber, inaktiv                   → aus
 *
 * Idempotent; --dry-run zeigt nur die Verteilung. --force überschreibt bereits
 * gesetzte Profile (sonst werden nur leere befüllt).
 */
class MigrateDataProfiles extends Command
{
    protected $signature = 'seo:migrate-profiles
                            {--team= : Nur dieses Team}
                            {--dry-run : Nur zählen, nichts schreiben}
                            {--force : Auch bereits gesetzte data_profile überschreiben}';

    protected $description = 'Weist Bestands-URLs ihr Daten-Profil zu (einmalig).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $query = SeoUrl::query();
        if ($team = $this->option('team')) {
            $query->where('team_id', $team);
        }
        if (! $force) {
            $query->whereNull('data_profile');
        }

        $counts = [];
        $query->chunkById(500, function ($urls) use ($dryRun, &$counts) {
            foreach ($urls as $url) {
                $profile = $this->profileFor($url);
                $counts[$profile] = ($counts[$profile] ?? 0) + 1;
                if (! $dryRun) {
                    $url->update(['data_profile' => $profile]);
                }
            }
        });

        $prefix = $dryRun ? '[dry-run] ' : '';
        foreach ($counts as $profile => $n) {
            $this->info("{$prefix}{$profile}: {$n}");
        }
        $this->info($prefix.'Gesamt: '.array_sum($counts).' URLs.');

        return self::SUCCESS;
    }

    protected function profileFor(SeoUrl $url): string
    {
        $active = $url->status === 'active';

        if ($url->is_own) {
            if (! $active) {
                return 'aus';
            }
            $deep = $url->backlink_count !== null || $url->llm_mentions_fetched_at !== null;

            return $deep ? 'tief' : 'standard';
        }

        return $active ? 'beobachten' : 'aus';
    }
}
