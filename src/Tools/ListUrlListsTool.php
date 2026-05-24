<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRelationship;

class ListUrlListsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.url_lists.GET';
    }

    public function getDescription(): string
    {
        return 'GET /seo/url-lists - Listet alle URL-Listen (teamübergreifend). Optional: search, list_id (für Detail mit URLs und aggregierten Metriken). Ohne list_id: Übersicht aller Listen mit URL-Count.';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'search' => [
                    'type' => 'string',
                    'description' => 'Suchbegriff für Listenname',
                ],
                'list_id' => [
                    'type' => 'integer',
                    'description' => 'Wenn angegeben: Detail-Ansicht dieser Liste mit URLs und aggregierten Metriken (inkl. Kind-URLs)',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            // Detail mode
            if (!empty($arguments['list_id'])) {
                return $this->getDetail((int) $arguments['list_id']);
            }

            // List mode
            $query = SeoUrlList::withCount('urls')->orderBy('name');

            if (!empty($arguments['search'])) {
                $query->where('name', 'like', '%' . $arguments['search'] . '%');
            }

            $lists = $query->limit(100)->get();

            return ToolResult::success([
                'lists' => $lists->map(fn (SeoUrlList $list) => [
                    'id' => $list->id,
                    'uuid' => $list->uuid,
                    'name' => $list->name,
                    'slug' => $list->slug,
                    'description' => $list->description,
                    'urls_count' => $list->urls_count,
                    'created_at' => $list->created_at?->toIso8601String(),
                ])->all(),
                'total' => $lists->count(),
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }

    private function getDetail(int $listId): ToolResult
    {
        $list = SeoUrlList::with('urls')->find($listId);

        if (!$list) {
            return ToolResult::error('Liste nicht gefunden.', 'NOT_FOUND');
        }

        $rootUrls = $list->urls;

        // Aggregate metrics incl. child URLs
        $childIds = SeoUrlRelationship::where('type', 'parent_child')
            ->whereIn('source_url_id', $rootUrls->pluck('id'))
            ->pluck('target_url_id');

        $allRelated = SeoUrl::whereIn('id', $rootUrls->pluck('id')->merge($childIds))->get();

        $aggregated = [
            'visibility_score' => round($allRelated->sum('visibility_score'), 1),
            'keyword_count' => $allRelated->sum('keyword_count'),
            'total_search_volume' => $allRelated->sum('total_search_volume'),
            'backlink_count' => $allRelated->sum('backlink_count'),
        ];

        $urls = $rootUrls->map(function (SeoUrl $url) {
            $childIds = SeoUrlRelationship::where('type', 'parent_child')
                ->where('source_url_id', $url->id)
                ->pluck('target_url_id');
            $children = $childIds->isNotEmpty() ? SeoUrl::whereIn('id', $childIds)->get() : collect();

            return [
                'id' => $url->id,
                'uuid' => $url->uuid,
                'url' => $url->url,
                'domain' => $url->domain,
                'path' => $url->path,
                'child_count' => $children->count(),
                'agg_visibility' => round((float) $url->visibility_score + $children->sum('visibility_score'), 1),
                'agg_keywords' => $url->keyword_count + $children->sum('keyword_count'),
                'agg_search_volume' => $url->total_search_volume + $children->sum('total_search_volume'),
                'agg_backlinks' => $url->backlink_count + $children->sum('backlink_count'),
                'added_at' => $url->pivot->added_at,
            ];
        })->all();

        return ToolResult::success([
            'list' => [
                'id' => $list->id,
                'uuid' => $list->uuid,
                'name' => $list->name,
                'slug' => $list->slug,
                'description' => $list->description,
            ],
            'aggregated' => $aggregated,
            'urls' => $urls,
            'urls_count' => count($urls),
        ]);
    }
}
