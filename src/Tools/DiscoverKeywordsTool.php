<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoKeywordService;

class DiscoverKeywordsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.discover.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/discover - Entdeckt neue Keywords via DataForSEO. Entweder seed_keywords (Array) ODER domain angeben. Optional: limit (Standard: 100). Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'seed_keywords' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Seed-Keywords für Related-Suche',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Domain für Keyword-Discovery (Alternative zu seed_keywords)',
                ],
                'limit' => [
                    'type' => 'integer',
                    'description' => 'Max. Anzahl Keywords (Standard: 100)',
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

            $settings = SeoTeamSettings::where('team_id', $team->id)->first();
            if (!$settings) {
                return ToolResult::error('Keine SEO-Einstellungen für dieses Team konfiguriert.', 'NOT_CONFIGURED');
            }

            $service = app(SeoKeywordService::class);
            $limit = (int) ($arguments['limit'] ?? 100);

            if (!empty($arguments['domain'])) {
                $result = $service->discoverFromDomain($settings, $arguments['domain'], $context->user, $limit);
            } elseif (!empty($arguments['seed_keywords'])) {
                $result = $service->discoverKeywords($settings, $arguments['seed_keywords'], $context->user, $limit);
            } else {
                return ToolResult::error('Entweder seed_keywords oder domain angeben.', 'VALIDATION_ERROR');
            }

            return ToolResult::success([
                'result' => $result,
                'message' => 'Keyword-Discovery abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
