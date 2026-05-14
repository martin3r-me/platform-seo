<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoBudgetGuardService;

class ResetBudgets extends Command
{
    protected $signature = 'seo:reset-budgets
                            {--project= : Specific project ID}';

    protected $description = 'Reset monthly budgets for SEO projects';

    public function handle(SeoBudgetGuardService $budgetGuard): int
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

        $this->info("Budget-Reset für {$projects->count()} Projekt(e)...");

        foreach ($projects as $project) {
            $previousSpent = $project->budget_spent_cents;
            $budgetGuard->resetMonthlyBudget($project);
            $this->line("  {$project->name}: {$previousSpent} Cent → 0");
        }

        $this->info('Fertig.');

        return self::SUCCESS;
    }
}
