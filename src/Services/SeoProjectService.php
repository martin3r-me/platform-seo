<?php

namespace Platform\Seo\Services;

use Platform\Core\Models\Team;
use Platform\Core\Models\User;
use Platform\Seo\Models\SeoProject;

class SeoProjectService
{
    public function create(Team $team, User $user, array $data): SeoProject
    {
        return SeoProject::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'domain' => $data['domain'] ?? null,
            'industry_preset' => $data['industry_preset'] ?? null,
            'budget_limit_cents' => $data['budget_limit_cents'] ?? config('seo.budget.default_limit_cents', 5000),
            'refresh_interval_hours' => $data['refresh_interval_hours'] ?? 168,
            'dataforseo_connection_id' => $data['dataforseo_connection_id'] ?? null,
            'location_code' => $data['location_code'] ?? 2276,
            'language_code' => $data['language_code'] ?? 1001,
            'settings' => $data['settings'] ?? null,
        ]);
    }

    public function update(SeoProject $project, array $data): SeoProject
    {
        $updateData = [];

        foreach ([
            'name', 'description', 'domain', 'industry_preset',
            'budget_limit_cents', 'refresh_interval_hours',
            'dataforseo_connection_id', 'location_code', 'language_code',
            'settings',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $project->update($updateData);
        }

        return $project->fresh();
    }

    public function delete(SeoProject $project): void
    {
        $project->delete();
    }
}
