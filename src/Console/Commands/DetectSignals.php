<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoSignalService;

class DetectSignals extends Command
{
    protected $signature = 'seo:detect-signals
                            {--project= : Specific project ID}';

    protected $description = 'Detect SEO signals (volume spikes, ranking changes, opportunities)';

    public function handle(SeoSignalService $signalService): int
    {
        $projectId = $this->option('project');

        $query = SeoProject::query();

        if ($projectId) {
            $query->where('id', $projectId);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->info('Keine Projekte gefunden.');
            return self::SUCCESS;
        }

        $this->info("Signal-Detection für {$projects->count()} Projekt(e)...");
        $this->newLine();

        $totalSignals = 0;

        foreach ($projects as $project) {
            $this->info("Projekt: {$project->name} (ID: {$project->id})");

            // Keyword-based signals
            $result = $signalService->detectSignals($project);

            $this->line("  Volume Spikes: {$result['volume_spikes']}");
            $this->line("  Volume Drops: {$result['volume_drops']}");
            $this->line("  Position Rises: {$result['position_rises']}");
            $this->line("  Position Drops: {$result['position_drops']}");
            $this->line("  Opportunities: {$result['opportunities']}");
            $this->line("  Keyword Signals: {$result['total_signals']}");

            // URL-based signals
            $urlResult = $signalService->detectUrlSignals($project);

            $this->line("  Redirects: {$urlResult['redirect_detected']}");
            $this->line("  URL Errors: {$urlResult['url_error']}");
            $this->line("  Cannibalization: {$urlResult['cannibalization_detected']}");

            $urlSignals = array_sum($urlResult);
            $this->line("  Total: " . ($result['total_signals'] + $urlSignals));
            $this->newLine();

            $totalSignals += $result['total_signals'] + $urlSignals;
        }

        $this->info("Gesamt: {$totalSignals} Signals erstellt.");

        return self::SUCCESS;
    }
}
