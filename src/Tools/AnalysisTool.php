<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoAnalysisService;

class AnalysisTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.analysis.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/analysis - SEO-Analysen abrufen. Parameter: type (required) — "ranking_trends" (Ranking-Entwicklung, optional: days), "competitor_gaps" (Lücken vs. Wettbewerber), "visibility" (Sichtbarkeits-Score), "quick_wins" (Low-Hanging-Fruit Keywords), "content_gaps" (fehlende Inhalte), "cluster_health" (Cluster-Qualität), "defend" (zu verteidigende Positionen), "summary" (Keyword-Zusammenfassung).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'type' => [
                    'type' => 'string',
                    'enum' => ['ranking_trends', 'competitor_gaps', 'visibility', 'quick_wins', 'content_gaps', 'cluster_health', 'defend', 'summary'],
                    'description' => 'Art der Analyse',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Zeitraum für ranking_trends (Standard: 30)',
                ],
            ],
            'required' => ['type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $service = app(SeoAnalysisService::class);
            $type = $arguments['type'] ?? '';

            $data = match ($type) {
                'ranking_trends' => $service->getRankingTrendsForTeam($team->id, (int) ($arguments['days'] ?? 30)),
                'competitor_gaps' => $service->getCompetitorGapsForTeam($team->id),
                'visibility' => $service->getVisibilityScoreForTeam($team->id),
                'quick_wins' => $service->getQuickWinsForTeam($team->id),
                'content_gaps' => $service->getContentGaps($team->id),
                'cluster_health' => $service->getClusterHealth($team->id),
                'defend' => $service->getDefend($team->id),
                'summary' => $service->getKeywordSummary($team->id),
                default => null,
            };

            if ($data === null) {
                return ToolResult::error("Unbekannter Analyse-Typ: {$type}", 'VALIDATION_ERROR');
            }

            return ToolResult::success([
                'type' => $type,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
