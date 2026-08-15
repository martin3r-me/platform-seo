<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

/**
 * Füllt das search_intent der Keywords via DataForSeo Labs (Bulk).
 */
class ClassifyIntentTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.intent.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords/intent - Klassifiziert Keywords nach Suchintention (informational/navigational/'
            . 'commercial/transactional) via DataForSeo Labs (Bulk bis 1000/Call). Standard: nur Keywords ohne Intent. '
            . 'Optional: limit, only_missing (Default true), dry_run.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => ['type' => 'integer', 'description' => 'Max. Keywords (Default 1000)'],
                'only_missing' => ['type' => 'boolean', 'description' => 'Nur Keywords ohne Intent (Default true)'],
                'dry_run' => ['type' => 'boolean', 'description' => 'Nur zählen/schätzen'],
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

            $result = app(SeoKeywordService::class)->classifyIntentForTeam($team->id, $context->user, [
                'limit' => $arguments['limit'] ?? 1000,
                'only_missing' => $arguments['only_missing'] ?? true,
                'dry_run' => $arguments['dry_run'] ?? false,
            ]);

            if (!empty($result['error'])) {
                return ToolResult::error($result['error'], 'FETCH_ERROR');
            }

            $msg = !empty($result['dry_run'])
                ? "{$result['candidates']} Keywords ohne Intent, geschätzte Kosten {$result['estimated_cost_cents']} Cent."
                : "{$result['classified']}/{$result['candidates']} Keywords klassifiziert ({$result['cost_cents']} Cent).";

            return ToolResult::success(array_merge($result, ['message' => $msg]));
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
