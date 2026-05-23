<?php

namespace Platform\Seo\Services;

use Platform\Core\Contracts\SeoAnalysisServiceInterface;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;
use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;

class SeoAnalysisService implements SeoAnalysisServiceInterface
{
    // =========================================================================
    // Contract: SeoAnalysisServiceInterface (teamId-based)
    // =========================================================================

    public function getRankingTrends(int $teamId, int $days = 30): array
    {
        return $this->getRankingTrendsForTeam($teamId, $days);
    }

    public function getCompetitorGaps(int $teamId): array
    {
        return $this->getCompetitorGapsForTeam($teamId);
    }

    public function getVisibilityScore(int $teamId): array
    {
        return $this->getVisibilityScoreForTeam($teamId);
    }

    public function getQuickWins(int $teamId): array
    {
        return $this->getQuickWinsForTeam($teamId);
    }

    // =========================================================================
    // Internal methods (team-based, used by UI)
    // =========================================================================

    public function getKeywordSummary(int $teamId): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)->get();
        $clustersCount = SeoKeywordCluster::where('team_id', $teamId)->count();

        return [
            'total_keywords' => $keywords->count(),
            'clusters_count' => $clustersCount,
            'avg_search_volume' => (int) $keywords->avg('search_volume'),
            'avg_difficulty' => (int) $keywords->avg('keyword_difficulty'),
            'total_search_volume' => (int) $keywords->sum('search_volume'),
            'intents' => $keywords->pluck('search_intent')->filter()->countBy()->toArray(),
            'with_metrics' => $keywords->whereNotNull('search_volume')->count(),
            'without_metrics' => $keywords->whereNull('search_volume')->count(),
        ];
    }

    public function getRankingTrendsForTeam(int $teamId, int $days = 30): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)->with('cluster')->get();
        $since = now()->subDays($days);

        $trends = [
            'rising' => [],
            'falling' => [],
            'stable' => [],
            'new_entries' => [],
            'no_data' => [],
        ];

        foreach ($keywords as $keyword) {
            $snapshots = SeoKeywordPosition::where('keyword_id', $keyword->id)
                ->where('team_id', $teamId)
                ->where('tracked_at', '>=', $since)
                ->orderBy('tracked_at')
                ->get();

            // Get best position from URL pivot
            $bestUrl = $keyword->urls()
                ->wherePivotNotNull('position')
                ->orderBy('seo_url_keywords.position')
                ->first();
            $currentPosition = $bestUrl?->pivot->position;

            if ($snapshots->isEmpty()) {
                $trends['no_data'][] = [
                    'keyword' => $keyword->keyword,
                    'cluster' => $keyword->cluster?->name,
                    'current_position' => $currentPosition,
                ];
                continue;
            }

            $firstSnapshot = $snapshots->first();
            $lastSnapshot = $snapshots->last();
            $positionChange = $firstSnapshot->position - $lastSnapshot->position;

            $entry = [
                'keyword' => $keyword->keyword,
                'cluster' => $keyword->cluster?->name,
                'current_position' => $lastSnapshot->position,
                'start_position' => $firstSnapshot->position,
                'position_change' => $positionChange,
                'snapshots_count' => $snapshots->count(),
                'best_position' => $snapshots->min('position'),
                'worst_position' => $snapshots->max('position'),
            ];

            if ($firstSnapshot->previous_position === null && $snapshots->count() <= 2) {
                $trends['new_entries'][] = $entry;
            } elseif ($positionChange > 2) {
                $trends['rising'][] = $entry;
            } elseif ($positionChange < -2) {
                $trends['falling'][] = $entry;
            } else {
                $trends['stable'][] = $entry;
            }
        }

        usort($trends['rising'], fn($a, $b) => $b['position_change'] <=> $a['position_change']);
        usort($trends['falling'], fn($a, $b) => $a['position_change'] <=> $b['position_change']);

        return [
            'period_days' => $days,
            'since' => $since->toIso8601String(),
            'summary' => [
                'rising_count' => count($trends['rising']),
                'falling_count' => count($trends['falling']),
                'stable_count' => count($trends['stable']),
                'new_entries_count' => count($trends['new_entries']),
                'no_data_count' => count($trends['no_data']),
            ],
            'rising' => $trends['rising'],
            'falling' => $trends['falling'],
            'stable' => $trends['stable'],
            'new_entries' => $trends['new_entries'],
        ];
    }

    public function getCompetitorGapsForTeam(int $teamId): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)
            ->with(['cluster', 'competitors', 'urls' => fn ($q) => $q->where('seo_urls.is_own', true)])
            ->get();

        $gaps = [];
        $domains = [];

        foreach ($keywords as $keyword) {
            if ($keyword->competitors->isEmpty()) {
                continue;
            }

            // Check if any own URL ranks for this keyword
            $ownUrl = $keyword->urls->first();
            $ownPosition = $ownUrl?->pivot->position;
            $isGap = !$ownUrl || $ownPosition === null;

            foreach ($keyword->competitors as $comp) {
                $domains[$comp->domain] = ($domains[$comp->domain] ?? 0) + 1;
            }

            if ($isGap) {
                $gaps[] = [
                    'keyword' => $keyword->keyword,
                    'keyword_id' => $keyword->id,
                    'cluster' => $keyword->cluster?->name,
                    'search_volume' => $keyword->search_volume,
                    'keyword_difficulty' => $keyword->keyword_difficulty,
                    'our_position' => $ownPosition,
                    'competitors' => $keyword->competitors->map(fn ($c) => [
                        'domain' => $c->domain,
                        'url' => $c->url,
                        'position' => $c->position,
                    ])->values()->toArray(),
                    'best_competitor_position' => $keyword->competitors->min('position'),
                    'opportunity_score' => $this->calculateOpportunityScore($keyword),
                ];
            }
        }

        usort($gaps, fn($a, $b) => ($b['opportunity_score'] ?? 0) <=> ($a['opportunity_score'] ?? 0));
        arsort($domains);

        return [
            'gaps' => $gaps,
            'gaps_count' => count($gaps),
            'total_keywords' => $keywords->count(),
            'keywords_with_competitors' => $keywords->filter(fn ($kw) => $kw->competitors->isNotEmpty())->count(),
            'top_competitor_domains' => array_slice($domains, 0, 10, true),
        ];
    }

    public function getVisibilityScoreForTeam(int $teamId): array
    {
        $urls = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->where('status', 'active')
            ->with(['keywords' => fn ($q) => $q->whereNotNull('seo_url_keywords.position')->whereNotNull('search_volume')])
            ->get();

        $totalScore = 0;
        $maxScore = 0;
        $breakdown = [];

        foreach ($urls as $url) {
            foreach ($url->keywords as $keyword) {
                $position = $keyword->pivot->position;
                $ctr = $this->estimateCtr($position);
                $keywordScore = ($keyword->search_volume * $ctr);
                $maxKeywordScore = ($keyword->search_volume * $this->estimateCtr(1));

                $totalScore += $keywordScore;
                $maxScore += $maxKeywordScore;

                $breakdown[] = [
                    'keyword' => $keyword->keyword,
                    'position' => $position,
                    'search_volume' => $keyword->search_volume,
                    'ctr' => round($ctr, 4),
                    'score' => round($keywordScore, 2),
                    'max_score' => round($maxKeywordScore, 2),
                ];
            }
        }

        usort($breakdown, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0,
            'keywords_with_position' => count($breakdown),
            'breakdown' => array_slice($breakdown, 0, 20),
        ];
    }

    public function getQuickWinsForTeam(int $teamId): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)
            ->with('cluster')
            ->whereNotNull('search_volume')
            ->where('search_volume', '>', 0)
            ->where(function ($q) {
                $q->where('keyword_difficulty', '<', 40)
                    ->orWhereNull('keyword_difficulty');
            })
            ->orderByDesc('search_volume')
            ->get();

        // Filter to keywords without a ranking
        $quickWins = [];
        foreach ($keywords as $keyword) {
            $hasRanking = $keyword->urls()
                ->where('seo_urls.is_own', true)
                ->wherePivotNotNull('position')
                ->exists();

            if (!$hasRanking) {
                $quickWins[] = [
                    'keyword' => $keyword->keyword,
                    'keyword_id' => $keyword->id,
                    'cluster' => $keyword->cluster?->name,
                    'search_volume' => $keyword->search_volume,
                    'keyword_difficulty' => $keyword->keyword_difficulty,
                    'search_intent' => $keyword->search_intent,
                    'opportunity_score' => $this->calculateOpportunityScore($keyword),
                ];
            }
        }

        usort($quickWins, fn($a, $b) => ($b['opportunity_score'] ?? 0) <=> ($a['opportunity_score'] ?? 0));

        return [
            'quick_wins' => $quickWins,
            'count' => count($quickWins),
            'total_search_volume' => array_sum(array_column($quickWins, 'search_volume')),
        ];
    }

    public function getContentGaps(int $teamId): array
    {
        $keywords = SeoKeyword::where('team_id', $teamId)
            ->with('cluster')
            ->get();

        // Filter keywords without own URL rankings
        $gapKeywords = $keywords->filter(function ($keyword) {
            return !$keyword->urls()
                ->where('seo_urls.is_own', true)
                ->wherePivotNotNull('position')
                ->exists();
        });

        $clusterGaps = [];
        foreach ($gapKeywords as $keyword) {
            $clusterName = $keyword->cluster?->name ?? '(Ohne Cluster)';
            if (!isset($clusterGaps[$clusterName])) {
                $clusterGaps[$clusterName] = [
                    'cluster' => $clusterName,
                    'keywords' => [],
                    'total_search_volume' => 0,
                ];
            }

            $clusterGaps[$clusterName]['keywords'][] = [
                'keyword' => $keyword->keyword,
                'keyword_id' => $keyword->id,
                'search_volume' => $keyword->search_volume,
                'keyword_difficulty' => $keyword->keyword_difficulty,
                'search_intent' => $keyword->search_intent,
            ];
            $clusterGaps[$clusterName]['total_search_volume'] += ($keyword->search_volume ?? 0);
        }

        $clusterGaps = array_values($clusterGaps);
        usort($clusterGaps, fn($a, $b) => $b['total_search_volume'] <=> $a['total_search_volume']);

        return [
            'clusters' => $clusterGaps,
            'clusters_with_gaps' => count($clusterGaps),
            'total_gaps' => $gapKeywords->count(),
        ];
    }

    public function getClusterHealth(int $teamId): array
    {
        $clusters = SeoKeywordCluster::where('team_id', $teamId)->with('keywords')->get();

        $health = [];
        foreach ($clusters as $cluster) {
            $keywords = $cluster->keywords;
            $total = $keywords->count();

            if ($total === 0) {
                $health[] = [
                    'cluster' => $cluster->name,
                    'color' => $cluster->color,
                    'keywords_count' => 0,
                    'coverage_score' => 0,
                    'health' => 'empty',
                ];
                continue;
            }

            // Check how many keywords have own URL rankings
            $withContent = 0;
            foreach ($keywords as $kw) {
                $hasOwnRanking = $kw->urls()
                    ->where('seo_urls.is_own', true)
                    ->wherePivotNotNull('position')
                    ->exists();
                if ($hasOwnRanking) {
                    $withContent++;
                }
            }

            $coverageScore = round(($withContent / $total) * 100, 1);

            $healthStatus = match (true) {
                $coverageScore < 25 => 'critical',
                $coverageScore < 50 => 'needs_work',
                $coverageScore < 75 => 'moderate',
                default => 'good',
            };

            $health[] = [
                'cluster' => $cluster->name,
                'color' => $cluster->color,
                'keywords_count' => $total,
                'coverage_score' => $coverageScore,
                'avg_search_volume' => (int) $keywords->avg('search_volume'),
                'total_search_volume' => (int) $keywords->sum('search_volume'),
                'health' => $healthStatus,
            ];
        }

        usort($health, fn($a, $b) => $a['coverage_score'] <=> $b['coverage_score']);

        return [
            'clusters' => $health,
            'clusters_count' => count($health),
        ];
    }

    public function getDefend(int $teamId): array
    {
        // Find keywords where own URLs rank in top 3
        $urls = SeoUrl::where('team_id', $teamId)
            ->where('is_own', true)
            ->with(['keywords' => fn ($q) => $q
                ->whereNotNull('seo_url_keywords.position')
                ->where('seo_url_keywords.position', '>=', 1)
                ->where('seo_url_keywords.position', '<=', 3)
                ->whereNotNull('search_volume')
                ->orderByDesc('search_volume')
            ])
            ->get();

        $defend = [];
        $seen = [];
        foreach ($urls as $url) {
            foreach ($url->keywords as $keyword) {
                if (isset($seen[$keyword->id])) {
                    continue;
                }
                $seen[$keyword->id] = true;

                $position = $keyword->pivot->position;
                $defend[] = [
                    'keyword' => $keyword->keyword,
                    'keyword_id' => $keyword->id,
                    'cluster' => $keyword->cluster?->name,
                    'search_volume' => $keyword->search_volume,
                    'position' => $position,
                    'keyword_difficulty' => $keyword->keyword_difficulty,
                    'traffic_value' => $keyword->search_volume && $keyword->cpc_cents
                        ? round(($keyword->search_volume * $keyword->cpc_cents / 100) * $this->estimateCtr($position), 2)
                        : null,
                ];
            }
        }

        usort($defend, fn($a, $b) => ($b['search_volume'] ?? 0) <=> ($a['search_volume'] ?? 0));

        return [
            'defend' => $defend,
            'count' => count($defend),
            'total_estimated_traffic_value' => round(array_sum(array_filter(array_column($defend, 'traffic_value'))), 2),
        ];
    }

    protected function estimateCtr(int $position): float
    {
        return match (true) {
            $position === 1 => 0.316,
            $position === 2 => 0.158,
            $position === 3 => 0.094,
            $position <= 5  => 0.06,
            $position <= 10 => 0.03,
            default => 0.01,
        };
    }

    protected function calculateOpportunityScore(mixed $keyword): float
    {
        $volume = $keyword->search_volume ?? 0;
        $difficulty = $keyword->keyword_difficulty ?? 50;

        if ($volume === 0) {
            return 0;
        }

        return round(($volume / max($difficulty, 1)) * 10, 2);
    }
}
