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
