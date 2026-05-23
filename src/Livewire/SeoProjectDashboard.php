<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamProject;
use Platform\Seo\Models\SeoUrlSnapshot;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoScoringService;
use Platform\Seo\Services\SeoUrlService;

class SeoProjectDashboard extends Component
{
    use ResolvesTeamProject;

    public bool $showSettingsModal = false;
    public string $editName = '';
    public string $editDescription = '';
    public string $editDomain = '';

    public function mount()
    {
        $this->resolveProject();
    }

    public function openSettingsModal()
    {
        $this->editName = $this->seoProject->name;
        $this->editDescription = $this->seoProject->description ?? '';
        $this->editDomain = $this->seoProject->domain ?? '';
        $this->showSettingsModal = true;
    }

    public function saveSettings()
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

        $this->showSettingsModal = false;
    }

    public function render()
    {
        $scoringService = app(SeoScoringService::class);
        $budgetGuard = app(SeoBudgetGuardService::class);
        $urlService = app(SeoUrlService::class);

        $visibility = $scoringService->getVisibilityScore($this->seoProject);
        $budgetSummary = $budgetGuard->getBudgetSummary($this->seoProject);

        // URL counts
        $urlCounts = [
            'total' => $this->seoProject->urls()->count(),
            'own' => $this->seoProject->urls()->where('is_own', true)->count(),
            'competitor' => $this->seoProject->urls()->where('is_own', false)->count(),
        ];

        // Keyword count from URLs
        $keywordCount = $this->seoProject->urls()
            ->where('is_own', true)
            ->sum('keyword_count');

        // Position distribution
        $positionDistribution = $urlService->getVisibilitySummary(
            $this->seoProject->team_id,
            $this->seoProject->domain
        )['position_distribution'] ?? [];

        // Visibility history from snapshots
        $visibilityHistory = SeoUrlSnapshot::whereHas('url', fn ($q) => $q->where('project_id', $this->seoProject->id)->where('is_own', true))
            ->where('snapshot_date', '>=', now()->subDays(30))
            ->selectRaw('snapshot_date, SUM(visibility_score) as total_visibility')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->pluck('total_visibility')
            ->values()
            ->toArray();

        // Top URLs by visibility
        $topUrls = $this->seoProject->urls()
            ->where('is_own', true)
            ->where('status', 'active')
            ->orderByDesc('visibility_score')
            ->take(10)
            ->get();

        // Recent signals
        $recentSignals = $this->seoProject->signals()
            ->with(['keyword', 'url'])
            ->where('status', 'new')
            ->orderByDesc('detected_at')
            ->take(5)
            ->get();

        return view('seo::livewire.seo-project-dashboard', [
            'visibility' => $visibility,
            'budgetSummary' => $budgetSummary,
            'urlCounts' => $urlCounts,
            'keywordCount' => $keywordCount,
            'positionDistribution' => $positionDistribution,
            'visibilityHistory' => $visibilityHistory,
            'topUrls' => $topUrls,
            'recentSignals' => $recentSignals,
        ])->layout('platform::layouts.app');
    }
}
