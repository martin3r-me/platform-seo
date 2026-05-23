<?php

namespace Platform\Seo\Livewire\Concerns;

use Illuminate\Support\Facades\Auth;
use Platform\Seo\Models\SeoTeamSettings;

trait ResolvesTeamSettings
{
    public SeoTeamSettings $seoSettings;

    protected function resolveSettings(): void
    {
        $team = Auth::user()->currentTeam;

        $this->seoSettings = SeoTeamSettings::firstOrCreate(
            ['team_id' => $team->id],
            [
                'budget_limit_cents' => config('seo.budget.default_limit_cents', 5000),
                'refresh_interval_hours' => 168,
                'location_code' => 2276,
                'language_code' => 1001,
            ]
        );
    }
}
