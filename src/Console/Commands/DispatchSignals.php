<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoSignalDispatcher;

/**
 * Routet zugelassene Signale zum richtigen Arbeitsobjekt (docs/SIGNALS-CONCEPT.md §4):
 * content → Content-Brief. page_edit/structural fließen über den Flynk-Push.
 */
class DispatchSignals extends Command
{
    protected $signature = 'seo:dispatch-signals
                            {--team= : Nur dieses Team}
                            {--limit=20 : Max. Signale pro Team}';

    protected $description = 'Erzeugt aus Signalen die passenden Arbeitsobjekte (Content-Briefs).';

    public function handle(SeoSignalDispatcher $dispatcher): int
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

        $total = 0;
        foreach ($settingsList as $settings) {
            $res = $dispatcher->dispatchTeam((int) $settings->team_id, $limit);
            $briefs = $res['by_target']['content_brief'] ?? 0;
            $this->info("Team {$settings->team_id}: {$res['dispatched']} dispatcht ({$briefs} Content-Briefs) von {$res['considered']} offenen");
            $total += $res['dispatched'];
        }

        $this->info("Fertig. {$total} Arbeitsobjekte erzeugt.");

        return self::SUCCESS;
    }
}
