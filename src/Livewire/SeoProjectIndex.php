<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoProjectService;
use Platform\Seo\Services\SeoScoringService;

class SeoProjectIndex extends Component
{
    public bool $showCreateModal = false;
    public string $newProjectName = '';
    public string $newProjectDomain = '';
    public ?string $newProjectPreset = null;

    public function createProject()
    {
        $this->validate([
            'newProjectName' => 'required|string|max:255',
            'newProjectDomain' => 'nullable|string|max:255',
        ]);

        $user = Auth::user();
        $team = $user->currentTeam;

        $projectService = app(SeoProjectService::class);
        $project = $projectService->create($team, $user, [
            'name' => $this->newProjectName,
            'domain' => $this->newProjectDomain ?: null,
            'industry_preset' => $this->newProjectPreset,
        ]);

        $this->reset(['newProjectName', 'newProjectDomain', 'newProjectPreset', 'showCreateModal']);
        $this->dispatch('updateSidebar');

        return $this->redirect(route('seo.projects.show', $project), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();
        $team = $user->currentTeam;

        $projects = SeoProject::where('team_id', $team->id)
            ->withCount(['urls', 'urls as own_urls_count' => fn ($q) => $q->where('is_own', true)])
            ->orderBy('name')
            ->get();

        $scoringService = app(SeoScoringService::class);
        $projectData = $projects->map(function ($project) use ($scoringService) {
            $visibility = $scoringService->getVisibilityScore($project);

            return [
                'project' => $project,
                'visibility' => $visibility['percentage'],
                'budget_percentage' => $project->budget_percentage,
                'budget_remaining' => $project->budget_remaining_cents,
            ];
        });

        $totalUrls = $projects->sum('urls_count');
        $avgVisibility = $projectData->avg('visibility') ?? 0;
        $totalBudgetRemaining = $projectData->sum('budget_remaining');

        return view('seo::livewire.seo-project-index', [
            'projectData' => $projectData,
            'totalProjects' => $projects->count(),
            'totalUrls' => $totalUrls,
            'avgVisibility' => round($avgVisibility, 1),
            'totalBudgetRemaining' => $totalBudgetRemaining,
            'presets' => config('seo.industry_presets', []),
        ])->layout('platform::layouts.app');
    }
}
