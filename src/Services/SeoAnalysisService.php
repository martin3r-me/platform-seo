<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoKeywordPosition;
use Platform\Seo\Models\SeoProject;

class SeoAnalysisService
{
    public function getKeywordSummary(SeoProject $project): array
    {
        $keywords = $project->keywords;

        return [
            'total_keywords' => $keywords->count(),
            'clusters_count' => $project->clusters()->count(),
            'avg_search_volume' => (int) $keywords->avg('search_volume'),
            'avg_difficulty' => (int) $keywords->avg('keyword_difficulty'),
            'total_search_volume' => (int) $keywords->sum('search_volume'),
            'intents' => $keywords->pluck('search_intent')->filter()->countBy()->toArray(),
            'priorities' => $keywords->pluck('priority')->filter()->countBy()->toArray(),
            'with_metrics' => $keywords->whereNotNull('search_volume')->count(),
            'without_metrics' => $keywords->whereNull('search_volume')->count(),
        ];
    }

    public function getRankingTrends(SeoProject $project, int $days = 30): array
    {
        $keywords = $project->keywords()->with('cluster')->get();
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
                ->where('tracked_at', '>=', $since)
                ->orderBy('tracked_at')
                ->get();

            if ($snapshots->isEmpty()) {
                $trends['no_data'][] = [
                    'keyword' => $keyword->keyword,
                    'cluster' => $keyword->cluster?->name,
                    'current_position' => $keyword->position,
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

    public function getCompetitorGaps(SeoProject $project): array
    {
        $keywords = $project->keywords()->with(['cluster', 'competitors'])->get();

        $gaps = [];
        $domains = [];

        foreach ($keywords as $keyword) {
            if ($keyword->competitors->isEmpty()) {
                continue;
            }

            $isGap = empty($keyword->published_url) || $keyword->position === null;

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
                    'our_position' => $keyword->position,
                    'published_url' => $keyword->published_url,
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

    public function getQuickWins(SeoProject $project): array
    {
        $keywords = $project->keywords()->with('cluster')
            ->whereNotNull('search_volume')
            ->where('search_volume', '>', 0)
            ->where(function ($q) {
                $q->where('keyword_difficulty', '<', 40)
                    ->orWhereNull('keyword_difficulty');
            })
            ->where(function ($q) {
                $q->where('content_status', 'none')
                    ->orWhereNull('content_status');
            })
            ->orderByDesc('search_volume')
            ->get();

        $quickWins = [];
        foreach ($keywords as $keyword) {
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

        usort($quickWins, fn($a, $b) => ($b['opportunity_score'] ?? 0) <=> ($a['opportunity_score'] ?? 0));

        return [
            'quick_wins' => $quickWins,
            'count' => count($quickWins),
            'total_search_volume' => array_sum(array_column($quickWins, 'search_volume')),
        ];
    }

    public function getContentGaps(SeoProject $project): array
    {
        $keywords = $project->keywords()->with('cluster')
            ->where(function ($q) {
                $q->where('content_status', 'none')
                    ->orWhere('content_status', 'planned')
                    ->orWhereNull('content_status');
            })
            ->get();

        $clusterGaps = [];
        foreach ($keywords as $keyword) {
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
                'content_status' => $keyword->content_status ?? 'none',
                'search_intent' => $keyword->search_intent,
            ];
            $clusterGaps[$clusterName]['total_search_volume'] += ($keyword->search_volume ?? 0);
        }

        $clusterGaps = array_values($clusterGaps);
        usort($clusterGaps, fn($a, $b) => $b['total_search_volume'] <=> $a['total_search_volume']);

        return [
            'clusters' => $clusterGaps,
            'clusters_with_gaps' => count($clusterGaps),
            'total_gaps' => $keywords->count(),
        ];
    }

    public function getClusterHealth(SeoProject $project): array
    {
        $clusters = $project->clusters()->with('keywords')->get();

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

            $contentStatuses = $keywords->groupBy(fn($kw) => $kw->content_status ?? 'none')
                ->map->count()
                ->toArray();

            $withContent = ($contentStatuses['published'] ?? 0) + ($contentStatuses['optimized'] ?? 0);
            $coverageScore = round(($withContent / $total) * 100, 1);

            $withPosition = $keywords->whereNotNull('position');
            $avgPosition = $withPosition->isNotEmpty()
                ? round($withPosition->avg('position'), 1)
                : null;

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
                'avg_position' => $avgPosition,
                'avg_search_volume' => (int) $keywords->avg('search_volume'),
                'total_search_volume' => (int) $keywords->sum('search_volume'),
                'content_status_distribution' => $contentStatuses,
                'health' => $healthStatus,
            ];
        }

        usort($health, fn($a, $b) => $a['coverage_score'] <=> $b['coverage_score']);

        return [
            'clusters' => $health,
            'clusters_count' => count($health),
        ];
    }

    public function getDefend(SeoProject $project): array
    {
        $keywords = $project->keywords()->with('cluster')
            ->whereNotNull('position')
            ->where('position', '>=', 1)
            ->where('position', '<=', 3)
            ->orderByDesc('search_volume')
            ->get();

        $defend = [];
        foreach ($keywords as $keyword) {
            $defend[] = [
                'keyword' => $keyword->keyword,
                'keyword_id' => $keyword->id,
                'cluster' => $keyword->cluster?->name,
                'search_volume' => $keyword->search_volume,
                'position' => $keyword->position,
                'keyword_difficulty' => $keyword->keyword_difficulty,
                'content_status' => $keyword->content_status,
                'published_url' => $keyword->published_url,
                'traffic_value' => $keyword->search_volume && $keyword->cpc_cents
                    ? round(($keyword->search_volume * $keyword->cpc_cents / 100) * $this->estimateCtr($keyword->position), 2)
                    : null,
            ];
        }

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
