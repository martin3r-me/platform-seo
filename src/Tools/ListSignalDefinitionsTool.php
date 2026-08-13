<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoSignalDefinition;

class ListSignalDefinitionsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.signal_definitions.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/signal-definitions - Listet die Signal-Definitionen des Teams. '
            .'Optional: pattern_type, is_active (bool). Ohne Filter wird zusätzlich der Muster-Katalog '
            .'(verfügbare pattern_type mit Default-Conditions) zurückgegeben — als Vorlage zum Erstellen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'pattern_type' => ['type' => 'string', 'description' => 'Nur Definitionen dieses Musters'],
                'is_active' => ['type' => 'boolean', 'description' => 'Nach Aktiv-Status filtern'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $query = SeoSignalDefinition::where('team_id', $team->id);
            if (! empty($arguments['pattern_type'])) {
                $query->where('pattern_type', $arguments['pattern_type']);
            }
            if (array_key_exists('is_active', $arguments) && $arguments['is_active'] !== null) {
                $query->where('is_active', (bool) $arguments['is_active']);
            }

            $defs = $query->orderBy('name')->get()->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'pattern_type' => $d->pattern_type,
                'conditions' => $d->conditions,
                'scope_type' => $d->scope_type,
                'scope_value' => $d->scope_value,
                'frequency' => $d->frequency,
                'severity' => $d->severity,
                'is_active' => $d->is_active,
            ])->all();

            $payload = ['definitions' => $defs, 'count' => count($defs)];

            // Ohne Filter: Muster-Katalog als Erstellungs-Vorlage mitliefern.
            if (empty($arguments['pattern_type']) && ! array_key_exists('is_active', $arguments)) {
                $payload['pattern_catalog'] = SeoSignalDefinition::patternCatalog();
            }

            return ToolResult::success($payload);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
