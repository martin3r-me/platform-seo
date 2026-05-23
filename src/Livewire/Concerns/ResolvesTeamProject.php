<?php

namespace Platform\Seo\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Platform\Seo\Models\SeoProject;

trait ResolvesTeamProject
{
    public SeoProject $seoProject;

    protected function resolveProject(): void
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $this->seoProject = SeoProject::firstOrCreate(
            ['team_id' => $team->id],
            [
                'user_id' => $user->id,
                'name' => $team->name ?? 'SEO',
                'budget_limit_cents' => config('seo.budget.default_limit_cents', 5000),
                'refresh_interval_hours' => 168,
                'location_code' => 2276,
                'language_code' => 1001,
            ]
        );
    }
}
