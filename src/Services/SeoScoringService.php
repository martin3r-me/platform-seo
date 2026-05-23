<?php

namespace Platform\Seo\Services;

use Platform\Seo\Models\SeoProject;
use Platform\Seo\Models\SeoUrl;

class SeoScoringService
{
    /**
     * Calculate a visibility score for a project based on ranking data.
     */
    public function getVisibilityScore(SeoProject $project): array
    {
        $keywords = $project->keywords()
            ->wherePivotNotNull('position')
            ->whereNotNull('search_volume')
            ->get();

        if ($keywords->isEmpty()) {
            return [
                'score' => 0,
                'max_score' => 0,
                'percentage' => 0,
                'keywords_with_position' => 0,
                'breakdown' => [],
            ];
        }

        $totalScore = 0;
        $maxScore = 0;
        $breakdown = [];

        foreach ($keywords as $keyword) {
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

        usort($breakdown, fn($a, $b) => $b['score'] <=> $a['score']);

        return [
            'score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0,
            'keywords_with_position' => $keywords->count(),
            'breakdown' => array_slice($breakdown, 0, 20),
        ];
    }

    /**
     * Score keywords by opportunity (volume / difficulty ratio).
     */
    public function getKeywordScores(SeoProject $project): array
    {
        $keywords = $project->keywords()
            ->whereNotNull('search_volume')
            ->where('search_volume', '>', 0)
            ->get();

        $scored = [];
        foreach ($keywords as $keyword) {
            $volume = $keyword->search_volume;
            $difficulty = $keyword->keyword_difficulty ?? 50;
            $opportunityScore = round(($volume / max($difficulty, 1)) * 10, 2);

            $position = $keyword->pivot->position;
            $cpcValue = $keyword->cpc_cents ? ($keyword->cpc_cents / 100) : 0;
            $trafficValue = $position
                ? round($volume * $this->estimateCtr($position) * $cpcValue, 2)
                : 0;

            $scored[] = [
                'keyword' => $keyword->keyword,
                'keyword_id' => $keyword->id,
                'cluster' => $keyword->cluster?->name,
                'search_volume' => $volume,
                'keyword_difficulty' => $difficulty,
                'position' => $position,
                'opportunity_score' => $opportunityScore,
                'traffic_value' => $trafficValue,
                'seasonality_index' => $keyword->seasonality_index,
            ];
        }

        usort($scored, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);

        return [
            'keywords' => $scored,
            'count' => count($scored),
        ];
    }

    /**
     * Calculate visibility score for a single URL based on its keyword positions.
     */
    public function getUrlVisibilityScore(SeoUrl $url): array
    {
        $keywords = $url->keywords;
        $totalScore = 0;
        $maxScore = 0;
        $breakdown = [];

        foreach ($keywords as $keyword) {
            $position = $keyword->pivot->position;
            if ($position === null || $keyword->search_volume === null) {
                continue;
            }

            $ctr = $this->estimateCtr($position);
            $keywordScore = $keyword->search_volume * $ctr;
            $maxKeywordScore = $keyword->search_volume * $this->estimateCtr(1);

            $totalScore += $keywordScore;
            $maxScore += $maxKeywordScore;

            $breakdown[] = [
                'keyword' => $keyword->keyword,
                'position' => $position,
                'search_volume' => $keyword->search_volume,
                'ctr' => round($ctr, 4),
                'score' => round($keywordScore, 2),
            ];
        }

        usort($breakdown, fn ($a, $b) => $b['score'] <=> $a['score']);

        return [
            'score' => round($totalScore, 2),
            'max_score' => round($maxScore, 2),
            'percentage' => $maxScore > 0 ? round(($totalScore / $maxScore) * 100, 1) : 0,
            'keywords_with_position' => count($breakdown),
            'breakdown' => array_slice($breakdown, 0, 20),
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
}
