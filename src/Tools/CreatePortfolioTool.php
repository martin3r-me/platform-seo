<?php

namespace Platform\Seo\Tools;

use Illuminate\Support\Str;
use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoPortfolio;

/**
 * Legt einen Wirkungsraum an — den Steuer-Scope (kontrollierte URLs + Ziel),
 * im Gegensatz zur Liste (Beobachtung). Optional verschachtelt (parent_id →
 * Verbund). Siehe docs/WIRKUNGSRAUM-CONCEPT.md.
 */
class CreatePortfolioTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.portfolios.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/portfolios - Legt einen Wirkungsraum an (Steuer-Scope: kontrollierte URLs + Ziel, '
            . 'im Gegensatz zur Liste = Beobachtung). Parameter: name (required). Optional: goal (das Ziel — welche '
            . 'Themen der Verbund dominieren soll), description, parent_id (übergeordneter Wirkungsraum = Verbund/'
            . 'Gruppierung). URLs hängt man danach via seo.portfolio_urls.POST an.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'goal' => ['type' => ['string', 'null'], 'description' => 'Das Ziel: welche Themen dominiert werden sollen'],
                'description' => ['type' => ['string', 'null']],
                'parent_id' => ['type' => ['integer', 'null'], 'description' => 'Übergeordneter Wirkungsraum (Verbund)'],
            ],
            'required' => ['name'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }
            if (empty($arguments['name'])) {
                return ToolResult::error('name ist erforderlich.', 'VALIDATION_ERROR');
            }

            $parentId = $arguments['parent_id'] ?? null;
            if ($parentId) {
                $parent = SeoPortfolio::where('team_id', $team->id)->find((int) $parentId);
                if (!$parent) {
                    return ToolResult::error('Übergeordneter Wirkungsraum nicht gefunden.', 'NOT_FOUND');
                }
            }

            $base = Str::slug($arguments['name']) ?: 'portfolio';
            $slug = $base;
            $i = 1;
            while (SeoPortfolio::where('team_id', $team->id)->where('slug', $slug)->exists()) {
                $slug = $base . '-' . (++$i);
            }

            $wr = SeoPortfolio::create([
                'team_id' => $team->id,
                'user_id' => $context->user?->id,
                'name' => $arguments['name'],
                'slug' => $slug,
                'description' => $arguments['description'] ?? null,
                'goal' => $arguments['goal'] ?? null,
                'parent_id' => $parentId ? (int) $parentId : null,
            ]);

            return ToolResult::success([
                'id' => $wr->id,
                'uuid' => $wr->uuid,
                'name' => $wr->name,
                'slug' => $wr->slug,
                'goal' => $wr->goal,
                'parent_id' => $wr->parent_id,
                'message' => "Wirkungsraum '{$wr->name}' angelegt (Steuer-Scope). URLs jetzt via seo.portfolio_urls.POST anhängen.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
