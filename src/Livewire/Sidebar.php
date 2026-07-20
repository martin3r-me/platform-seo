<?php

namespace Platform\Seo\Livewire;

use Livewire\Component;
use Platform\Organization\Models\OrganizationEntity;
use Platform\Organization\Services\EntityDimensionBridge;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlList;
use Platform\Seo\Models\SeoUrlRegistration;
use Platform\Seo\Models\SeoUrlRelationship;

class Sidebar extends Component
{
    public function render()
    {
        $user = auth()->user();
        $teamId = $user?->currentTeam->id ?? null;

        if (!$user || !$teamId) {
            return view('seo::livewire.sidebar', [
                'entityTypeGroups' => collect(),
                'unlinkedLists' => collect(),
                'moduleGroups' => collect(),
                'unassignedUrls' => collect(),
            ]);
        }

        // 1. Load URLs for this team (root-only — Child-URLs würden die Sidebar
        //    unübersichtlich machen; wie im URL-Explorer nur Parent-URLs zeigen).
        $childUrlIds = SeoUrlRelationship::where('team_id', $teamId)
            ->where('type', 'parent_child')
            ->pluck('target_url_id');

        $urls = SeoUrl::where('team_id', $teamId)
            ->where('status', 'active')
            ->when($childUrlIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $childUrlIds))
            ->orderBy('url')
            ->get();

        // Lists don't have team_id — scope through URLs belonging to this team
        $lists = SeoUrlList::whereHas('urls', function ($q) use ($teamId) {
            $q->where('seo_urls.team_id', $teamId);
        })->withCount('urls')->orderBy('name')->get();

        // 2. Get entity links for both types
        $listIds = $lists->pluck('id')->toArray();
        $urlIds = $urls->pluck('id')->toArray();

        $entityItemMap = []; // entity_id => ['lists' => [...], 'urls' => [...]]
        $linkedListIds = [];
        $linkedUrlIds = [];

        try {
            // Lists linked to entities
            if (!empty($listIds)) {
                $listLinks = EntityDimensionBridge::linksForLinkables(
                    ['seo_url_list', SeoUrlList::class],
                    $listIds
                );
                foreach ($listLinks as $link) {
                    $entityItemMap[$link->entity_id]['lists'][] = $link->linkable_id;
                    $linkedListIds[] = $link->linkable_id;
                }
            }

            // URLs linked to entities
            if (!empty($urlIds)) {
                $urlLinks = EntityDimensionBridge::linksForLinkables(
                    ['seo_url', SeoUrl::class],
                    $urlIds
                );
                foreach ($urlLinks as $link) {
                    $entityItemMap[$link->entity_id]['urls'][] = $link->linkable_id;
                    $linkedUrlIds[] = $link->linkable_id;
                }
            }
        } catch (\Throwable $e) {
            // Organization module not loaded
        }

        $linkedListIds = array_unique($linkedListIds);
        $linkedUrlIds = array_unique($linkedUrlIds);

        // 3. Ancestor traversal for tree display
        $directEntityIds = array_keys($entityItemMap);
        if (!empty($directEntityIds)) {
            $directEntities = OrganizationEntity::with(['allParents.type'])
                ->whereIn('id', $directEntityIds)
                ->get()
                ->keyBy('id');

            foreach ($directEntities as $entityId => $entity) {
                $ancestor = $entity->allParents;
                while ($ancestor) {
                    if (!isset($entityItemMap[$ancestor->id])) {
                        $entityItemMap[$ancestor->id] = [];
                    }
                    $ancestor = $ancestor->allParents;
                }
            }
        }

        // 4. Build entity type groups (tree structure)
        $entityTypeGroups = collect();
        $entityIds = array_keys($entityItemMap);

        if (!empty($entityIds)) {
            $entities = OrganizationEntity::with('type')
                ->whereIn('id', $entityIds)
                ->get()
                ->keyBy('id');

            // Parent-child relationships
            $entityChildrenMap = [];
            $rootEntityIds = [];

            foreach ($entities as $entity) {
                $parentId = $entity->parent_entity_id;
                if ($parentId && $entities->has($parentId)) {
                    $entityChildrenMap[$parentId][] = $entity->id;
                } else {
                    $rootEntityIds[] = $entity->id;
                }
            }

            // Recursive tree builder
            $buildTree = function (int $entityId) use (&$buildTree, $entities, $entityChildrenMap, $entityItemMap, $lists, $urls): ?array {
                $entity = $entities->get($entityId);
                if (!$entity) {
                    return null;
                }

                $childIds = $entityChildrenMap[$entityId] ?? [];
                $childNodes = collect($childIds)
                    ->map(fn ($childId) => $buildTree($childId))
                    ->filter();

                // Children grouped by type
                $childrenByType = $childNodes
                    ->groupBy(fn ($child) => $child['type_id'])
                    ->map(function ($group) use ($entities) {
                        $firstChild = $group->first();
                        $typeEntity = $entities->get($firstChild['entity_id']);
                        $type = $typeEntity?->type;

                        return [
                            'type_id' => $firstChild['type_id'],
                            'type_name' => $type?->name ?? 'Sonstige',
                            'type_icon' => $type?->icon ?? null,
                            'sort_order' => $type?->sort_order ?? 999,
                            'children' => $group->sortBy('entity_name')->values(),
                        ];
                    })
                    ->sortBy('sort_order')
                    ->values();

                $itemData = $entityItemMap[$entityId] ?? [];

                $entityLists = collect($itemData['lists'] ?? [])
                    ->map(fn ($id) => $lists->firstWhere('id', $id))
                    ->filter()
                    ->values();

                $entityUrls = collect($itemData['urls'] ?? [])
                    ->map(fn ($id) => $urls->firstWhere('id', $id))
                    ->filter()
                    ->values();

                // Total items count (own + children)
                $totalItems = $entityLists->count() + $entityUrls->count();
                foreach ($childNodes as $child) {
                    $totalItems += $child['total_items'];
                }

                if ($totalItems === 0) {
                    return null;
                }

                return [
                    'entity_id' => $entityId,
                    'entity_name' => $entity->name,
                    'type_id' => $entity->type?->id,
                    'lists' => $entityLists,
                    'urls' => $entityUrls,
                    'children_by_type' => $childrenByType,
                    'total_items' => $totalItems,
                ];
            };

            // Root entities grouped by type
            $groupedByType = [];
            foreach ($rootEntityIds as $entityId) {
                $entity = $entities->get($entityId);
                if (!$entity || !$entity->type) {
                    continue;
                }

                $tree = $buildTree($entityId);
                if (!$tree) {
                    continue;
                }

                $typeId = $entity->type->id;
                if (!isset($groupedByType[$typeId])) {
                    $groupedByType[$typeId] = [
                        'type_id' => $typeId,
                        'type_name' => $entity->type->name,
                        'type_icon' => $entity->type->icon,
                        'sort_order' => $entity->type->sort_order ?? 999,
                        'entities' => [],
                    ];
                }
                $groupedByType[$typeId]['entities'][] = $tree;
            }

            $entityTypeGroups = collect($groupedByType)
                ->sortBy('sort_order')
                ->map(function ($group) {
                    $group['entities'] = collect($group['entities'])
                        ->sortBy('entity_name')
                        ->values();
                    return $group;
                })
                ->values();
        }

        // 5. Nicht am Baum hängende Listen + URLs
        $unlinkedLists = $lists->filter(fn ($list) => !in_array($list->id, $linkedListIds))->values();
        $unlinkedUrls = $urls->filter(fn ($url) => !in_array($url->id, $linkedUrlIds))->values();

        // Owner-Segmentierung (das Werkzeug): woher stammt eine nicht-verankerte URL?
        //  - Modul-URLs (source_module != seo) → eigene Gruppe je Modul (haben ein Zuhause)
        //  - Agentur-URLs ohne Knoten → „Einzuordnen" (die echte Arbeit)
        //  - Wettbewerber (is_own=false) → nicht in der Sidebar (eigene Linse)
        $ownerByUrl = [];
        $unlinkedIds = $unlinkedUrls->pluck('id')->all();
        if (!empty($unlinkedIds)) {
            foreach (SeoUrlRegistration::whereIn('url_id', $unlinkedIds)
                        ->where('source_module', '!=', 'seo')
                        ->get(['url_id', 'source_module']) as $reg) {
                $ownerByUrl[$reg->url_id] ??= $reg->source_module;
            }
        }

        $moduleUrlGroups = [];
        $unassignedUrls = collect();
        foreach ($unlinkedUrls as $url) {
            if (! $url->is_own) {
                continue; // Wettbewerber gehören in die Wettbewerber-Linse
            }
            $owner = $ownerByUrl[$url->id] ?? null;
            if ($owner) {
                $moduleUrlGroups[$owner][] = $url;
            } else {
                $unassignedUrls->push($url);
            }
        }

        $moduleGroups = collect($moduleUrlGroups)->map(fn ($groupUrls, $module) => [
            'module' => $module,
            'label' => config('seo.provenance.'.$module.'.label') ?? ucfirst($module),
            'urls' => collect($groupUrls),
        ])->values();

        return view('seo::livewire.sidebar', [
            'entityTypeGroups' => $entityTypeGroups,
            'unlinkedLists' => $unlinkedLists,
            'moduleGroups' => $moduleGroups,
            'unassignedUrls' => $unassignedUrls,
        ]);
    }
}
