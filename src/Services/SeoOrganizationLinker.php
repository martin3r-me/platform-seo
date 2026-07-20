<?php

namespace Platform\Seo\Services;

use Illuminate\Support\Facades\Log;

/**
 * Entkoppelter Adapter zum Organization-Modul.
 *
 * Hängt SEO-Records (URLs, Cluster, Signale) über den generischen Dimension-Link-
 * Layer an Organisations-Knoten — ohne harte Foreign Keys. Der Knoten ist die
 * Verteil-Schiene: an ihm treffen sich URL, Marke und Flynk-Container.
 *
 * Alle Aufrufe sind gegen ein fehlendes/deaktiviertes Organization-Modul
 * abgesichert; das SEO-Modul funktioniert auch ohne (No-Op statt Fehler).
 */
class SeoOrganizationLinker
{
    public const ALIAS_URL = 'seo_url';
    public const ALIAS_URL_LIST = 'seo_url_list';
    public const ALIAS_CLUSTER = 'seo_cluster';
    public const ALIAS_CONTENT_BRIEF = 'seo_content_brief';
    public const ALIAS_SIGNAL = 'seo_signal';

    /**
     * Liefert die Bridge-Klasse, wenn das Organization-Modul geladen ist.
     *
     * @return class-string|null
     */
    protected function bridge(): ?string
    {
        $class = \Platform\Organization\Services\EntityDimensionBridge::class;

        return class_exists($class) ? $class : null;
    }

    /**
     * Setzt den Heimat-Knoten eines Records (ersetzt bestehende Knoten-Links).
     */
    public function setNode(string $morphAlias, int $id, int $entityId): void
    {
        $bridge = $this->bridge();
        if (! $bridge) {
            return;
        }

        try {
            $bridge::replaceLinks($morphAlias, $id, $entityId);
        } catch (\Throwable $e) {
            $this->logFailure('setNode', $morphAlias, $id, $entityId, $e);
        }
    }

    /**
     * Fügt einen zusätzlichen Knoten-Link hinzu (Mehrfach-Kontext möglich).
     */
    public function addNode(string $morphAlias, int $id, int $entityId): void
    {
        $bridge = $this->bridge();
        if (! $bridge) {
            return;
        }

        try {
            $bridge::createLink($entityId, $morphAlias, $id);
        } catch (\Throwable $e) {
            $this->logFailure('addNode', $morphAlias, $id, $entityId, $e);
        }
    }

    /**
     * Entfernt einen bestimmten Knoten-Link.
     */
    public function unlink(string $morphAlias, int $id, int $entityId): void
    {
        $bridge = $this->bridge();
        if (! $bridge) {
            return;
        }

        try {
            $bridge::deleteLink($entityId, $morphAlias, $id);
        } catch (\Throwable $e) {
            $this->logFailure('unlink', $morphAlias, $id, $entityId, $e);
        }
    }

    /**
     * Knoten-IDs, an denen ein einzelner Record hängt.
     *
     * @return int[]
     */
    public function nodeIdsFor(string $morphAlias, int $id): array
    {
        return $this->nodeIdsForMany($morphAlias, [$id])[$id] ?? [];
    }

    /**
     * Batch: Knoten-IDs je Record.
     *
     * @param  int[]  $ids
     * @return array<int,int[]>  [record_id => [entity_id, ...]]
     */
    public function nodeIdsForMany(string $morphAlias, array $ids): array
    {
        $bridge = $this->bridge();
        if (! $bridge || empty($ids)) {
            return [];
        }

        try {
            $result = [];
            foreach ($bridge::linksForLinkables([$morphAlias], $ids, false) as $link) {
                $entityId = $link->entity_id ?? null;
                if ($entityId) {
                    $result[$link->linkable_id][] = (int) $entityId;
                }
            }

            return array_map(fn ($e) => array_values(array_unique($e)), $result);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Rückrichtung: Record-IDs eines Typs, die an einem Knoten hängen.
     *
     * @return int[]
     */
    public function linkableIdsForNode(string $morphAlias, int $entityId): array
    {
        $bridge = $this->bridge();
        if (! $bridge) {
            return [];
        }

        try {
            return $bridge::linksForEntity($entityId)
                ->filter(fn ($link) => $link->linkable_type === $morphAlias)
                ->pluck('linkable_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Der Knoten selbst + alle Nachfahren (Teilbaum) — Basis der Perspektive.
     * Guarded: nur der Knoten selbst, wenn das Organization-Modul fehlt.
     *
     * @return int[]
     */
    public function descendantEntityIds(int $entityId): array
    {
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (! class_exists($class)) {
            return [$entityId];
        }

        try {
            $all = [$entityId];
            $frontier = [$entityId];
            $guard = 0;
            while (! empty($frontier) && $guard++ < 50) {
                $children = $class::whereIn('parent_entity_id', $frontier)
                    ->pluck('id')->map(fn ($i) => (int) $i)->all();
                $children = array_values(array_diff($children, $all));
                if (empty($children)) {
                    break;
                }
                $all = array_merge($all, $children);
                $frontier = $children;
            }

            return $all;
        } catch (\Throwable $e) {
            return [$entityId];
        }
    }

    /**
     * Record-IDs eines Typs, die an einer Menge von Knoten hängen (Union).
     *
     * @param  int[]  $entityIds
     * @return int[]
     */
    public function linkableIdsForNodes(string $morphAlias, array $entityIds): array
    {
        $ids = [];
        foreach ($entityIds as $entityId) {
            foreach ($this->linkableIdsForNode($morphAlias, (int) $entityId) as $lid) {
                $ids[$lid] = true;
            }
        }

        return array_map('intval', array_keys($ids));
    }

    /**
     * Entitäten, die von einem Anker über einen Relationstyp (code) ausgehen —
     * z.B. „provides_service_to" → die Kunden. Selektor der Perspektive. Guarded.
     *
     * @return int[]
     */
    public function relatedEntityIds(int $entityId, string $relationCode): array
    {
        $relClass = \Platform\Organization\Models\OrganizationEntityRelationship::class;
        $typeClass = \Platform\Organization\Models\OrganizationEntityRelationType::class;
        if (! class_exists($relClass) || ! class_exists($typeClass)) {
            return [];
        }

        try {
            $typeId = $typeClass::where('code', $relationCode)->value('id');
            if (! $typeId) {
                return [];
            }

            return $relClass::where('from_entity_id', $entityId)
                ->where('relation_type_id', $typeId)
                ->pluck('to_entity_id')
                ->map(fn ($i) => (int) $i)
                ->unique()->values()->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Ausgehende Relationen eines Knotens (datengetriebenes Unter-Menü):
     * welche Relationstypen hat dieser Knoten wirklich, mit wie vielen Zielen?
     *
     * @return array<int,array{code:string,name:string,count:int}>
     */
    public function availableRelations(int $entityId): array
    {
        $relClass = \Platform\Organization\Models\OrganizationEntityRelationship::class;
        if (! class_exists($relClass)) {
            return [];
        }

        try {
            $rows = $relClass::where('from_entity_id', $entityId)
                ->with('relationType:id,code,name')
                ->get(['id', 'relation_type_id', 'to_entity_id']);

            $byType = [];
            foreach ($rows as $row) {
                $type = $row->relationType;
                if (! $type) {
                    continue;
                }
                $byType[$type->code] ??= ['code' => $type->code, 'name' => $type->name, 'count' => 0];
                $byType[$type->code]['count']++;
            }

            return array_values($byType);
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function relationName(string $code): ?string
    {
        $typeClass = \Platform\Organization\Models\OrganizationEntityRelationType::class;
        if (! class_exists($typeClass)) {
            return null;
        }
        try {
            return $typeClass::where('code', $code)->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Welche der gegebenen Record-IDs hängen an irgendeinem Knoten? (für „Ablage").
     *
     * @param  int[]  $ids
     * @return int[]
     */
    public function linkedLinkableIds(string $morphAlias, array $ids): array
    {
        $bridge = $this->bridge();
        if (! $bridge || empty($ids)) {
            return [];
        }
        try {
            $linked = [];
            foreach ($bridge::linksForLinkables([$morphAlias], $ids) as $link) {
                $linked[(int) $link->linkable_id] = true;
            }

            return array_keys($linked);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Batch: Knoten (id + name) je Record — für Anzeige/Diagnose.
     *
     * @param  int[]  $ids
     * @return array<int,array<int,array{id:int,name:?string}>>
     */
    public function nodesForMany(string $morphAlias, array $ids): array
    {
        $bridge = $this->bridge();
        if (! $bridge || empty($ids)) {
            return [];
        }

        try {
            $result = [];
            foreach ($bridge::linksForLinkables([$morphAlias], $ids, true) as $link) {
                $entityId = $link->entity_id ?? null;
                if (! $entityId) {
                    continue;
                }
                $result[$link->linkable_id][] = [
                    'id' => (int) $entityId,
                    'name' => $link->entity?->name,
                ];
            }

            return $result;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Verfügbare Organisations-Knoten eines Teams — für Kontext-Picker in der UI.
     * Guarded: leeres Array, wenn das Organization-Modul nicht geladen ist.
     *
     * @return array<int,array{id:int,name:string}>
     */
    public function availableNodes(int $teamId): array
    {
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (! class_exists($class)) {
            return [];
        }

        try {
            return $class::query()
                ->where('team_id', $teamId)
                ->where('is_active', true)
                ->orderBy('name')
                ->limit(500)
                ->get(['id', 'name'])
                ->map(fn ($e) => ['id' => (int) $e->id, 'name' => (string) $e->name])
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Name eines Organisations-Knotens (guarded; null ohne Organization-Modul).
     */
    public function nodeName(int $entityId): ?string
    {
        $class = \Platform\Organization\Models\OrganizationEntity::class;
        if (! class_exists($class)) {
            return null;
        }

        try {
            return $class::query()->whereKey($entityId)->value('name');
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected function logFailure(string $op, string $alias, int $id, int $entityId, \Throwable $e): void
    {
        Log::warning('SEO: Knoten-Verlinkung fehlgeschlagen', [
            'op' => $op,
            'alias' => $alias,
            'id' => $id,
            'entity_id' => $entityId,
            'error' => $e->getMessage(),
        ]);
    }
}
