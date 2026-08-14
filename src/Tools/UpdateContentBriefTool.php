<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoContentBriefNote;
use Platform\Seo\Models\SeoContentBriefSection;

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
                'sections' => [
                    'type' => 'array',
                    'description' => 'Ersetzt ALLE Abschnitte (gleiche Struktur wie beim POST: {heading, heading_level?, description?, target_keywords?[], notes?}). Weglassen = unverändert; leeres Array = alle entfernen.',
                    'items' => ['type' => 'object'],
                ],
                'notes' => [
                    'type' => 'array',
                    'description' => 'Ersetzt ALLE Notizen ({note_type, content}). Weglassen = unverändert; leeres Array = alle entfernen.',
                    'items' => ['type' => 'object'],
                ],
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

            // Abschnitte ersetzen (nur wenn der Key übergeben wurde).
            $sectionCount = null;
            if (array_key_exists('sections', $arguments) && is_array($arguments['sections'])) {
                SeoContentBriefSection::where('content_brief_id', $brief->id)->delete();
                $sectionCount = 0;
                foreach ($arguments['sections'] as $i => $section) {
                    $heading = is_array($section) ? ($section['heading'] ?? '') : (string) $section;
                    if ($heading === '') {
                        continue;
                    }
                    SeoContentBriefSection::create([
                        'content_brief_id' => $brief->id,
                        'team_id' => $brief->team_id,
                        'user_id' => $context->user?->id,
                        'order' => $i,
                        'heading' => $heading,
                        'heading_level' => $section['heading_level'] ?? 'h2',
                        'description' => $section['description'] ?? null,
                        'target_keywords' => $section['target_keywords'] ?? null,
                        'notes' => $section['notes'] ?? null,
                    ]);
                    $sectionCount++;
                }
            }

            // Notizen ersetzen (nur wenn der Key übergeben wurde).
            $noteCount = null;
            if (array_key_exists('notes', $arguments) && is_array($arguments['notes'])) {
                SeoContentBriefNote::where('content_brief_id', $brief->id)->delete();
                $noteCount = 0;
                foreach ($arguments['notes'] as $i => $note) {
                    $content = is_array($note) ? ($note['content'] ?? '') : (string) $note;
                    if ($content === '') {
                        continue;
                    }
                    SeoContentBriefNote::create([
                        'content_brief_id' => $brief->id,
                        'team_id' => $brief->team_id,
                        'user_id' => $context->user?->id,
                        'note_type' => is_array($note) ? ($note['note_type'] ?? 'instruction') : 'instruction',
                        'content' => $content,
                        'order' => $i,
                    ]);
                    $noteCount++;
                }
            }

            return ToolResult::success(array_filter([
                'id' => $brief->id,
                'uuid' => $brief->uuid,
                'name' => $brief->name,
                'status' => $brief->status,
                'external_task_ref' => $brief->external_task_ref,
                'external_document_ref' => $brief->external_document_ref,
                'published_url' => $brief->published_url,
                'sections' => $sectionCount,
                'notes' => $noteCount,
                'message' => "Content-Brief '{$brief->name}' aktualisiert.",
            ], fn ($v) => $v !== null));
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
