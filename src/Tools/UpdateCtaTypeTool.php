<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoCtaType;

class UpdateCtaTypeTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cta_types.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/cta-types/{id} - Aktualisiert einen CTA-Typ (team-scoped). Parameter: cta_type_id (required). Optional: label, mechanism (tel/form/link/email), sort, active.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cta_type_id' => ['type' => 'integer', 'description' => 'ID des CTA-Typs.'],
                'label' => ['type' => 'string'],
                'mechanism' => ['type' => 'string', 'enum' => SeoCtaType::MECHANISMS],
                'sort' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
            'required' => ['cta_type_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }
            $type = SeoCtaType::where('team_id', $team->id)->find((int) ($arguments['cta_type_id'] ?? 0));
            if (! $type) {
                return ToolResult::error('CTA-Typ nicht gefunden.', 'NOT_FOUND');
            }

            $attrs = [];
            if (isset($arguments['label']) && trim((string) $arguments['label']) !== '') {
                $attrs['label'] = trim((string) $arguments['label']);
            }
            if (isset($arguments['mechanism'])) {
                if (! in_array($arguments['mechanism'], SeoCtaType::MECHANISMS, true)) {
                    return ToolResult::error('mechanism muss eines sein: '.implode(', ', SeoCtaType::MECHANISMS), 'VALIDATION_ERROR');
                }
                $attrs['mechanism'] = $arguments['mechanism'];
            }
            if (array_key_exists('sort', $arguments)) {
                $attrs['sort'] = (int) $arguments['sort'];
            }
            if (array_key_exists('active', $arguments)) {
                $attrs['active'] = (bool) $arguments['active'];
            }

            if (! empty($attrs)) {
                $type->update($attrs);
            }

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'mechanism' => $type->mechanism,
                'active' => $type->active,
                'message' => "CTA-Typ '{$type->label}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
