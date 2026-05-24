<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoSignal;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoBudgetGuardService;
use Platform\Seo\Services\SeoScoringService;

class DashboardTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.dashboard.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/dashboard - SEO-Übersicht mit Visibility-Score, Keyword-Count, URL-Count, offenen Signalen, Budget-Status und Cluster-Verteilung. Gibt einen schnellen Überblick über den SEO-Stand des Teams.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $settings = SeoTeamSettings::where('team_id', $team->id)->first();

            // Counts
            $urlsTotal = SeoUrl::where('team_id', $team->id)->count();
            $urlsOwn = SeoUrl::where('team_id', $team->id)->where('is_own', true)->count();
            $urlsCompetitor = $urlsTotal - $urlsOwn;
            $keywordsTotal = SeoKeyword::where('team_id', $team->id)->count();
            $clustersTotal = SeoKeywordCluster::where('team_id', $team->id)->count();
            $signalsOpen = SeoSignal::where('team_id', $team->id)->whereIn('status', ['new', 'acknowledged'])->count();
            $signalsCritical = SeoSignal::where('team_id', $team->id)->where('status', 'new')->where('severity', 'critical')->count();

            // Visibility
            $visibility = null;
            if ($settings) {
                try {
                    $visibility = app(SeoScoringService::class)->getVisibilityScore($settings);
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            // Budget
            $budget = null;
            if ($settings) {
                try {
                    $budget = app(SeoBudgetGuardService::class)->getBudgetSummary($settings);
                } catch (\Throwable $e) {
                    // Ignore
                }
            }

            // Top keywords by search volume
            $topKeywords = SeoKeyword::where('team_id', $team->id)
                ->whereNotNull('search_volume')
                ->orderByDesc('search_volume')
                ->limit(10)
                ->get(['id', 'keyword', 'search_volume', 'keyword_difficulty', 'search_intent']);

            return ToolResult::success([
                'domain' => $settings?->domain,
                'counts' => [
                    'urls_total' => $urlsTotal,
                    'urls_own' => $urlsOwn,
                    'urls_competitor' => $urlsCompetitor,
                    'keywords' => $keywordsTotal,
                    'clusters' => $clustersTotal,
                    'signals_open' => $signalsOpen,
                    'signals_critical' => $signalsCritical,
                ],
                'visibility' => $visibility,
                'budget' => $budget,
                'top_keywords' => $topKeywords->map(fn ($kw) => [
                    'id' => $kw->id,
                    'keyword' => $kw->keyword,
                    'search_volume' => $kw->search_volume,
                    'keyword_difficulty' => $kw->keyword_difficulty,
                    'search_intent' => $kw->search_intent,
                ])->all(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
