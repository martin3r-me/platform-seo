<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoWirkungsraum;

/**
 * Listet die Wirkungsräume des Teams (Steuer-Scopes), inkl. URL-Zahl,
 * Ziel und Verschachtelung.
 */
class ListWirkungsraeumeTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.wirkungsraeume.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/wirkungsraeume - Listet die Wirkungsräume des Teams (Steuer-Scopes: kontrollierte URLs + Ziel). '
            . 'Optional: parent_id (nur Kinder eines Verbunds).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'parent_id' => ['type' => ['integer', 'null']],
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

            $query = SeoWirkungsraum::where('team_id', $team->id)
                ->withCount('urls', 'children')
                ->orderBy('name');

            if (array_key_exists('parent_id', $arguments)) {
                $query->where('parent_id', $arguments['parent_id']);
            }

            $items = $query->get()->map(fn ($wr) => [
                'id' => $wr->id,
                'name' => $wr->name,
                'slug' => $wr->slug,
                'goal' => $wr->goal,
                'parent_id' => $wr->parent_id,
                'urls_count' => $wr->urls_count,
                'children_count' => $wr->children_count,
            ])->all();

            return ToolResult::success([
                'wirkungsraeume' => $items,
                'total' => count($items),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
