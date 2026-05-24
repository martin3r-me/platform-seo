<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoUrlService;

class CannibalizationTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cannibalization.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/cannibalization - Erkennt Keyword-Kannibalisierung: Keywords, für die mehrere eigene URLs ranken und sich gegenseitig Sichtbarkeit wegnehmen.';
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

            $service = app(SeoUrlService::class);
            $data = $service->getCannibalization($team->id);

            return ToolResult::success([
                'cannibalization' => $data,
                'total' => is_array($data) ? count($data) : 0,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
