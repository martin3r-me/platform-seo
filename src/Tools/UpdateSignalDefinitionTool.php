<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignalDefinition;

class UpdateSignalDefinitionTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signal_definitions.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/signal-definitions/{id} - Aktualisiert eine Signal-Definition. '
            .'Parameter: id (required). Optional: name, conditions (wird gemerged), scope_type, scope_value, '
            .'frequency, severity, is_active (bool, zum Aktivieren/Pausieren), description.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'ID der Signal-Definition'],
                'name' => ['type' => 'string'],
                'conditions' => ['type' => 'object', 'description' => 'Wird in bestehende Conditions gemerged'],
                'scope_type' => ['type' => 'string', 'enum' => SeoSignalDefinition::SCOPE_TYPES],
                'scope_value' => ['type' => 'object'],
                'frequency' => ['type' => 'string', 'enum' => SeoSignalDefinition::FREQUENCIES],
                'severity' => ['type' => 'string', 'enum' => SeoSignalDefinition::SEVERITIES],
                'enrich_with_ai' => ['type' => 'boolean', 'description' => 'KI-Anreicherung an-/ausschalten'],
                'is_active' => ['type' => 'boolean'],
                'description' => ['type' => 'string'],
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

            foreach (['name', 'description', 'scope_type', 'scope_value', 'frequency', 'severity'] as $field) {
                if (array_key_exists($field, $arguments)) {
                    $def->{$field} = $arguments[$field];
                }
            }
            if (array_key_exists('is_active', $arguments)) {
                $def->is_active = (bool) $arguments['is_active'];
            }
            if (array_key_exists('enrich_with_ai', $arguments)) {
                $def->enrich_with_ai = (bool) $arguments['enrich_with_ai'];
            }
            if (! empty($arguments['conditions']) && is_array($arguments['conditions'])) {
                $def->conditions = array_merge($def->conditions ?? [], $arguments['conditions']);
            }

            $def->save();

            return ToolResult::success([
                'id' => $def->id,
                'name' => $def->name,
                'pattern_type' => $def->pattern_type,
                'conditions' => $def->conditions,
                'is_active' => $def->is_active,
                'message' => "Signal-Definition '{$def->name}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
