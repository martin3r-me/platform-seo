<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;

class UpdateUrlTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/urls - Aktualisiert Eigenschaften einer oder mehrerer SEO-URLs. Typischer Use-Case: is_own ändern (eigene ↔ Wettbewerber), Priorität setzen, Status ändern.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Einzelne URL-ID zum Aktualisieren',
                ],
                'url_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von URL-IDs für Bulk-Update',
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Alle URLs dieser Domain aktualisieren',
                ],
                'is_own' => [
                    'type' => 'boolean',
                    'description' => 'Eigene URL (true) oder Wettbewerber (false)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'redirected', 'deleted', 'error'],
                ],
                'priority' => [
                    'type' => 'integer',
                    'description' => 'Priorität (0-100)',
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

            $query = SeoUrl::where('team_id', $team->id);

            if (!empty($arguments['url_id'])) {
                $query->where('id', (int) $arguments['url_id']);
            } elseif (!empty($arguments['url_ids'])) {
                $query->whereIn('id', array_map('intval', $arguments['url_ids']));
            } elseif (!empty($arguments['domain'])) {
                $query->where('domain', $arguments['domain']);
            } else {
                return ToolResult::error('Mindestens url_id, url_ids oder domain angeben.', 'VALIDATION_ERROR');
            }

            $urls = $query->get();

            if ($urls->isEmpty()) {
                return ToolResult::error('Keine URLs gefunden.', 'NOT_FOUND');
            }

            $updates = [];
            if (isset($arguments['is_own'])) {
                $updates['is_own'] = (bool) $arguments['is_own'];
            }
            if (isset($arguments['status'])) {
                $updates['status'] = $arguments['status'];
            }
            if (isset($arguments['priority'])) {
                $updates['priority'] = max(0, min(100, (int) $arguments['priority']));
            }

            if (empty($updates)) {
                return ToolResult::error('Keine Änderungen angegeben. Mindestens is_own, status oder priority setzen.', 'VALIDATION_ERROR');
            }

            foreach ($urls as $url) {
                $url->update($updates);
            }

            return ToolResult::success([
                'updated' => $urls->count(),
                'url_ids' => $urls->pluck('id')->all(),
                'changes' => $updates,
                'message' => $urls->count() . ' URL(s) aktualisiert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
