<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Services\SeoClusteringService;

class AutoClusterTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.auto.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/clusters/auto - Automatisches SERP-basiertes Keyword-Clustering. Gruppiert Keywords anhand überlappender SERP-Ergebnisse. Optional: min_overlap (Standard: 3, min. gemeinsame URLs für Cluster-Zuordnung). Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'min_overlap' => [
                    'type' => 'integer',
                    'description' => 'Minimale SERP-Überlappung für Cluster-Zuordnung (Standard: 3)',
                ],
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

            $settings = SeoTeamSettings::where('team_id', $team->id)->first();
            if (!$settings) {
                return ToolResult::error('Keine SEO-Einstellungen für dieses Team konfiguriert.', 'NOT_CONFIGURED');
            }

            $service = app(SeoClusteringService::class);
            $minOverlap = (int) ($arguments['min_overlap'] ?? 3);

            $result = $service->autoCluster($settings, $context->user, $minOverlap);

            return ToolResult::success([
                'result' => $result,
                'message' => 'Auto-Clustering abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
