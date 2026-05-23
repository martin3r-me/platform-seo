<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

class RefreshKeywords extends Command
{
    protected $signature = 'seo:refresh-keywords
                            {--team= : Specific team ID}
                            {--force : Refresh even if not due}';

    protected $description = 'Refresh keyword metrics and rankings for SEO teams';

    public function handle(SeoKeywordService $keywordService): int
    {
        $teamId = $this->option('team');
        $force = $this->option('force');

        $query = SeoTeamSettings::query();

        if ($teamId) {
            $query->where('team_id', $teamId);
        } elseif (!$force) {
            $query->where(function ($q) {
                $q->whereNull('next_refresh_at')
                    ->orWhere('next_refresh_at', '<=', now());
            });
        }

        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams zum Aktualisieren.');
            return self::SUCCESS;
        }

        $this->info("Aktualisiere {$settingsList->count()} Team(s)...");
        $this->newLine();

        foreach ($settingsList as $settings) {
            $this->info("Team ID: {$settings->team_id} | Domain: {$settings->domain}");

            // Fetch metrics
            $metricsResult = $keywordService->fetchMetrics($settings->team_id, null, null);
            $this->line("  Metriken: {$metricsResult['fetched']} Keywords aktualisiert ({$metricsResult['cost_cents']} Cent)");

            if (isset($metricsResult['error'])) {
                $this->warn("  Fehler: {$metricsResult['error']}");
                continue;
            }

            // Fetch rankings
            $rankingsResult = $keywordService->fetchRankings($settings->team_id);
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
