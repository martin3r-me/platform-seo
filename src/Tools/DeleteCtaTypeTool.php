<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoCtaType;

class DeleteCtaTypeTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cta_types.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/cta-types - Löscht CTA-Typen per ID (team-scoped, mehrere möglich). Parameter: cta_type_ids (array, required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cta_type_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'IDs der zu löschenden CTA-Typen.',
                ],
            ],
            'required' => ['cta_type_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }
            $ids = array_values(array_filter(array_map('intval', (array) ($arguments['cta_type_ids'] ?? []))));
            if (empty($ids)) {
                return ToolResult::error('cta_type_ids ist erforderlich (Array von IDs).', 'VALIDATION_ERROR');
            }

            $deleted = SeoCtaType::where('team_id', $team->id)->whereIn('id', $ids)->delete();

            return ToolResult::success([
                'deleted' => $deleted,
                'message' => "{$deleted} CTA-Typ(en) gelöscht.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
