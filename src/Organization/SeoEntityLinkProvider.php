<?php

namespace Platform\Seo\Organization;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Platform\Organization\Contracts\EntityLinkProvider;
use Platform\Organization\Contracts\HasMetricDefinitions;

class SeoEntityLinkProvider implements EntityLinkProvider, HasMetricDefinitions
{
    public function morphAliases(): array
    {
        return ['seo_url', 'seo_url_list'];
    }

    public function linkTypeConfig(): array
    {
        return [
            'seo_url' => [
                'label' => 'URLs',
                'singular' => 'URL',
                'icon' => 'globe-alt',
                'route' => 'seo.urls.show',
            ],
            'seo_url_list' => [
                'label' => 'URL-Listen',
                'singular' => 'URL-Liste',
                'icon' => 'queue-list',
                'route' => 'seo.lists.show',
            ],
        ];
    }

    public function applyEagerLoading(Builder $query, string $morphAlias, string $fqcn): void
    {
        if ($morphAlias === 'seo_url_list') {
            $query->withCount('urls');
        }
    }

    public function extractMetadata(string $morphAlias, mixed $model): array
    {
        return match ($morphAlias) {
            'seo_url' => [
                'visibility_score' => $model->visibility_score,
                'keyword_count' => $model->keyword_count,
                'total_search_volume' => $model->total_search_volume,
                'domain' => $model->domain,
                'path' => $model->path,
            ],
            'seo_url_list' => [
                'urls_count' => $model->urls_count ?? 0,
            ],
            default => [],
        };
    }

    public function metadataDisplayRules(): array
    {
        return [
            'seo_url' => [
                ['field' => 'visibility_score', 'format' => 'score', 'label' => 'Sichtbarkeit'],
                ['field' => 'keyword_count', 'format' => 'count', 'label' => 'Keywords'],
                ['field' => 'domain', 'format' => 'text', 'label' => 'Domain'],
            ],
            'seo_url_list' => [
                ['field' => 'urls_count', 'format' => 'count', 'label' => 'URLs', 'suffix' => 'URLs'],
            ],
        ];
    }

    public function timeTrackableCascades(): array
    {
        return [];
    }

    public function metrics(string $morphAlias, array $linksByEntity): array
    {
        return match ($morphAlias) {
            'seo_url' => $this->urlMetrics($linksByEntity),
            'seo_url_list' => $this->urlListMetrics($linksByEntity),
            default => [],
        };
    }

    protected function urlMetrics(array $linksByEntity): array
    {
        $allIds = [];
        foreach ($linksByEntity as $ids) {
            $allIds = array_merge($allIds, $ids);
        }
        $allIds = array_values(array_unique($allIds));

        if (empty($allIds)) {
            return [];
        }

        $urls = DB::table('seo_urls')
            ->whereIn('id', $allIds)
            ->whereNull('deleted_at')
            ->select('id', 'visibility_score', 'keyword_count', 'total_search_volume', 'backlink_count')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $ids) {
            $visibility = 0;
            $keywords = 0;
            $searchVolume = 0;
            $backlinks = 0;

            foreach ($ids as $id) {
                $url = $urls->get($id);
                if (! $url) {
                    continue;
                }
                $visibility += (float) $url->visibility_score;
                $keywords += (int) $url->keyword_count;
                $searchVolume += (int) $url->total_search_volume;
                $backlinks += (int) $url->backlink_count;
            }

            $result[$entityId] = [
                'seo_visibility' => $visibility,
                'seo_keywords' => $keywords,
                'seo_search_volume' => $searchVolume,
                'seo_backlinks' => $backlinks,
            ];
        }

        return $result;
    }

    protected function urlListMetrics(array $linksByEntity): array
    {
        $allListIds = [];
        foreach ($linksByEntity as $ids) {
            $allListIds = array_merge($allListIds, $ids);
        }
        $allListIds = array_values(array_unique($allListIds));

        if (empty($allListIds)) {
            return [];
        }

        // Load URL IDs per list
        $listUrlIds = DB::table('seo_url_list_entries')
            ->whereIn('list_id', $allListIds)
            ->select('list_id', 'url_id')
            ->get()
            ->groupBy('list_id')
            ->map(fn ($rows) => $rows->pluck('url_id')->all());

        // Collect all unique URL IDs across all lists
        $allUrlIds = $listUrlIds->flatten()->unique()->values()->all();

        if (empty($allUrlIds)) {
            // Return zeros for all entities
            $result = [];
            foreach ($linksByEntity as $entityId => $ids) {
                $result[$entityId] = [
                    'seo_visibility' => 0,
                    'seo_keywords' => 0,
                    'seo_search_volume' => 0,
                    'seo_backlinks' => 0,
                ];
            }
            return $result;
        }

        $urls = DB::table('seo_urls')
            ->whereIn('id', $allUrlIds)
            ->whereNull('deleted_at')
            ->select('id', 'visibility_score', 'keyword_count', 'total_search_volume', 'backlink_count')
            ->get()
            ->keyBy('id');

        $result = [];
        foreach ($linksByEntity as $entityId => $listIds) {
            // Deduplicate URL IDs across all lists for this entity
            $entityUrlIds = [];
            foreach ($listIds as $listId) {
                $urlIdsForList = $listUrlIds->get($listId, []);
                foreach ($urlIdsForList as $urlId) {
                    $entityUrlIds[$urlId] = true;
                }
            }

            $visibility = 0;
            $keywords = 0;
            $searchVolume = 0;
            $backlinks = 0;

            foreach (array_keys($entityUrlIds) as $urlId) {
                $url = $urls->get($urlId);
                if (! $url) {
                    continue;
                }
                $visibility += (float) $url->visibility_score;
                $keywords += (int) $url->keyword_count;
                $searchVolume += (int) $url->total_search_volume;
                $backlinks += (int) $url->backlink_count;
            }

            $result[$entityId] = [
                'seo_visibility' => $visibility,
                'seo_keywords' => $keywords,
                'seo_search_volume' => $searchVolume,
                'seo_backlinks' => $backlinks,
            ];
        }

        return $result;
    }

    public function activityChildren(string $morphAlias, array $linkableIds): array
    {
        return [];
    }

    public function metricDefinitions(): array
    {
        return [
            'seo_visibility'    => ['label' => 'Sichtbarkeit (SEO)', 'group' => 'seo', 'direction' => 'up', 'unit' => 'score', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
            'seo_keywords'      => ['label' => 'Ranking-Keywords', 'group' => 'seo', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
            'seo_search_volume' => ['label' => 'Suchvolumen', 'group' => 'seo', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
            'seo_backlinks'     => ['label' => 'Backlinks', 'group' => 'seo', 'direction' => 'up', 'unit' => 'count', 'dimension' => 'potential', 'type' => 'stock', 'aggregation_mode' => 'rolled_up'],
        ];
    }
}
