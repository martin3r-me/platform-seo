<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoKeywordService;

/**
 * Frischt den Keyword-Footprint der Wettbewerber-URLs auf — profil-gesteuert
 * (Profil "beobachten"/"analyse" enthält competitor_footprint, "aus" nicht).
 * Fälligkeit über die Profil-Kadenz (Standard monatlich). Kosten via
 * linkCompetitorKeywords (~10 ct/Domain), budget-gegated.
 */
class RefreshCompetitorFootprints extends Command
{
    protected $signature = 'seo:refresh-footprints
                            {--team= : Nur dieses Team}
                            {--dry-run : Nur zählen, nichts holen}';

    protected $description = 'Aktualisiert die Wettbewerber-Footprints gemäß Daten-Profil.';

    public function handle(SeoKeywordService $keywordService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = SeoTeamSettings::query();
        if ($team = $this->option('team')) {
            $query->where('team_id', $team);
        }

        $totalRefreshed = 0;
        foreach ($query->get() as $settings) {
            $teamId = (int) $settings->team_id;
            $urls = SeoUrl::where('team_id', $teamId)
                ->where('is_own', false)
                ->where('status', 'active')
                ->get();

            $refreshed = 0;
            foreach ($urls as $url) {
                // Profil-gesteuerte Fälligkeit: false, wenn Profil kein
                // competitor_footprint enthält ("aus") oder noch nicht fällig.
                if (! $url->isDueForCollector('competitor_footprint', 0)) {
                    continue;
                }
                if ($dryRun) {
                    $refreshed++;
                    continue;
                }

                $res = $keywordService->linkCompetitorKeywords($teamId, $url, null, ['keywords_limit' => 200]);
                if (empty($res['error'])) {
                    $url->setCollectorTimestamp('competitor_footprint');
                    $refreshed++;
                } else {
                    $this->warn("  {$url->domain}: {$res['error']}");
                }
            }

            $prefix = $dryRun ? '[dry-run] ' : '';
            $this->info("{$prefix}Team {$teamId}: {$refreshed} Wettbewerber-Footprints".($dryRun ? ' fällig' : ' aktualisiert'));
            $totalRefreshed += $refreshed;
        }

        $this->info('Gesamt: '.$totalRefreshed);

        return self::SUCCESS;
    }
}
