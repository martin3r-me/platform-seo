<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeywordCluster;

class ListClustersTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/clusters - Listet Keyword-Cluster des Teams. Jeder Cluster hat Name, Farbe, Keyword-Count und aggregierte Metriken.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'cluster_id' => [
                    'type' => 'integer',
                    'description' => 'Detail eines Clusters mit allen Keywords',
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

            // Detail mode
            if (!empty($arguments['cluster_id'])) {
                $cluster = SeoKeywordCluster::with('keywords')
                    ->where('team_id', $team->id)
                    ->find((int) $arguments['cluster_id']);

                if (!$cluster) {
                    return ToolResult::error('Cluster nicht gefunden.', 'NOT_FOUND');
                }

                return ToolResult::success([
                    'cluster' => [
                        'id' => $cluster->id,
                        'name' => $cluster->name,
                        'description' => $cluster->description,
                        'color' => $cluster->color,
                        'keywords_count' => $cluster->keywords->count(),
                        'total_search_volume' => $cluster->keywords->sum('search_volume'),
                        'avg_difficulty' => $cluster->keywords->avg('keyword_difficulty') ? round($cluster->keywords->avg('keyword_difficulty'), 1) : null,
                    ],
                    'keywords' => $cluster->keywords->map(fn ($kw) => [
                        'id' => $kw->id,
                        'keyword' => $kw->keyword,
                        'search_volume' => $kw->search_volume,
                        'keyword_difficulty' => $kw->keyword_difficulty,
                        'search_intent' => $kw->search_intent,
                    ])->all(),
                ]);
            }

            $clusters = SeoKeywordCluster::withCount('keywords')
                ->where('team_id', $team->id)
                ->orderBy('order')
                ->orderBy('name')
                ->get();

            // Aggregate metrics per cluster
            $result = $clusters->map(function (SeoKeywordCluster $c) {
                $keywords = $c->keywords()->get();

                return [
                    'id' => $c->id,
                    'uuid' => $c->uuid,
                    'name' => $c->name,
                    'description' => $c->description,
                    'color' => $c->color,
                    'order' => $c->order,
                    'keywords_count' => $c->keywords_count,
                    'total_search_volume' => $keywords->sum('search_volume'),
                    'avg_difficulty' => $keywords->avg('keyword_difficulty') ? round($keywords->avg('keyword_difficulty'), 1) : null,
                ];
            })->all();

            return ToolResult::success([
                'clusters' => $result,
                'total' => count($result),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
