<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

class RefreshCompetitors extends Command
{
    protected $signature = 'seo:refresh-competitors
                            {--team= : Specific team ID}
                            {--only-new : Only keywords without existing competitor data}
                            {--force : Process all teams, ignore refresh schedule}';

    protected $description = 'Fetch SERP competitors for keywords with competitor tracking enabled';

    public function handle(SeoKeywordService $keywordService): int
    {
        $teamId = $this->option('team');
        $onlyNew = $this->option('only-new');
        $force = $this->option('force');

        // Find teams that have keywords with competitor_tracking_depth set
        $query = SeoTeamSettings::query();

        if ($teamId) {
            $query->where('team_id', $teamId);
        } elseif (!$force) {
            $query->where(function ($q) {
                $q->whereNull('next_refresh_at')
                    ->orWhere('next_refresh_at', '<=', now());
            });
        }

        $settingsList = $query->get()->filter(function ($settings) {
            return SeoKeyword::where('team_id', $settings->team_id)
                ->whereNotNull('competitor_tracking_depth')
                ->where('competitor_tracking_depth', '>', 0)
                ->exists();
        });

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams mit Competitor-Tracking-Keywords gefunden.');
            return self::SUCCESS;
        }

        $this->info("SERP-Competitors fuer {$settingsList->count()} Team(s)...");
        $this->newLine();

        foreach ($settingsList as $settings) {
            $trackedCount = SeoKeyword::where('team_id', $settings->team_id)
                ->whereNotNull('competitor_tracking_depth')
                ->where('competitor_tracking_depth', '>', 0)
                ->when($onlyNew, fn ($q) => $q->whereDoesntHave('competitors'))
                ->count();

            $this->info("Team ID: {$settings->team_id} | Domain: {$settings->domain} | Keywords mit Tracking: {$trackedCount}");

            if ($trackedCount === 0) {
                $this->line('  Uebersprungen (keine faelligen Keywords)');
                $this->newLine();
                continue;
            }

            $options = [
                'only_with_competitor_tracking' => true,
                'only_new' => $onlyNew,
            ];

            $result = $keywordService->fetchRankings($settings->team_id, null, $options);

            $this->line("  Abgefragt: {$result['fetched']} Keywords, {$result['position_snapshots']} Snapshots ({$result['cost_cents']} Cent)");

            if (isset($result['error'])) {
                $this->warn("  Fehler: {$result['error']}");
            }

            $this->newLine();
        }

        $this->info('Fertig.');

        return self::SUCCESS;
    }
}
