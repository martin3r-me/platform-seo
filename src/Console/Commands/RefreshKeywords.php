<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

class RefreshKeywords extends Command
{
    protected $signature = 'seo:refresh-keywords
                            {--team= : Specific team ID}
                            {--force : Refresh even if not due}';

    protected $description = 'Refresh keyword rankings (domain-based) and metrics for SEO teams';

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

            // Fetch rankings per Domain (1 API-Call pro Domain)
            $rankingsResult = $keywordService->fetchRankingsByDomain($settings->team_id);
            $this->line("  Rankings: {$rankingsResult['fetched']} Keywords, {$rankingsResult['position_snapshots']} Snapshots, {$rankingsResult['api_calls']} API-Call(s) ({$rankingsResult['cost_cents']} Cent)");

            if (isset($rankingsResult['error'])) {
                $this->warn("  Fehler: {$rankingsResult['error']}");
            }

            // Fetch metrics nur für Keywords ohne URL-Zuordnung
            $orphanCount = SeoKeyword::where('team_id', $settings->team_id)
                ->whereDoesntHave('urls')
                ->whereNull('last_fetched_at')
                ->orWhere(function ($q) use ($settings) {
                    $q->where('team_id', $settings->team_id)
                        ->whereDoesntHave('urls')
                        ->where('last_fetched_at', '<', now()->subDays(7));
                })
                ->count();

            if ($orphanCount > 0) {
                $metricsResult = $keywordService->fetchMetrics($settings->team_id, null, null);
                $this->line("  Metriken (ohne URL): {$metricsResult['fetched']} Keywords aktualisiert ({$metricsResult['cost_cents']} Cent)");

                if (isset($metricsResult['error'])) {
                    $this->warn("  Fehler: {$metricsResult['error']}");
                }
            } else {
                $this->line('  Metriken: übersprungen (alle Keywords haben URL-Zuordnung)');
            }

            $this->newLine();
        }

        $this->info('Fertig.');

        return self::SUCCESS;
    }
}
