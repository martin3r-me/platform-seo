<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Seo\Livewire\Concerns\ResolvesTeamSettings;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlSnapshot;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoScoringService;
use Platform\Seo\Services\SeoUrlService;

class SeoProjectDashboard extends Component
{
    use ResolvesTeamSettings;

    public bool $showSettingsModal = false;
    public string $editDomain = '';

    public function mount()
    {
        $this->resolveSettings();
    }

    public function openSettingsModal()
    {
        $this->editDomain = $this->seoSettings->domain ?? '';
        $this->showSettingsModal = true;
    }

    public function saveSettings()
    {
        $this->validate([
            'editDomain' => 'nullable|string|max:255',
        ]);

        $this->seoSettings->update([
            'domain' => $this->editDomain ?: null,
        ]);

        $this->showSettingsModal = false;
    }

    public function render()
    {
        $scoringService = app(SeoScoringService::class);
        $budgetGuard = app(SeoBudgetGuardService::class);
        $urlService = app(SeoUrlService::class);

        $teamId = $this->seoSettings->team_id;

        $visibility = $scoringService->getVisibilityScore($this->seoSettings);
        $budgetSummary = $budgetGuard->getBudgetSummary($this->seoSettings);

        // URL counts
        $urlCounts = [
            'total' => SeoUrl::where('team_id', $teamId)->count(),
            'own' => SeoUrl::where('team_id', $teamId)->where('is_own', true)->count(),
            'competitor' => SeoUrl::where('team_id', $teamId)->where('is_own', false)->count(),
        ];

        // Keyword count from URLs
        $keywordCount = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->sum('keyword_count');

        // Position distribution
        $positionDistribution = $urlService->getVisibilitySummary(
            $teamId,
            $this->seoSettings->domain
        )['position_distribution'] ?? [];

        // Visibility history from snapshots
        $visibilityHistory = SeoUrlSnapshot::whereHas('url', fn ($q) => $q->where('team_id', $teamId)->where('is_own', true))
            ->where('snapshot_date', '>=', now()->subDays(30))
            ->selectRaw('snapshot_date, SUM(visibility_score) as total_visibility')
            ->groupBy('snapshot_date')
            ->orderBy('snapshot_date')
            ->pluck('total_visibility')
            ->values()
            ->toArray();

        // Top URLs by visibility
        $topUrls = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->where('status', 'active')
            ->orderByDesc('visibility_score')
            ->take(10)
            ->get();

        // Recent signals
        $recentSignals = SeoSignal::where('team_id', $teamId)
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
