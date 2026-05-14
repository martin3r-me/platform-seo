<?php

namespace Platform\Seo\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Platform\Seo\Models\SeoProject;
use Platform\Seo\Services\SeoAnalysisService;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoScoringService;

class SeoProjectDetail extends Component
{
    public SeoProject $seoProject;

    public bool $showEditModal = false;
    public string $editName = '';
    public string $editDescription = '';
    public string $editDomain = '';

    public function mount(SeoProject $seoProject)
    {
        $this->seoProject = $seoProject;
    }

    public function openEditModal()
    {
        $this->editName = $this->seoProject->name;
        $this->editDescription = $this->seoProject->description ?? '';
        $this->editDomain = $this->seoProject->domain ?? '';
        $this->showEditModal = true;
    }

    public function saveProject()
    {
        $this->validate([
            'editName' => 'required|string|max:255',
            'editDescription' => 'nullable|string',
            'editDomain' => 'nullable|string|max:255',
        ]);

        $this->seoProject->update([
            'name' => $this->editName,
            'description' => $this->editDescription ?: null,
            'domain' => $this->editDomain ?: null,
        ]);

        $this->showEditModal = false;
        $this->dispatch('updateSidebar');
    }

    public function deleteProject()
    {
        $this->seoProject->delete();
        $this->dispatch('updateSidebar');

        return $this->redirect(route('seo.projects.index'), navigate: true);
    }

    public function render()
    {
        $analysisService = app(SeoAnalysisService::class);
        $budgetGuard = app(SeoBudgetGuardService::class);
        $scoringService = app(SeoScoringService::class);

        $keywordSummary = $analysisService->getKeywordSummary($this->seoProject);
        $budgetSummary = $budgetGuard->getBudgetSummary($this->seoProject);
        $visibility = $scoringService->getVisibilityScore($this->seoProject);

        $recentSignals = $this->seoProject->signals()
            ->where('status', 'new')
            ->orderByDesc('detected_at')
            ->take(10)
            ->get();

        return view('seo::livewire.seo-project-detail', [
            'keywordSummary' => $keywordSummary,
            'budgetSummary' => $budgetSummary,
            'visibility' => $visibility,
            'recentSignals' => $recentSignals,
        ])->layout('platform::layouts.app');
    }
}
