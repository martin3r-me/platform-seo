<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrlList;

class DeleteUrlListTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url_lists.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/url-lists/{id} - Löscht eine URL-Liste. Nur die Liste und Pivot-Einträge werden gelöscht, die URLs selbst bleiben erhalten. Parameter: list_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list_id' => [
                    'type' => 'integer',
                    'description' => 'ID der zu löschenden Liste',
                ],
            ],
            'required' => ['list_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $list = SeoUrlList::find((int) ($arguments['list_id'] ?? 0));

            if (!$list) {
                return ToolResult::error('Liste nicht gefunden.', 'NOT_FOUND');
            }

            $name = $list->name;
            $list->delete();

            return ToolResult::success([
                'message' => "Liste '{$name}' gelöscht. URLs bleiben erhalten.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
