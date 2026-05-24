<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

class CreateKeywordTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.keywords.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/keywords - Fügt ein oder mehrere Keywords hinzu. Parameter: keywords (Array von Objekten mit "keyword" (required) und optional: search_intent, topic, cluster_id, target_url, priority, notes).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'keywords' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string', 'description' => 'Das Keyword'],
                            'search_intent' => [
                                'type' => 'string',
                                'enum' => ['informational', 'navigational', 'commercial', 'transactional'],
                            ],
                            'topic' => ['type' => 'string'],
                            'cluster_id' => ['type' => 'integer'],
                            'target_url' => ['type' => 'string'],
                            'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                            'notes' => ['type' => 'string'],
                        ],
                        'required' => ['keyword'],
                    ],
                    'description' => 'Array von Keywords',
                ],
            ],
            'required' => ['keywords'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $keywordsData = $arguments['keywords'] ?? [];
            if (empty($keywordsData)) {
                return ToolResult::error('Keine Keywords angegeben.', 'VALIDATION_ERROR');
            }

            $service = app(SeoKeywordService::class);
            $created = $service->addKeywords($team->id, $keywordsData, $context->user);

            return ToolResult::success([
                'keywords' => $created->map(fn ($kw) => [
                    'id' => $kw->id,
                    'uuid' => $kw->uuid,
                    'keyword' => $kw->keyword,
                    'search_intent' => $kw->search_intent,
                    'cluster_id' => $kw->cluster_id,
                ])->all(),
                'count' => $created->count(),
                'message' => $created->count() . ' Keyword(s) hinzugefügt.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
