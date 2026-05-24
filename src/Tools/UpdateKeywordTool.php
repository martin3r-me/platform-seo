<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Services\SeoKeywordService;

class UpdateKeywordTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/keywords/{id} - Aktualisiert ein Keyword. Parameter: keyword_id (required). Optional: search_intent, topic, cluster_id (null zum Entfernen), target_url, priority, notes, content_status.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keyword_id' => ['type' => 'integer', 'description' => 'ID des Keywords'],
                'search_intent' => [
                    'type' => 'string',
                    'enum' => ['informational', 'navigational', 'commercial', 'transactional'],
                ],
                'topic' => ['type' => ['string', 'null']],
                'cluster_id' => ['type' => ['integer', 'null'], 'description' => 'Cluster-ID (null zum Entfernen)'],
                'target_url' => ['type' => ['string', 'null']],
                'priority' => ['type' => ['string', 'null'], 'enum' => ['low', 'medium', 'high', null]],
                'notes' => ['type' => ['string', 'null']],
                'content_status' => ['type' => ['string', 'null']],
            ],
            'required' => ['keyword_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $keyword = SeoKeyword::where('team_id', $team->id)
                ->find((int) ($arguments['keyword_id'] ?? 0));

            if (!$keyword) {
                return ToolResult::error('Keyword nicht gefunden.', 'NOT_FOUND');
            }

            $data = array_filter([
                'search_intent' => $arguments['search_intent'] ?? null,
                'topic' => $arguments['topic'] ?? null,
                'target_url' => $arguments['target_url'] ?? null,
                'priority' => $arguments['priority'] ?? null,
                'notes' => $arguments['notes'] ?? null,
                'content_status' => $arguments['content_status'] ?? null,
            ], fn ($v) => $v !== null);

            // Handle cluster_id separately (can be explicitly set to null)
            if (array_key_exists('cluster_id', $arguments)) {
                $service = app(SeoKeywordService::class);
                $service->moveToCluster($keyword, $arguments['cluster_id']);
            }

            if (!empty($data)) {
                $service = $service ?? app(SeoKeywordService::class);
                $keyword = $service->updateKeyword($keyword, $data);
            }

            return ToolResult::success([
                'id' => $keyword->id,
                'keyword' => $keyword->keyword,
                'search_intent' => $keyword->search_intent,
                'topic' => $keyword->topic,
                'cluster_id' => $keyword->cluster_id,
                'priority' => $keyword->priority,
                'message' => "Keyword '{$keyword->keyword}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
