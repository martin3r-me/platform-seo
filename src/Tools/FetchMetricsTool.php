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
        return 'POST /seo/keywords/metrics - Holt Suchvolumen & CPC (Bulk, bis 1000 Keywords/Call) von DataForSEO und '
            . 'aktualisiert die seo_keywords Tabelle. Standard: gesamter Team-Pool, nur veraltete Keywords (>7 Tage). '
            . 'Gezielt messen: url_id ODER cluster_id ODER keywords (Array exakter Keyword-Strings) einschränken. '
            . 'force=true frischt auch nicht-veraltete auf. Verbraucht API-Budget (Bulk = günstig).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords dieser URL auffrischen',
                ],
                'cluster_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords dieses Clusters auffrischen',
                ],
                'keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur diese exakten Keywords auffrischen (müssen bereits im Pool sein)',
                ],
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

            $options = array_filter([
                'url_id' => $arguments['url_id'] ?? null,
                'cluster_id' => $arguments['cluster_id'] ?? null,
                'keywords' => $arguments['keywords'] ?? null,
                'force' => $arguments['force'] ?? null,
            ], fn ($v) => $v !== null);

            $service = app(SeoKeywordService::class);
            $result = $service->fetchMetrics($team->id, null, $context->user, $options);

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
