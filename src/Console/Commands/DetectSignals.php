<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoSignalService;

class DetectSignals extends Command
{
    protected $signature = 'seo:detect-signals
                            {--team= : Specific team ID}';

    protected $description = 'Detect SEO signals (volume spikes, ranking changes, opportunities)';

    public function handle(SeoSignalService $signalService): int
    {
        $teamId = $this->option('team');

        $query = SeoTeamSettings::query();

        if ($teamId) {
            $query->where('team_id', $teamId);
        }

        $settingsList = $query->get();

        if ($settingsList->isEmpty()) {
            $this->info('Keine Teams gefunden.');
            return self::SUCCESS;
        }

        $this->info("Signal-Detection für {$settingsList->count()} Team(s)...");
        $this->newLine();

        $totalSignals = 0;

        foreach ($settingsList as $settings) {
            $this->info("Team ID: {$settings->team_id} | Domain: {$settings->domain}");

            // Keyword-based signals
            $result = $signalService->detectSignals($settings);

            $this->line("  Volume Spikes: {$result['volume_spikes']}");
            $this->line("  Volume Drops: {$result['volume_drops']}");
            $this->line("  Position Rises: {$result['position_rises']}");
            $this->line("  Position Drops: {$result['position_drops']}");
            $this->line("  Opportunities: {$result['opportunities']}");
            $this->line("  Keyword Signals: {$result['total_signals']}");

            // URL-based signals
            $urlResult = $signalService->detectUrlSignals($settings);

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
