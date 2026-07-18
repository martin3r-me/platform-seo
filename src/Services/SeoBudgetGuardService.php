<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\DB;
use Platform\Core\Models\User;
use Platform\Seo\Models\SeoBudgetLog;
use Platform\Seo\Models\SeoTeamSettings;

class SeoBudgetGuardService
{
    public function canFetch(SeoTeamSettings $settings, int $estimatedCostCents): bool
    {
        if ($settings->budget_limit_cents === null) {
            return true;
        }

        return ($settings->budget_spent_cents + $estimatedCostCents) <= $settings->budget_limit_cents;
    }

    public function recordCost(SeoTeamSettings $settings, string $action, int $count, int $costCents, ?User $user = null, ?string $collector = null): SeoBudgetLog
    {
        // Log und Verbrauch konsistent halten. Der increment() selbst ist atomar
        // (SET budget_spent_cents = budget_spent_cents + n) — daher kein Lost-Update
        // bei nebenläufigen Fetches; die Transaktion koppelt nur Log + Verbrauch.
        return DB::transaction(function () use ($settings, $action, $count, $costCents, $user, $collector) {
            $log = SeoBudgetLog::create([
                'team_id' => $settings->team_id,
                'action' => $action,
                'collector' => $collector,
                'keyword_count' => $count,
                'cost_cents' => $costCents,
                'user_id' => $user?->id,
            ]);

            $settings->increment('budget_spent_cents', $costCents);

            return $log;
        });
    }

    public function resetMonthlyBudget(SeoTeamSettings $settings): void
    {
        $settings->update([
            'budget_spent_cents' => 0,
        ]);
    }

    public function getBudgetSummary(SeoTeamSettings $settings): array
    {
        return [
            'limit_cents' => $settings->budget_limit_cents,
            'spent_cents' => $settings->budget_spent_cents,
            'remaining_cents' => $settings->budget_remaining_cents,
            'percentage' => $settings->budget_percentage,
        ];
    }

    /**
     * Calculate how many items can be afforded at a given cost per item.
     */
    public function affordableItemCount(SeoTeamSettings $settings, int $costPerItem): int
    {
        if ($settings->budget_limit_cents === null || $costPerItem <= 0) {
            return PHP_INT_MAX;
        }

        $remaining = $settings->budget_remaining_cents;

        return max(0, (int) floor($remaining / $costPerItem));
    }
}
