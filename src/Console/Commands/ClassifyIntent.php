<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

/**
 * Füllt das search_intent der Keywords (nur fehlende) via DataForSeo Labs.
 * Selbstwartend: neue Keywords aus der Discovery bekommen laufend ihren Intent.
 */
class ClassifyIntent extends Command
{
    protected $signature = 'seo:classify-intent
                            {--team= : Nur dieses Team}
                            {--limit=1000 : Max. Keywords pro Team/Lauf}
                            {--all : Auch bereits klassifizierte neu bestimmen}';

    protected $description = 'Klassifiziert Keywords nach Suchintention (search_intent).';

    public function handle(SeoKeywordService $service): int
    {
        $teamId = $this->option('team');
        $query = SeoTeamSettings::query();
        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $total = 0;
        foreach ($query->get() as $settings) {
            $res = $service->classifyIntentForTeam((int) $settings->team_id, null, [
                'limit' => (int) $this->option('limit'),
                'only_missing' => ! $this->option('all'),
            ]);
            if (!empty($res['error'])) {
                $this->warn("Team {$settings->team_id}: {$res['error']}");
                continue;
            }
            $this->info("Team {$settings->team_id}: {$res['classified']}/{$res['candidates']} klassifiziert ({$res['cost_cents']} Cent)");
            $total += $res['classified'];
        }

        $this->info("Gesamt: {$total} Keywords klassifiziert.");

        return self::SUCCESS;
    }
}
