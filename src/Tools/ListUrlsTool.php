<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;
use Platform\Seo\Services\SeoUrlService;

class ListUrlsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.urls.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/urls - Listet SEO-URLs des Teams. Optional: search (URL-Suche), is_own (true/false), status (active/redirected/deleted/error), url_id (Detail mit Metriken, Keywords, Relationships), domain, limit, offset. Ohne url_id: Übersicht.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'url_id' => [
                    'type' => 'integer',
                    'description' => 'Detail-Ansicht einer URL mit Keywords, On-Page-Daten, Relationships',
                ],
                'search' => [
                    'type' => 'string',
                    'description' => 'Suche in URL',
                ],
                'is_own' => [
                    'type' => 'boolean',
                    'description' => 'Filter: eigene URLs (true) oder Wettbewerber (false)',
                ],
                'status' => [
                    'type' => 'string',
                    'enum' => ['active', 'redirected', 'deleted', 'error'],
                ],
                'domain' => [
                    'type' => 'string',
                    'description' => 'Filter nach Domain',
                ],
                'sort' => [
                    'type' => 'string',
                    'enum' => ['visibility_score', 'keyword_count', 'total_search_volume', 'backlink_count', 'last_crawled_at', 'url'],
                    'description' => 'Sortierfeld (Standard: visibility_score)',
                ],
                'sort_dir' => [
                    'type' => 'string',
                    'enum' => ['asc', 'desc'],
                ],
                'include_children' => [
                    'type' => 'boolean',
                    'description' => 'Auch Kind-URLs anzeigen (Standard: false, nur Root-URLs).',
                ],
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

            // Detail mode
            if (!empty($arguments['url_id'])) {
                return $this->getDetail((int) $arguments['url_id'], $team->id);
            }

            $query = SeoUrl::where('team_id', $team->id);

            // Nur Root-URLs (keine Kinder) — es sei denn explizit alle gewünscht
            if (empty($arguments['include_children'])) {
                $childIds = SeoUrlRelationship::where('team_id', $team->id)
                    ->where('type', 'parent_child')
                    ->pluck('target_url_id');
                if ($childIds->isNotEmpty()) {
                    $query->whereNotIn('id', $childIds);
                }
            }

            if (!empty($arguments['search'])) {
                $query->where('url', 'like', '%' . $arguments['search'] . '%');
            }
            if (isset($arguments['is_own'])) {
                $query->where('is_own', (bool) $arguments['is_own']);
            }
            if (!empty($arguments['status'])) {
                $query->where('status', $arguments['status']);
            }
            if (!empty($arguments['domain'])) {
                $query->where('domain', $arguments['domain']);
            }

            $sort = $arguments['sort'] ?? 'visibility_score';
            $dir = $arguments['sort_dir'] ?? 'desc';
            $query->orderBy($sort, $dir);

            $limit = min((int) ($arguments['limit'] ?? 50), 200);
            $offset = (int) ($arguments['offset'] ?? 0);

            $total = $query->count();
            $urls = $query->skip($offset)->take($limit)->get();

            // Kinder-URLs pro Root laden für Aggregation
            $urlIds = $urls->pluck('id');
            $childRelations = SeoUrlRelationship::where('type', 'parent_child')
                ->whereIn('source_url_id', $urlIds)
                ->pluck('target_url_id', 'source_url_id')
                ->groupBy(fn ($childId, $parentId) => $parentId);

            $allChildIds = SeoUrlRelationship::where('type', 'parent_child')
                ->whereIn('source_url_id', $urlIds)
                ->pluck('target_url_id');
            $childUrls = $allChildIds->isNotEmpty()
                ? SeoUrl::whereIn('id', $allChildIds)->get()->keyBy('id')
                : collect();

            return ToolResult::success([
                'urls' => $urls->map(function (SeoUrl $u) use ($childRelations, $childUrls) {
                    $children = collect();
                    if (isset($childRelations[$u->id])) {
                        $children = $childRelations[$u->id]->map(fn ($childId) => $childUrls->get($childId))->filter();
                    }

                    return [
                        'id' => $u->id,
                        'uuid' => $u->uuid,
                        'url' => $u->url,
                        'domain' => $u->domain,
                        'path' => $u->path,
                        'is_own' => $u->is_own,
                        'status' => $u->status,
                        'http_status' => $u->http_status,
                        'keyword_count' => $u->keyword_count + $children->sum('keyword_count'),
                        'total_search_volume' => $u->total_search_volume + $children->sum('total_search_volume'),
                        'visibility_score' => (float) $u->visibility_score + (float) $children->sum('visibility_score'),
                        'backlink_count' => $u->backlink_count + $children->sum('backlink_count'),
                        'child_count' => $children->count(),
                        'last_crawled_at' => $u->last_crawled_at?->toIso8601String(),
                    ];
                })->all(),
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function getDetail(int $urlId, int $teamId): ToolResult
    {
        $url = SeoUrl::with(['onPage', 'keywords', 'sourceRelationships.targetUrl', 'targetRelationships.sourceUrl'])
            ->where('team_id', $teamId)
            ->find($urlId);

        if (!$url) {
            return ToolResult::error('URL nicht gefunden.', 'NOT_FOUND');
        }

        $keywords = $url->keywords->map(fn ($kw) => [
            'id' => $kw->id,
            'keyword' => $kw->keyword,
            'search_volume' => $kw->search_volume,
            'position' => $kw->pivot->position,
            'previous_position' => $kw->pivot->previous_position,
            'keyword_difficulty' => $kw->keyword_difficulty,
            'search_intent' => $kw->search_intent,
        ])->all();

        $relationships = [];
        foreach ($url->sourceRelationships as $rel) {
            $relationships[] = [
                'type' => $rel->type,
                'direction' => 'outgoing',
                'related_url' => $rel->targetUrl?->url,
                'related_url_id' => $rel->target_url_id,
                'strength' => $rel->strength,
            ];
        }
        foreach ($url->targetRelationships as $rel) {
            $relationships[] = [
                'type' => $rel->type,
                'direction' => 'incoming',
                'related_url' => $rel->sourceUrl?->url,
                'related_url_id' => $rel->source_url_id,
                'strength' => $rel->strength,
            ];
        }

        $onPage = null;
        if ($url->onPage) {
            $op = $url->onPage;
            $onPage = [
                'title' => $op->title,
                'meta_description' => $op->meta_description,
                'h1' => $op->h1,
                'word_count' => $op->word_count,
                'page_speed_score' => $op->page_speed_score,
                'mobile_score' => $op->mobile_score,
                'overall_score' => $op->overall_score,
                'issues' => $op->issues,
                'analyzed_at' => $op->analyzed_at?->toIso8601String(),
            ];
        }

        return ToolResult::success([
            'url' => [
                'id' => $url->id,
                'uuid' => $url->uuid,
                'url' => $url->url,
                'domain' => $url->domain,
                'path' => $url->path,
                'is_own' => $url->is_own,
                'status' => $url->status,
                'http_status' => $url->http_status,
                'priority' => $url->priority,
                'keyword_count' => $url->keyword_count,
                'total_search_volume' => $url->total_search_volume,
                'visibility_score' => (float) $url->visibility_score,
                'backlink_count' => $url->backlink_count,
                'redirect_url' => $url->redirect_url,
                'last_crawled_at' => $url->last_crawled_at?->toIso8601String(),
                'meta' => $url->meta,
            ],
            'on_page' => $onPage,
            'keywords' => $keywords,
            'keywords_count' => count($keywords),
            'relationships' => $relationships,
        ]);
    }
}
