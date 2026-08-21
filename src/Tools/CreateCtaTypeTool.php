<?php

namespace Platform\Seo\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoCtaType;

class CreateCtaTypeTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.cta_types.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/cta-types - Legt einen CTA-Typ an. Parameter: label (required). Optional: code (sonst aus label), mechanism (tel/form/link/email), sort, active.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'label' => ['type' => 'string', 'description' => 'Anzeigename, z. B. „Angebot anfordern".'],
                'code' => ['type' => 'string', 'description' => 'Schlüssel (kebab/snake); sonst aus label abgeleitet.'],
                'mechanism' => ['type' => 'string', 'enum' => SeoCtaType::MECHANISMS, 'description' => 'tel | form | link | email.'],
                'sort' => ['type' => 'integer'],
                'active' => ['type' => 'boolean'],
            ],
            'required' => ['label'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }
            $label = trim((string) ($arguments['label'] ?? ''));
            if ($label === '') {
                return ToolResult::error('label ist erforderlich.', 'VALIDATION_ERROR');
            }
            $code = Str::slug(trim((string) ($arguments['code'] ?? $label)), '_');
            if ($code === '') {
                return ToolResult::error('Konnte keinen gültigen code ableiten.', 'VALIDATION_ERROR');
            }
            $mechanism = $arguments['mechanism'] ?? 'link';
            if (! in_array($mechanism, SeoCtaType::MECHANISMS, true)) {
                return ToolResult::error('mechanism muss eines sein: '.implode(', ', SeoCtaType::MECHANISMS), 'VALIDATION_ERROR');
            }
            if (SeoCtaType::where('team_id', $team->id)->where('code', $code)->exists()) {
                return ToolResult::error("CTA-Typ '{$code}' existiert bereits.", 'CONFLICT');
            }

            $type = SeoCtaType::create([
                'team_id' => $team->id,
                'code' => $code,
                'label' => $label,
                'mechanism' => $mechanism,
                'sort' => (int) ($arguments['sort'] ?? 0),
                'active' => $arguments['active'] ?? true,
            ]);

            return ToolResult::success([
                'id' => $type->id,
                'code' => $type->code,
                'label' => $type->label,
                'mechanism' => $type->mechanism,
                'message' => "CTA-Typ '{$type->label}' angelegt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
