<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Services\SeoKeywordService;

class CreateClusterTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.clusters.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/clusters - Erstellt einen neuen Keyword-Cluster. Parameter: name (required). Optional: description, color (Hex, z.B. "#3B82F6").';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Cluster-Name'],
                'description' => ['type' => 'string'],
                'color' => ['type' => 'string', 'description' => 'Hex-Farbe, z.B. "#3B82F6"'],
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
                return ToolResult::error('Name ist erforderlich.', 'VALIDATION_ERROR');
            }

            $service = app(SeoKeywordService::class);
            $cluster = $service->createCluster($team->id, [
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'color' => $arguments['color'] ?? '#3B82F6',
            ], $context->user);

            return ToolResult::success([
                'id' => $cluster->id,
                'uuid' => $cluster->uuid,
                'name' => $cluster->name,
                'color' => $cluster->color,
                'message' => "Cluster '{$cluster->name}' erstellt.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
