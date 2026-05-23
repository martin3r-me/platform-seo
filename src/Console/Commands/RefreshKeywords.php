<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoKeywordService;

class RefreshKeywords extends Command
{
    protected $signature = 'seo:refresh-keywords
                            {--project= : Specific project ID}
                            {--force : Refresh even if not due}';

    protected $description = 'Refresh keyword metrics and rankings for SEO projects';

    public function handle(SeoKeywordService $keywordService): int
    {
        $projectId = $this->option('project');
        $force = $this->option('force');

        $query = SeoProject::query();

        if ($projectId) {
            $query->where('id', $projectId);
        } elseif (!$force) {
            $query->where(function ($q) {
                $q->whereNull('next_refresh_at')
                    ->orWhere('next_refresh_at', '<=', now());
            });
        }

        $projects = $query->get();

        if ($projects->isEmpty()) {
            $this->info('Keine Projekte zum Aktualisieren.');
            return self::SUCCESS;
        }

        $this->info("Aktualisiere {$projects->count()} Projekt(e)...");
        $this->newLine();

        foreach ($projects as $project) {
            $this->info("Projekt: {$project->name} (ID: {$project->id})");

            $user = $project->user;
            if (!$user) {
                $this->warn("  Kein User zugeordnet, überspringe.");
                continue;
            }

            // Fetch metrics
            $metricsResult = $keywordService->fetchMetrics($project->team_id, $project->id, $user);
            $this->line("  Metriken: {$metricsResult['fetched']} Keywords aktualisiert ({$metricsResult['cost_cents']} Cent)");

            if (isset($metricsResult['error'])) {
                $this->warn("  Fehler: {$metricsResult['error']}");
                continue;
            }

            // Fetch rankings
            $rankingsResult = $keywordService->fetchRankings($project->id, $user);
            $this->line("  Rankings: {$rankingsResult['fetched']} Keywords, {$rankingsResult['position_snapshots']} Snapshots ({$rankingsResult['cost_cents']} Cent)");

            if (isset($rankingsResult['error'])) {
                $this->warn("  Fehler: {$rankingsResult['error']}");
            }

            $this->newLine();
        }

        $this->info('Fertig.');

        return self::SUCCESS;
    }
}
