<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoUrlService;

class EnrichUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.enrichment.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/urls/enrichment - Stößt URL-Enrichment an (On-Page, Rankings, Backlinks etc.). Optional: url (bestimmte URL enrichen) oder ohne für alle fälligen URLs. Optional: collectors (Array von Collector-Namen zum Einschränken). Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url' => [
                    'type' => 'string',
                    'description' => 'Bestimmte URL enrichen (URL-String). Ohne: alle fälligen URLs.',
                ],
                'url_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'URL-IDs enrichen (Alternative zu url).',
                ],
                'collectors' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Nur bestimmte Collectors ausführen (on_page, serp_ranking, keyword_metrics, gsc, backlinks)',
                ],
                'force' => [
                    'type' => 'boolean',
                    'description' => 'Erzwingt Enrichment auch wenn URL noch nicht fällig ist.',
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

            $service = app(SeoUrlService::class);
            $result = $service->enrich(
                $team->id,
                $arguments['url'] ?? null,
                $arguments['collectors'] ?? [],
                $arguments['force'] ?? false,
                $arguments['url_ids'] ?? null,
            );

            return ToolResult::success([
                'result' => $result,
                'message' => 'Enrichment abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
