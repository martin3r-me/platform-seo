<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;

class ManageUrlListEntriesTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url_list_entries.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/url-list-entries - URLs zu einer Liste hinzufügen oder entfernen. Nur Root-URLs erlaubt (keine Kind-URLs). Eine URL kann in mehreren Listen sein. action: "add" (default) oder "remove".';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list_id' => [
                    'type' => 'integer',
                    'description' => 'ID der URL-Liste',
                ],
                'url_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'Array von SeoUrl-IDs',
                ],
                'action' => [
                    'type' => 'string',
                    'enum' => ['add', 'remove'],
                    'description' => 'Aktion: "add" (Standard) oder "remove"',
                ],
            ],
            'required' => ['list_id', 'url_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $list = SeoUrlList::find((int) ($arguments['list_id'] ?? 0));

            if (!$list) {
                return ToolResult::error('Liste nicht gefunden.', 'NOT_FOUND');
            }

            $urlIds = array_map('intval', $arguments['url_ids'] ?? []);
            if (empty($urlIds)) {
                return ToolResult::error('Keine URL-IDs angegeben.', 'VALIDATION_ERROR');
            }

            $action = $arguments['action'] ?? 'add';

            if ($action === 'remove') {
                $list->urls()->detach($urlIds);

                return ToolResult::success([
                    'message' => count($urlIds) . ' URL(s) aus Liste entfernt.',
                    'list_id' => $list->id,
                    'removed_url_ids' => $urlIds,
                ]);
            }

            // Add: validate root-only constraint
            $childUrlIds = SeoUrlRelationship::where('type', 'parent_child')
                ->whereIn('target_url_id', $urlIds)
                ->pluck('target_url_id')
                ->all();

            if (!empty($childUrlIds)) {
                $rejected = SeoUrl::whereIn('id', $childUrlIds)->pluck('url')->all();

                return ToolResult::error(
                    'Folgende URLs sind Kind-URLs und können nicht direkt hinzugefügt werden: ' . implode(', ', $rejected),
                    'VALIDATION_ERROR'
                );
            }

            // Verify URLs exist
            $existing = SeoUrl::whereIn('id', $urlIds)->pluck('id')->all();
            $missing = array_diff($urlIds, $existing);

            if (!empty($missing)) {
                return ToolResult::error(
                    'URLs mit folgenden IDs nicht gefunden: ' . implode(', ', $missing),
                    'NOT_FOUND'
                );
            }

            $list->urls()->syncWithoutDetaching(
                collect($existing)->mapWithKeys(fn ($id) => [
                    $id => ['added_at' => now()],
                ])->all()
            );

            return ToolResult::success([
                'message' => count($existing) . ' URL(s) zur Liste hinzugefügt.',
                'list_id' => $list->id,
                'added_url_ids' => $existing,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
