<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

class FetchMetricsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.metrics.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/metrics - Holt Suchvolumen, CPC und Wettbewerbsdaten von DataForSEO für alle Keywords des Teams. Aktualisiert seo_keywords Tabelle. Nur veraltete Keywords (>7 Tage) werden abgefragt. Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Alle Keywords aktualisieren, auch wenn noch nicht veraltet',
                ],
            ],
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
            $result = $service->fetchMetrics($team->id, null, $context->user);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            return ToolResult::success([
                'result' => $result,
                'message' => $result['fetched'] . ' Keywords aktualisiert (' . ($result['cost_cents'] ?? 0) . ' Cent).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
