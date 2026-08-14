<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoSignalEnrichmentService;

/**
 * Reichert berechnete Signale (von enrich-aktiven Definitionen) per generativer KI an.
 * Kostet LLM-Budget — daher explizit/command-getrieben, mit --limit als Deckel.
 */
class EnrichSignals extends Command
{
    protected $signature = 'seo:enrich-signals
                            {--team= : Nur dieses Team}
                            {--limit=20 : Max. Signale pro Team}
                            {--force : Auch bereits angereicherte Signale neu anreichern}';

    protected $description = 'Reichert offene Signale enrich-aktiver Definitionen per KI an.';

    public function handle(SeoSignalEnrichmentService $service): int
    {
        $teamId = $this->option('team');
        $limit = (int) $this->option('limit');

        $query = SeoTeamSettings::query();
        if ($teamId) {
            $query->where('team_id', $teamId);
        }
        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams gefunden.');

            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        $total = 0;
        foreach ($settingsList as $settings) {
            $res = $service->enrichTeam((int) $settings->team_id, $limit, $force);
            if (! empty($res['error'])) {
                $this->warn("Team {$settings->team_id}: {$res['error']}");

                continue;
            }
            $this->info("Team {$settings->team_id}: {$res['enriched']} angereichert, {$res['skipped']} übersprungen");
            $total += $res['enriched'];
        }

        $this->info("Fertig. {$total} Signale angereichert.");

        return self::SUCCESS;
    }
}
