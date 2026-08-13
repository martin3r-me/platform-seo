<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoSignalEvaluator;

/**
 * Wertet die aktiven Signal-Definitionen aus und erzeugt daraus echte Signale.
 * Definition-getrieben (docs/SIGNALS-CONCEPT.md) — das Gegenstück zum hartcodierten
 * seo:detect-signals.
 */
class EvaluateSignals extends Command
{
    protected $signature = 'seo:evaluate-signals
                            {--team= : Nur dieses Team}
                            {--frequency= : Nur Definitionen dieser Frequenz (every_snapshot|daily|weekly)}';

    protected $description = 'Wertet aktive Signal-Definitionen aus und erzeugt Signale.';

    public function handle(SeoSignalEvaluator $evaluator): int
    {
        $teamId = $this->option('team');
        $frequency = $this->option('frequency') ?: null;

        $query = SeoTeamSettings::query();
        if ($teamId) {
            $query->where('team_id', $teamId);
        }
        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams gefunden.');

            return self::SUCCESS;
        }

        $total = 0;
        foreach ($settingsList as $settings) {
            $res = $evaluator->evaluateTeam((int) $settings->team_id, $frequency);
            $this->info("Team {$settings->team_id}: {$res['definitions']} Definitionen → {$res['created']} neue Signale");
            foreach ($res['by_pattern'] as $pattern => $count) {
                $this->line("  {$pattern}: {$count}");
            }
            $total += $res['created'];
        }

        $this->info("Fertig. {$total} neue Signale erzeugt.");

        return self::SUCCESS;
    }
}
