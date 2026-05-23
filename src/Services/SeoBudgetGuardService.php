<?php

namespace Platform\Seo\Services;

use Platform\Core\Models\User;
use Platform\Seo\Models\SeoBudgetLog;
use Platform\Seo\Models\SeoProject;

class SeoBudgetGuardService
{
    public function canFetch(SeoProject $project, int $estimatedCostCents): bool
    {
        if ($project->budget_limit_cents === null) {
            return true;
        }

        return ($project->budget_spent_cents + $estimatedCostCents) <= $project->budget_limit_cents;
    }

    public function recordCost(SeoProject $project, string $action, int $count, int $costCents, ?User $user = null, ?string $collector = null): SeoBudgetLog
    {
        $log = SeoBudgetLog::create([
            'project_id' => $project->id,
            'action' => $action,
            'collector' => $collector,
            'keyword_count' => $count,
            'cost_cents' => $costCents,
            'user_id' => $user?->id,
        ]);

        $project->increment('budget_spent_cents', $costCents);

        return $log;
    }

    public function resetMonthlyBudget(SeoProject $project): void
    {
        $project->update([
            'budget_spent_cents' => 0,
        ]);
    }

    public function getBudgetSummary(SeoProject $project): array
    {
        return [
            'limit_cents' => $project->budget_limit_cents,
            'spent_cents' => $project->budget_spent_cents,
            'remaining_cents' => $project->budget_remaining_cents,
            'percentage' => $project->budget_percentage,
        ];
    }

    /**
     * Calculate how many items can be afforded at a given cost per item.
     */
    public function affordableItemCount(SeoProject $project, int $costPerItem): int
    {
        if ($project->budget_limit_cents === null || $costPerItem <= 0) {
            return PHP_INT_MAX;
        }

        $remaining = $project->budget_remaining_cents;

        return max(0, (int) floor($remaining / $costPerItem));
    }
}
