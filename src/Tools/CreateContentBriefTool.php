<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoContentBrief;
use Platform\Seo\Models\SeoContentBriefNote;
use Platform\Seo\Models\SeoContentBriefSection;
use Platform\Seo\Models\SeoKeywordCluster;

/**
 * Erstellt einen Content-Brief direkt (Cluster → Brief), ohne den Signal-Weg.
 *
 * Der Signal-Dispatcher schreibt Briefs aus KI-Outlines; dieses Tool erlaubt die
 * kuratierte/manuelle Erstellung aus einem Cluster mit Sections, Notes und
 * Cluster-Verknüpfung — die zentrale Werkbank-Aktion "aus dem Cluster einen
 * Arbeitsauftrag machen".
 */
class CreateContentBriefTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.content_briefs.POST';
    }

    public function getDescription(): string
    {
        return 'POST /seo/content-briefs - Erstellt einen Content-Brief (Produktions-Plan für ein Stück Content). '
            . 'Parameter: name (required). Optional: description, content_type (guide/pillar/article/landing, Standard guide), '
            . 'search_intent (informational/commercial/transactional/navigational), status (Standard "briefed"), '
            . 'target_url, target_slug, target_word_count, cluster_id (+ cluster_role, Standard "primary"), '
            . 'sections (Array von {heading, heading_level?, description?, target_keywords?[], notes?}), '
            . 'notes (Array von {note_type, content}). Ideal um aus einem Keyword-Cluster einen Arbeitsauftrag zu machen.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'Titel des Briefs'],
                'description' => ['type' => 'string'],
                'content_type' => ['type' => 'string', 'description' => 'guide/pillar/article/landing (Standard: guide)'],
                'search_intent' => [
                    'type' => 'string',
                    'enum' => ['informational', 'navigational', 'commercial', 'transactional'],
                ],
                'status' => ['type' => 'string', 'description' => 'Lifecycle-Status (Standard: briefed)'],
                'target_url' => ['type' => 'string'],
                'target_slug' => ['type' => 'string'],
                'target_word_count' => ['type' => 'integer'],
                'cluster_id' => ['type' => 'integer', 'description' => 'Cluster, auf den der Brief zielt'],
                'cluster_role' => ['type' => 'string', 'description' => 'Rolle im Cluster (Standard: primary)'],
                'sections' => [
                    'type' => 'array',
                    'description' => 'H2-Abschnitte (Reihenfolge = Array-Reihenfolge)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'heading' => ['type' => 'string'],
                            'heading_level' => ['type' => 'string', 'description' => 'h2/h3 (Standard h2)'],
                            'description' => ['type' => 'string'],
                            'target_keywords' => ['type' => 'array', 'items' => ['type' => 'string']],
                            'notes' => ['type' => 'string'],
                        ],
                    ],
                ],
                'notes' => [
                    'type' => 'array',
                    'description' => 'Brief-weite Notizen (Instruktion, Referenz, ...)',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'note_type' => ['type' => 'string', 'description' => 'instruction/reference/competitor/keyword/...'],
                            'content' => ['type' => 'string'],
                        ],
                    ],
                ],
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

            $teamId = $team->id;
            $userId = $context->user?->id;

            // Cluster auflösen (optional, aber empfohlen)
            $cluster = null;
            if (!empty($arguments['cluster_id'])) {
                $cluster = SeoKeywordCluster::where('team_id', $teamId)->find((int) $arguments['cluster_id']);
                if (!$cluster) {
                    return ToolResult::error('Cluster nicht gefunden.', 'NOT_FOUND');
                }
            }

            $brief = SeoContentBrief::create([
                'team_id' => $teamId,
                'user_id' => $userId,
                'name' => $arguments['name'],
                'description' => $arguments['description'] ?? null,
                'content_type' => $arguments['content_type'] ?? 'guide',
                'search_intent' => $arguments['search_intent'] ?? 'informational',
                'status' => $arguments['status'] ?? 'briefed',
                'target_url' => $arguments['target_url'] ?? null,
                'target_slug' => $arguments['target_slug'] ?? null,
                'target_word_count' => isset($arguments['target_word_count']) ? (int) $arguments['target_word_count'] : null,
            ]);

            if ($cluster) {
                $brief->clusters()->syncWithoutDetaching([
                    $cluster->id => ['role' => $arguments['cluster_role'] ?? 'primary'],
                ]);
            }

            // Sections
            $sectionCount = 0;
            foreach (($arguments['sections'] ?? []) as $i => $section) {
                $heading = is_array($section) ? ($section['heading'] ?? '') : (string) $section;
                if ($heading === '') {
                    continue;
                }
                SeoContentBriefSection::create([
                    'content_brief_id' => $brief->id,
                    'team_id' => $teamId,
                    'user_id' => $userId,
                    'order' => $i,
                    'heading' => $heading,
                    'heading_level' => $section['heading_level'] ?? 'h2',
                    'description' => $section['description'] ?? null,
                    'target_keywords' => $section['target_keywords'] ?? null,
                    'notes' => $section['notes'] ?? null,
                ]);
                $sectionCount++;
            }

            // Notes
            $noteCount = 0;
            foreach (($arguments['notes'] ?? []) as $i => $note) {
                $content = is_array($note) ? ($note['content'] ?? '') : (string) $note;
                if ($content === '') {
                    continue;
                }
                SeoContentBriefNote::create([
                    'content_brief_id' => $brief->id,
                    'team_id' => $teamId,
                    'user_id' => $userId,
                    'note_type' => is_array($note) ? ($note['note_type'] ?? 'instruction') : 'instruction',
                    'content' => $content,
                    'order' => $i,
                ]);
                $noteCount++;
            }

            return ToolResult::success([
                'id' => $brief->id,
                'uuid' => $brief->uuid,
                'name' => $brief->name,
                'status' => $brief->status,
                'target_url' => $brief->target_url,
                'cluster_id' => $cluster?->id,
                'sections' => $sectionCount,
                'notes' => $noteCount,
                'message' => "Content-Brief '{$brief->name}' erstellt ({$sectionCount} Abschnitte, {$noteCount} Notizen).",
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
