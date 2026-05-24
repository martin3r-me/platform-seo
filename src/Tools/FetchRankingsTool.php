<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

class FetchRankingsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.rankings.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/rankings - Holt aktuelle SERP-Positionen für alle Team-Keywords via DataForSEO. Erstellt Position-Snapshots, trackt Veränderungen, erkennt Wettbewerber. Verbraucht API-Budget!';
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

            $service = app(SeoKeywordService::class);
            $result = $service->fetchRankings($team->id, $context->user);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            $response = [
                'fetched' => $result['fetched'],
                'position_snapshots' => $result['position_snapshots'],
                'cost_cents' => $result['cost_cents'],
                'message' => $result['fetched'] . ' Keywords geprüft, ' . $result['position_snapshots'] . ' Positionen getrackt (' . $result['cost_cents'] . ' Cent).',
            ];

            if (!empty($result['top_competitors'])) {
                $response['top_competitors'] = array_slice($result['top_competitors'], 0, 10);
            }

            return ToolResult::success($response);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
