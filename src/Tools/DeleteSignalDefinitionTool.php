<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignalDefinition;

class DeleteSignalDefinitionTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signal_definitions.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/signal-definitions/{id} - Löscht eine Signal-Definition (Soft-Delete). '
            .'Parameter: id (required). Bereits gefeuerte Signale bleiben erhalten. '
            .'Zum bloßen Pausieren besser seo.signal_definitions.PUT mit is_active=false nutzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Signal-Definition'],
            ],
            'required' => ['id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $def = SeoSignalDefinition::where('team_id', $team->id)->find($arguments['id'] ?? 0);
            if (! $def) {
                return ToolResult::error('Signal-Definition nicht gefunden.', 'NOT_FOUND');
            }

            $name = $def->name;
            $def->delete();

            return ToolResult::success([
                'message' => "Signal-Definition '{$name}' gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
