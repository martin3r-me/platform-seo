<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoClusteringService;
use Platform\Seo\Services\SeoOrganizationLinker;

class AutoClusterTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.auto.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/clusters/auto - Automatisches SERP-basiertes Keyword-Clustering. Gruppiert Keywords anhand überlappender SERP-Ergebnisse. Optional: url_id (nur Keywords dieser eigenen URL + Kinder clustern = kunden-scoped; neue Cluster hängen am Kunden-Knoten), min_overlap (Standard: 3). Ohne url_id werden ALLE noch nicht geclusterten Team-Keywords verarbeitet. Verbraucht API-Budget!';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Nur Keywords dieser eigenen URL (inkl. Kind-URLs) clustern. Empfohlen — hält Cluster pro Kunde und begrenzt die Kosten.',
                ],
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

            // Optionales Kunden-Scope: nur Keywords einer eigenen URL + ihrer Kinder.
            $urlIds = null;
            $entityId = null;
            if (! empty($arguments['url_id'])) {
                $rootId = (int) $arguments['url_id'];
                $childIds = SeoUrlRelationship::where('source_url_id', $rootId)
                    ->where('type', 'parent_child')
                    ->pluck('target_url_id')->all();
                $urlIds = SeoUrl::whereIn('id', array_merge([$rootId], $childIds))
                    ->where('team_id', $team->id)
                    ->where('is_own', true)
                    ->pluck('id')->all();

                if (empty($urlIds)) {
                    return ToolResult::error('URL nicht gefunden oder kein eigenes Asset.', 'NOT_FOUND');
                }

                $nodes = app(SeoOrganizationLinker::class)->nodeIdsFor(SeoOrganizationLinker::ALIAS_URL, $rootId);
                $entityId = $nodes[0] ?? null;
            }

            $result = $service->autoCluster($settings, $context->user, $minOverlap, $urlIds, $entityId);

            return ToolResult::success([
                'result' => $result,
                'message' => 'Auto-Clustering abgeschlossen.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
