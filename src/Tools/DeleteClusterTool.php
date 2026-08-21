<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoKeywordCluster;

/**
 * DELETE /seo/clusters — löscht Cluster per ID (team-scoped). Keywords werden
 * nur ABGEHÄNGT (cluster_id = null, zurück in den ungeordneten Pool), nie
 * gelöscht. Für gezieltes Aufräumen eines Wirkungsraums.
 */
class DeleteClusterTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.DELETE';
    }

    public function getDescription(): string
    {
        return 'DELETE /seo/clusters - Löscht Cluster per ID (team-scoped, mehrere möglich). Keywords werden abgehängt (zurück in den Pool), NICHT gelöscht. Parameter: cluster_ids (array, required).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cluster_ids' => [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                    'description' => 'IDs der zu löschenden Cluster.',
                ],
            ],
            'required' => ['cluster_ids'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $ids = array_values(array_filter(array_map('intval', (array) ($arguments['cluster_ids'] ?? []))));
            if (empty($ids)) {
                return ToolResult::error('cluster_ids ist erforderlich (Array von IDs).', 'VALIDATION_ERROR');
            }

            $clusters = SeoKeywordCluster::where('team_id', $team->id)->whereIn('id', $ids)->get();
            if ($clusters->isEmpty()) {
                return ToolResult::error('Keine passenden Cluster im Team gefunden.', 'NOT_FOUND');
            }

            $report = [];
            foreach ($clusters as $cluster) {
                $detached = SeoKeyword::where('cluster_id', $cluster->id)->update(['cluster_id' => null]);
                $cluster->snapshots()->delete();
                $report[] = [
                    'id' => $cluster->id,
                    'name' => $cluster->name,
                    'keywords_detached' => $detached,
                ];
                $cluster->delete();
            }

            return ToolResult::success([
                'deleted' => count($report),
                'clusters' => $report,
                'message' => count($report).' Cluster gelöscht · Keywords in den Pool zurück (nicht gelöscht).',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: '.$e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
