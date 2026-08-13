<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignalDefinition;

class CreateSignalDefinitionTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signal_definitions.POST';
    }

    public function getDescription(): string
    {
        $patterns = implode(', ', SeoSignalDefinition::patternTypes());

        return "POST /seo/signal-definitions - Erstellt eine Signal-Definition (deklariert, wann ein SEO-Signal entsteht). "
            ."Parameter: name (required), pattern_type (required, eines von: {$patterns}), conditions (optional, tunbare Parameter des Musters), "
            ."scope_type (all|entity|entity_subtree|list, default all), scope_value (z.B. {entity_id} oder {list_id}), "
            ."frequency (every_snapshot|daily|weekly, default daily), severity (info|watch|warning|critical, default warning), description. "
            ."Nutze seo.signal_definitions.GET ohne Filter, um den Muster-Katalog mit Default-Conditions zu sehen.";
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Sprechender Name (z.B. "Griffweite Caterer-Keywords")'],
                'pattern_type' => ['type' => 'string', 'enum' => SeoSignalDefinition::patternTypes(), 'description' => 'Domänennatives Muster'],
                'conditions' => ['type' => 'object', 'description' => 'Tunbare Parameter (überschreibt Muster-Defaults), z.B. {"min_volume": 200}'],
                'scope_type' => ['type' => 'string', 'enum' => SeoSignalDefinition::SCOPE_TYPES, 'description' => 'Geltungsbereich'],
                'scope_value' => ['type' => 'object', 'description' => 'z.B. {"entity_id": 14} oder {"list_id": 5}'],
                'frequency' => ['type' => 'string', 'enum' => SeoSignalDefinition::FREQUENCIES],
                'severity' => ['type' => 'string', 'enum' => SeoSignalDefinition::SEVERITIES],
                'description' => ['type' => 'string'],
            ],
            'required' => ['name', 'pattern_type'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $name = trim($arguments['name'] ?? '');
            $pattern = $arguments['pattern_type'] ?? '';
            if ($name === '') {
                return ToolResult::error('Name ist erforderlich.', 'VALIDATION_ERROR');
            }

            $catalog = SeoSignalDefinition::patternCatalog();
            if (! isset($catalog[$pattern])) {
                return ToolResult::error("Unbekanntes pattern_type '{$pattern}'. Erlaubt: ".implode(', ', array_keys($catalog)), 'VALIDATION_ERROR');
            }

            $scopeType = $arguments['scope_type'] ?? 'all';
            if (! in_array($scopeType, SeoSignalDefinition::SCOPE_TYPES, true)) {
                return ToolResult::error('Ungültiger scope_type.', 'VALIDATION_ERROR');
            }

            // Muster-Defaults mit übergebenen Conditions überlagern.
            $conditions = array_merge($catalog[$pattern]['conditions'], $arguments['conditions'] ?? []);

            $def = SeoSignalDefinition::create([
                'team_id' => $team->id,
                'created_by' => $context->user?->id,
                'name' => $name,
                'description' => $arguments['description'] ?? null,
                'pattern_type' => $pattern,
                'conditions' => $conditions,
                'scope_type' => $scopeType,
                'scope_value' => $arguments['scope_value'] ?? null,
                'frequency' => $arguments['frequency'] ?? 'daily',
                'severity' => $arguments['severity'] ?? 'warning',
                'is_active' => true,
            ]);

            return ToolResult::success([
                'id' => $def->id,
                'uuid' => $def->uuid,
                'name' => $def->name,
                'pattern_type' => $def->pattern_type,
                'conditions' => $def->conditions,
                'message' => "Signal-Definition '{$def->name}' ({$def->pattern_type}) erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
