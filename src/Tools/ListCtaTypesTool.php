<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoCtaType;

class ListCtaTypesTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cta_types.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/cta-types - Listet die CTA-Typen des Teams (kuratierte CTA-Mechaniken: anruf, kontakt, angebot …). Optional: active_only.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'active_only' => ['type' => 'boolean', 'description' => 'Nur aktive Typen.'],
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

            $query = SeoCtaType::where('team_id', $team->id);
            if (! empty($arguments['active_only'])) {
                $query->where('active', true);
            }

            $types = $query->orderBy('sort')->orderBy('label')->get()
                ->map(fn (SeoCtaType $t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'label' => $t->label,
                    'mechanism' => $t->mechanism,
                    'sort' => $t->sort,
                    'active' => $t->active,
                ])->all();

            return ToolResult::success(['cta_types' => $types, 'count' => count($types)]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
