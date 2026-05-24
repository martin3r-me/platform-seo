<?php

namespace Platform\Seo\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrlList;

class UpdateUrlListTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url_lists.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/url-lists/{id} - Aktualisiert eine URL-Liste. Parameter: list_id (required). Optional: name, description. Slug wird bei Namensänderung automatisch aktualisiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'list_id' => [
                    'type' => 'integer',
                    'description' => 'ID der Liste',
                ],
                'name' => [
                    'type' => 'string',
                    'description' => 'Neuer Name',
                ],
                'description' => [
                    'type' => ['string', 'null'],
                    'description' => 'Neue Beschreibung (null zum Entfernen)',
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

            $data = [];

            if (isset($arguments['name'])) {
                $data['name'] = $arguments['name'];
                $data['slug'] = Str::slug($arguments['name']);

                $duplicate = SeoUrlList::where('slug', $data['slug'])->where('id', '!=', $list->id)->exists();
                if ($duplicate) {
                    return ToolResult::error("Slug '{$data['slug']}' ist bereits vergeben.", 'DUPLICATE');
                }
            }

            if (array_key_exists('description', $arguments)) {
                $data['description'] = $arguments['description'];
            }

            if (!empty($data)) {
                $list->update($data);
            }

            return ToolResult::success([
                'id' => $list->id,
                'uuid' => $list->uuid,
                'name' => $list->name,
                'slug' => $list->slug,
                'description' => $list->description,
                'message' => "Liste '{$list->name}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
