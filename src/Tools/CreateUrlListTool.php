<?php

namespace Platform\Seo\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrlList;

class CreateUrlListTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url_lists.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/url-lists - Erstellt eine neue URL-Liste (teamübergreifend). Parameter: name (required), description (optional). Slug wird automatisch generiert.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => 'string',
                    'description' => 'Name der Liste (z.B. "BHG.GROUP")',
                ],
                'description' => [
                    'type' => 'string',
                    'description' => 'Optionale Beschreibung der Liste',
                ],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            if (empty($arguments['name'])) {
                return ToolResult::error('Name ist erforderlich.', 'VALIDATION_ERROR');
            }

            $slug = Str::slug($arguments['name']);

            if (SeoUrlList::where('slug', $slug)->exists()) {
                return ToolResult::error("Eine Liste mit dem Slug '{$slug}' existiert bereits.", 'DUPLICATE');
            }

            $list = SeoUrlList::create([
                'name' => $arguments['name'],
                'slug' => $slug,
                'description' => $arguments['description'] ?? null,
                'created_by' => $context->user->id,
            ]);

            return ToolResult::success([
                'id' => $list->id,
                'uuid' => $list->uuid,
                'name' => $list->name,
                'slug' => $list->slug,
                'description' => $list->description,
                'message' => "Liste '{$list->name}' erfolgreich erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
