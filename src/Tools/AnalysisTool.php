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
                    'enum' => ['ranking_trends', 'competitor_gaps', 'visibility', 'quick_wins', 'content_gaps', 'cluster_health', 'defend', 'summary', 'rankings', 'competitors', 'keywords', 'overview'],
                    'description' => 'Art der Analyse. Aliase: rankings=ranking_trends, competitors=competitor_gaps, keywords/overview=summary',
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'Zeitraum für ranking_trends (Standard: 30)',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Optional: schränkt die "summary"-Analyse auf Keywords ein, für die eine URL dieser Domain rankt (z.B. "tm-foodsolutions.de"). Wird bei anderen Typen ignoriert.',
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

            // Aliases für gebräuchliche Alternativnamen
            $type = match ($type) {
                'rankings', 'ranking' => 'ranking_trends',
                'competitors', 'competitor', 'gaps' => 'competitor_gaps',
                'keywords', 'overview', 'on_page', 'onpage', 'metadata' => 'summary',
                default => $type,
            };

            $data = match ($type) {
                'ranking_trends' => $service->getRankingTrendsForTeam($team->id, (int) ($arguments['days'] ?? 30)),
                'competitor_gaps' => $service->getCompetitorGapsForTeam($team->id),
                'visibility' => $service->getVisibilityScoreForTeam($team->id),
                'quick_wins' => $service->getQuickWinsForTeam($team->id),
                'content_gaps' => $service->getContentGaps($team->id),
                'cluster_health' => $service->getClusterHealth($team->id),
                'defend' => $service->getDefend($team->id),
                'summary' => $service->getKeywordSummary($team->id, $this->normalizeDomain($arguments['domain'] ?? null)),
                default => null,
            };

            if ($data === null) {
                $validTypes = 'ranking_trends, competitor_gaps, visibility, quick_wins, content_gaps, cluster_health, defend, summary';
                return ToolResult::error("Unbekannter Analyse-Typ: '{$type}'. Verfügbar: {$validTypes}", 'VALIDATION_ERROR');
            }

            return ToolResult::success([
                'type' => $type,
                'data' => $data,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    /**
     * Normalisiert eine Domain-Eingabe: entfernt Schema, Pfad und führendes "www.".
     */
    private function normalizeDomain(mixed $input): ?string
    {
        if (!is_string($input) || trim($input) === '') {
            return null;
        }
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z]+://#', '', $host);
        $host = explode('/', $host, 2)[0];
        $host = preg_replace('#^www\.#', '', $host);
        $host = trim($host);

        return $host !== '' ? $host : null;
    }
}
