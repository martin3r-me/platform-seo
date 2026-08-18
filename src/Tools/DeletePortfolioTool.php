<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoPortfolio;

/**
 * Löscht einen Wirkungsraum (Steuer-Scope). Die kontrollierten URLs bleiben
 * erhalten — nur die Zugehörigkeit (Pivot) wird gelöst; Unter-Räume werden
 * entkoppelt (parent_id=null). Genutzt u. a., um einen fälschlich als
 * Wirkungsraum geführten Register (z. B. Syltjunkie) sauber aus den
 * Steuer-Scopes zu nehmen.
 */
class DeletePortfolioTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.portfolios.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/portfolios/{id} - Löscht einen Wirkungsraum. Die kontrollierten URLs bleiben '
            . 'erhalten (nur die Zugehörigkeit wird gelöst); Unter-Räume werden entkoppelt. Parameter: portfolio_id (required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'portfolio_id' => [
                    'type' => 'integer',
                    'description' => 'ID des zu löschenden Wirkungsraums',
                ],
            ],
            'required' => ['portfolio_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $wr = SeoPortfolio::where('team_id', $team->id)->find((int) ($arguments['portfolio_id'] ?? 0));
            if (! $wr) {
                return ToolResult::error('Wirkungsraum nicht gefunden.', 'NOT_FOUND');
            }

            $name = $wr->name;
            $wr->urls()->detach();
            SeoPortfolio::where('parent_id', $wr->id)->update(['parent_id' => null]);
            $wr->delete();

            return ToolResult::success([
                'message' => "Wirkungsraum '{$name}' gelöscht. URLs bleiben erhalten.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
