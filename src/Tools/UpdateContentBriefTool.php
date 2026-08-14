<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoContentBrief;

/**
 * Aktualisiert einen Content-Brief — Status-Workflow und die Flynk-Referenzen
 * (Vorwärts-Link, docs/CONTENT-BRIEF-TRACKING.md). So trägt der Connector bei
 * der Übergabe an Flynk task/document/project-IDs zurück und schaltet den Status.
 */
class UpdateContentBriefTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.content_briefs.PUT';
    }

    public function getDescription(): string
    {
        return 'PUT /seo/content-briefs/{id} - Aktualisiert einen Content-Brief. Parameter: brief_id (required). '
            . 'Optional: name, description, content_type, search_intent, status (briefed/queued/in_production/published), '
            . 'target_url, target_slug, target_word_count, external_project_ref, external_task_ref, external_document_ref, '
            . 'published_url. Setzt beim Status "published" published_at automatisch. Für die Flynk-Übergabe: '
            . 'external_task_ref/-document_ref/-project_ref speichern und status auf "queued" setzen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'brief_id' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'description' => ['type' => ['string', 'null']],
                'content_type' => ['type' => 'string'],
                'search_intent' => ['type' => 'string'],
                'status' => ['type' => 'string', 'description' => 'briefed/queued/in_production/published'],
                'target_url' => ['type' => ['string', 'null']],
                'target_slug' => ['type' => ['string', 'null']],
                'target_word_count' => ['type' => ['integer', 'null']],
                'external_project_ref' => ['type' => ['string', 'null'], 'description' => 'Flynk-Projekt-ID'],
                'external_task_ref' => ['type' => ['string', 'null'], 'description' => 'Flynk-Aufgaben-ID'],
                'external_document_ref' => ['type' => ['string', 'null'], 'description' => 'Flynk-Dokument-ID'],
                'published_url' => ['type' => ['string', 'null']],
            ],
            'required' => ['brief_id'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (!$team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $brief = SeoContentBrief::where('team_id', $team->id)
                ->find((int) ($arguments['brief_id'] ?? 0));

            if (!$brief) {
                return ToolResult::error('Brief nicht gefunden.', 'NOT_FOUND');
            }

            if (isset($arguments['status']) && !in_array($arguments['status'], SeoContentBrief::STATUSES, true)) {
                return ToolResult::error(
                    'Ungültiger Status. Erlaubt: ' . implode(', ', SeoContentBrief::STATUSES),
                    'VALIDATION_ERROR',
                );
            }

            $fields = [
                'name', 'description', 'content_type', 'search_intent', 'status',
                'target_url', 'target_slug', 'target_word_count',
                'external_project_ref', 'external_task_ref', 'external_document_ref', 'published_url',
            ];

            $data = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $arguments)) {
                    $data[$field] = $arguments[$field];
                }
            }

            // published_at automatisch mit dem Status setzen/löschen.
            if (isset($data['status'])) {
                if ($data['status'] === SeoContentBrief::STATUS_PUBLISHED && !$brief->published_at) {
                    $data['published_at'] = now();
                } elseif ($data['status'] !== SeoContentBrief::STATUS_PUBLISHED) {
                    $data['published_at'] = null;
                }
            }

            $brief->update($data);

            return ToolResult::success([
                'id' => $brief->id,
                'uuid' => $brief->uuid,
                'name' => $brief->name,
                'status' => $brief->status,
                'external_task_ref' => $brief->external_task_ref,
                'external_document_ref' => $brief->external_document_ref,
                'published_url' => $brief->published_url,
                'message' => "Content-Brief '{$brief->name}' aktualisiert.",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
