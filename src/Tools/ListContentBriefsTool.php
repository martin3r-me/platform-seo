<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoContentBrief;

/**
 * Listet Content-Briefs bzw. zeigt einen Brief im Detail (Sections + Notes +
 * verknüpfte Cluster). Solange es keine dedizierte UI gibt, ist das der
 * Lesezugang zu den Produktions-Plänen.
 */
class ListContentBriefsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.content_briefs.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/content-briefs - Listet Content-Briefs des Teams. Optional: brief_id (Detail mit Sections+Notes+Clustern), '
            . 'status, cluster_id, limit, offset.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'brief_id' => ['type' => 'integer', 'description' => 'Detail eines Briefs inkl. Sections und Notes'],
                'status' => ['type' => 'string'],
                'cluster_id' => ['type' => 'integer', 'description' => 'Nur Briefs, die auf diesen Cluster zielen'],
                'limit' => ['type' => 'integer'],
                'offset' => ['type' => 'integer'],
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

            $teamId = $team->id;

            // Detail
            if (!empty($arguments['brief_id'])) {
                $brief = SeoContentBrief::with(['sections', 'notes', 'clusters'])
                    ->where('team_id', $teamId)
                    ->find((int) $arguments['brief_id']);

                if (!$brief) {
                    return ToolResult::error('Brief nicht gefunden.', 'NOT_FOUND');
                }

                return ToolResult::success([
                    'brief' => [
                        'id' => $brief->id,
                        'uuid' => $brief->uuid,
                        'name' => $brief->name,
                        'description' => $brief->description,
                        'content_type' => $brief->content_type,
                        'search_intent' => $brief->search_intent,
                        'status' => $brief->status,
                        'target_url' => $brief->target_url,
                        'target_slug' => $brief->target_slug,
                        'target_word_count' => $brief->target_word_count,
                        'clusters' => $brief->clusters->map(fn ($c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'role' => $c->pivot->role ?? null,
                        ])->values(),
                        'sections' => $brief->sections->map(fn ($s) => [
                            'order' => $s->order,
                            'heading' => $s->heading,
                            'heading_level' => $s->heading_level,
                            'description' => $s->description,
                            'target_keywords' => $s->target_keywords,
                            'notes' => $s->notes,
                        ])->values(),
                        'notes' => $brief->notes->map(fn ($n) => [
                            'note_type' => $n->note_type,
                            'content' => $n->content,
                        ])->values(),
                    ],
                ]);
            }

            // Liste
            $query = SeoContentBrief::withCount(['sections', 'notes'])
                ->where('team_id', $teamId);

            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['cluster_id'])) {
                $clusterId = (int) $arguments['cluster_id'];
                $query->whereHas('clusters', fn ($q) => $q->where('seo_keyword_clusters.id', $clusterId));
            }

            $limit = (int) ($arguments['limit'] ?? 50);
            $offset = (int) ($arguments['offset'] ?? 0);
            $total = $query->count();

            $briefs = $query->orderByDesc('id')
                ->limit($limit)
                ->offset($offset)
                ->get()
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'name' => $b->name,
                    'status' => $b->status,
                    'content_type' => $b->content_type,
                    'target_url' => $b->target_url,
                    'sections_count' => $b->sections_count,
                    'notes_count' => $b->notes_count,
                ]);

            return ToolResult::success([
                'briefs' => $briefs,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
