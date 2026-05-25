<?php

namespace Platform\Seo\Tools;

use Platform\Core\Contracts\ToolContract;
use Platform\Core\Contracts\ToolContext;
use Platform\Core\Contracts\ToolResult;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Models\SeoUrlRelationship;

class RepairRelationshipsTool implements ToolContract
{
    public function getName(): string
    {
        return 'seo.relationships.repair';
    }

    public function getDescription(): string
    {
        return 'POST /seo/relationships/repair - Repariert fehlerhafte parent_child-Beziehungen. Entfernt zirkuläre Referenzen und korrigiert die Richtung (Root-URL "/" darf nie Kind sein). Optional: domain (nur für eine Domain reparieren).';
    }

    public function getSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'domain' => [
                    'type' => 'string',
                    'description' => 'Optional: Nur Beziehungen dieser Domain reparieren',
                ],
                'dry_run' => [
                    'type' => 'boolean',
                    'description' => 'Nur anzeigen was repariert würde, ohne Änderungen',
                ],
            ],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        try {
            $team = $context->team;
            if (! $team) {
                return ToolResult::error('Kein Team im Kontext.', 'MISSING_TEAM');
            }

            $dryRun = $arguments['dry_run'] ?? false;
            $filterDomain = $arguments['domain'] ?? null;

            $urlQuery = SeoUrl::where('team_id', $team->id);
            if ($filterDomain) {
                $urlQuery->where('domain', $filterDomain);
            }
            $urls = $urlQuery->get();

            if ($urls->isEmpty()) {
                return ToolResult::error('Keine URLs gefunden.', 'NOT_FOUND');
            }

            // Nach Domain gruppieren
            $byDomain = $urls->groupBy('domain');
            $repaired = [];

            foreach ($byDomain as $domain => $domainUrls) {
                $domainUrlIds = $domainUrls->pluck('id')->all();

                // Root-URL bestimmen (kürzester Pfad)
                $rootUrl = $domainUrls->sortBy(fn ($u) => strlen(rtrim($u->path ?: '/', '/')))->first();
                if (! $rootUrl) {
                    continue;
                }

                $domainRepairs = [
                    'domain' => $domain,
                    'root_url_id' => $rootUrl->id,
                    'root_path' => $rootUrl->path ?: '/',
                    'circular_removed' => 0,
                    'reversed_fixed' => 0,
                ];

                // 1. Zirkuläre Referenzen entfernen: A→B und B→A
                $relationships = SeoUrlRelationship::where('type', 'parent_child')
                    ->whereIn('source_url_id', $domainUrlIds)
                    ->whereIn('target_url_id', $domainUrlIds)
                    ->get();

                $pairs = [];
                $toDelete = [];
                foreach ($relationships as $rel) {
                    $key = min($rel->source_url_id, $rel->target_url_id) . '-' . max($rel->source_url_id, $rel->target_url_id);
                    if (isset($pairs[$key])) {
                        // Zirkulär: die falsche Richtung löschen (wo die kürzere URL target ist)
                        $sourceUrl = $domainUrls->firstWhere('id', $rel->source_url_id);
                        $targetUrl = $domainUrls->firstWhere('id', $rel->target_url_id);
                        $sourcePath = strlen(rtrim($sourceUrl->path ?? '/', '/'));
                        $targetPath = strlen(rtrim($targetUrl->path ?? '/', '/'));

                        if ($sourcePath >= $targetPath) {
                            // Source hat längeren/gleichen Pfad als Target → falsche Richtung
                            $toDelete[] = $rel->id;
                        } else {
                            // Die andere Richtung ist falsch
                            $toDelete[] = $pairs[$key];
                        }
                        $domainRepairs['circular_removed']++;
                    } else {
                        $pairs[$key] = $rel->id;
                    }
                }

                // 2. Root darf nie target sein (innerhalb der Domain)
                $rootAsTarget = SeoUrlRelationship::where('type', 'parent_child')
                    ->where('target_url_id', $rootUrl->id)
                    ->whereIn('source_url_id', $domainUrlIds)
                    ->pluck('id')
                    ->all();

                $toDelete = array_unique(array_merge($toDelete, $rootAsTarget));
                $domainRepairs['reversed_fixed'] += count($rootAsTarget);

                if (! $dryRun && ! empty($toDelete)) {
                    SeoUrlRelationship::whereIn('id', $toDelete)->delete();
                }

                $domainRepairs['total_deleted'] = count($toDelete);

                if ($domainRepairs['circular_removed'] > 0 || $domainRepairs['reversed_fixed'] > 0) {
                    $repaired[] = $domainRepairs;
                }
            }

            return ToolResult::success([
                'dry_run' => $dryRun,
                'domains_checked' => $byDomain->count(),
                'repaired' => $repaired,
                'message' => empty($repaired)
                    ? 'Keine fehlerhaften Beziehungen gefunden.'
                    : count($repaired) . ' Domain(s) repariert.',
            ]);
        } catch (\Throwable $e) {
            return ToolResult::error('Fehler: ' . $e->getMessage(), 'EXECUTION_ERROR');
        }
    }
}
