<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoProjectService;

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
            ->withCount('keywords')
            ->orderBy('name')
            ->get();

        return view('seo::livewire.seo-project-index', [
            'projects' => $projects,
            'presets' => config('seo.industry_presets', []),
        ])->layout('platform::layouts.app');
    }
}
