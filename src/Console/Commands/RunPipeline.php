<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoUrlPipelineService;

class RunPipeline extends Command
{
    protected $signature = 'seo:pipeline
                            {--project= : Specific project ID}
                            {--collector= : Run only a specific collector}
                            {--max-urls= : Max URLs to process per run}
                            {--force : Refresh even if not due}
                            {--dry-run : Show what would happen without executing}';

    protected $description = 'Run the SEO data pipeline for URL-centric data collection';

    public function handle(SeoUrlPipelineService $pipeline): int
    {
        $projectId = $this->option('project');
        $collector = $this->option('collector');
        $maxUrls = $this->option('max-urls');
        $force = $this->option('force');
        $dryRun = $this->option('dry-run');

        $query = SeoProject::query();

        if ($projectId) {
            $query->where('id', $projectId);
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->info('Keine Projekte gefunden.');

            return self::SUCCESS;
        }

        $mode = $dryRun ? ' (DRY RUN)' : '';
        $this->info("SEO Pipeline{$mode} fuer {$projects->count()} Projekt(e)...");
        $this->newLine();

        $totalCost = 0;
        $totalUrlsProcessed = 0;

        foreach ($projects as $project) {
            $this->info("Projekt: {$project->name} (ID: {$project->id})");

            $options = [
                'dry_run' => $dryRun,
                'force' => $force,
            ];

            if ($collector) {
                $options['collectors'] = [$collector];
            }
            if ($maxUrls) {
                $options['max_urls'] = (int) $maxUrls;
            }

            $result = $pipeline->runPipeline($project, $options);

            $this->line("  URLs verarbeitet: {$result['urls_processed']}");
            $this->line("  Gesamtkosten: {$result['total_cost_cents']} Cent");

            foreach ($result['collectors_run'] as $collectorResult) {
                $status = ($collectorResult['skipped'] ?? false) ? 'uebersprungen' : 'OK';
                $reason = isset($collectorResult['reason']) ? " ({$collectorResult['reason']})" : '';
                $cost = $collectorResult['cost_cents'] ?? $collectorResult['estimated_cost_cents'] ?? 0;

                $this->line("    [{$collectorResult['collector']}] {$collectorResult['name']}: {$status}{$reason}");
                $this->line("      URLs fällig: {$collectorResult['urls_due']}, Kosten: {$cost} Cent");

                if (! empty($collectorResult['errors'])) {
                    foreach ($collectorResult['errors'] as $error) {
                        $this->warn("      Fehler: {$error}");
                    }
                }
            }

            if (! empty($result['errors'])) {
                $this->warn("  Fehler insgesamt: ".count($result['errors']));
            }

            $totalCost += $result['total_cost_cents'];
            $totalUrlsProcessed += $result['urls_processed'];
            $this->newLine();
        }

        $this->info("Fertig. URLs: {$totalUrlsProcessed}, Kosten: {$totalCost} Cent");

        return self::SUCCESS;
    }
}
