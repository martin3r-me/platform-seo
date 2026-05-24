<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoUrlService;

class RegisterUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/urls - Registriert eine oder mehrere URLs für SEO-Tracking. Parameter: urls (Array von URL-Strings, required). Optional: is_own (Standard: true, false für Wettbewerber-URLs).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'urls' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Array von URLs zum Registrieren',
                ],
                'is_own' => [
                    'type' => 'boolean',
                    'description' => 'Eigene URLs (true, Standard) oder Wettbewerber (false)',
                ],
            ],
            'required' => ['urls'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $urls = $arguments['urls'] ?? [];
            if (empty($urls)) {
                return ToolResult::error('Keine URLs angegeben.', 'VALIDATION_ERROR');
            }

            $urlService = app(SeoUrlService::class);
            $isOwn = $arguments['is_own'] ?? true;
            $results = [];

            foreach ($urls as $url) {
                $result = $urlService->register($team->id, trim($url), [
                    'is_own' => $isOwn,
                    'reason' => 'mcp',
                ]);
                $results[] = [
                    'url' => trim($url),
                    'url_id' => $result['url_id'] ?? null,
                    'created' => $result['created'] ?? false,
                ];
            }

            $created = count(array_filter($results, fn ($r) => $r['created']));

            return ToolResult::success([
                'results' => $results,
                'total' => count($results),
                'created' => $created,
                'existing' => count($results) - $created,
                'message' => "{$created} neue URL(s) registriert, " . (count($results) - $created) . " bereits vorhanden.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
