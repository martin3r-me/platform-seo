<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoContentBriefReconciler;

/**
 * Schließt den SEO ↔ Flynk-Loop (docs/CONTENT-BRIEF-TRACKING.md): prüft offene
 * Content-Briefs gegen ihre Live-Seiten (x-content-brief-Marker) und schaltet
 * gematchte Briefs auf "published".
 */
class ReconcileContentBriefs extends Command
{
    protected $signature = 'seo:reconcile-briefs
                            {--team= : Nur dieses Team}
                            {--dry-run : Nur prüfen, nichts schreiben}';

    protected $description = 'Gleicht Content-Briefs mit ihren veröffentlichten Seiten ab (Marker-basiert).';

    public function handle(SeoContentBriefReconciler $reconciler): int
    {
        $teamId = $this->option('team');
        $dryRun = (bool) $this->option('dry-run');

        $query = SeoTeamSettings::query();
        if ($teamId) {
            $query->where('team_id', $teamId);
        }
        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams gefunden.');

            return self::SUCCESS;
        }

        $totalPublished = 0;
        foreach ($settingsList as $settings) {
            $res = $reconciler->reconcileTeam((int) $settings->team_id, $dryRun);
            $verb = $dryRun ? 'würden veröffentlicht' : 'veröffentlicht';
            $this->info("Team {$settings->team_id}: {$res['checked']} geprüft, {$res['published']} {$verb}, {$res['pending']} offen");
            foreach ($res['errors'] as $err) {
                $this->warn("  {$err}");
            }
            $totalPublished += $res['published'];
        }

        $this->info(($dryRun ? '[dry-run] ' : '') . "Gesamt: {$totalPublished} Briefs veröffentlicht.");

        return self::SUCCESS;
    }
}
