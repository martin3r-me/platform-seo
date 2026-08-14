<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoContentBriefReconciler;

/**
 * Schließt den SEO ↔ Flynk-Loop: prüft die (erwarteten) Seiten offener Briefs auf
 * den x-content-brief-Marker und schaltet passende Briefs auf "published"
 * (registriert die Live-Seite als getrackte eigene URL).
 */
class ReconcileContentBriefsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.content_briefs.reconcile.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/content-briefs/reconcile - Gleicht offene Content-Briefs mit ihren Live-Seiten ab: '
            . 'liest den <meta name="x-content-brief">-Marker (leichter HTTP-Fetch, kein API-Cost) und schaltet '
            . 'gematchte Briefs auf "published" + registriert die Seite als getrackte eigene URL. Optional: dry_run.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'dry_run' => ['type' => 'boolean', 'description' => 'Nur prüfen, nichts schreiben.'],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $dryRun = (bool) ($arguments['dry_run'] ?? false);
            $result = app(SeoContentBriefReconciler::class)->reconcileTeam($team->id, $dryRun);

            $verb = $dryRun ? 'würden veröffentlicht' : 'veröffentlicht';

            return ToolResult::success(array_merge($result, [
                'dry_run' => $dryRun,
                'message' => "{$result['checked']} Seiten geprüft, {$result['published']} Briefs {$verb}, "
                    . "{$result['pending']} weiter offen.",
            ]));
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
