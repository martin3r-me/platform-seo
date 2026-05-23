<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoBudgetGuardService;

class ResetBudgets extends Command
{
    protected $signature = 'seo:reset-budgets
                            {--team= : Specific team ID}';

    protected $description = 'Reset monthly budgets for SEO teams';

    public function handle(SeoBudgetGuardService $budgetGuard): int
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

        $this->info("Budget-Reset für {$settingsList->count()} Team(s)...");

        foreach ($settingsList as $settings) {
            $previousSpent = $settings->budget_spent_cents;
            $budgetGuard->resetMonthlyBudget($settings);
            $this->line("  Team {$settings->team_id}: {$previousSpent} Cent → 0");
        }

        $this->info('Fertig.');

        return self::SUCCESS;
    }
}
