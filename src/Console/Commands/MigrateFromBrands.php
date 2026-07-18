<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Seo\Models\SeoUrl;
use Platform\Seo\Services\SeoOrganizationLinker;
use Symfony\Component\Uid\UuidV7;

/**
 * Migriert die SEO-/Content-Daten aus dem Marken-Modul ins zentrale SEO-Modul.
 *
 * Entkoppelt: brand_id/seo_board_id fallen weg — stattdessen wird jede migrierte
 * Einheit an den Organisations-Knoten der Marke gehängt (D3). Bewusst NICHT
 * migriert: Budget-Logs sowie Positions-/Competitor-Historie (die Collectors
 * messen frisch neu). Dry-run ist Default.
 */
class MigrateFromBrands extends Command
{
    protected $signature = 'seo:migrate-from-brands
                            {--team= : Nur für ein bestimmtes Team}
                            {--dry-run : Nur anzeigen, nichts schreiben}
                            {--batch-size=100 : Batch-Größe}';

    protected $description = 'Migriert Cluster, Keywords und Content-Briefs aus dem Marken-Modul ins SEO-Modul';

    private array $clusterMap = [];   // brands cluster id → seo cluster id
    private array $keywordMap = [];   // brands keyword id → seo keyword id
    private array $briefMap = [];     // brands content_brief id → seo content_brief id
    private array $nodeByBrand = [];  // brand id → entity/node id

    private bool $dryRun;
    private int $batchSize;

    public function handle(SeoOrganizationLinker $linker): int
    {
        $this->dryRun = (bool) $this->option('dry-run');
        $this->batchSize = (int) $this->option('batch-size');

        if (! $this->sourceTablesExist()) {
            $this->error('Marken-Modul-Tabellen nicht gefunden (brands_seo_keyword_clusters, brands_seo_keywords).');
            return self::FAILURE;
        }

        $teamIds = $this->resolveTeamIds();
        if (empty($teamIds)) {
            $this->warn('Keine Teams mit Marken-SEO-Daten gefunden.');
            return self::SUCCESS;
        }

        $this->info('SEO-Migration aus dem Marken-Modul');
        $this->info('==================================');
        if ($this->dryRun) {
            $this->warn('[DRY-RUN] Es werden keine Daten geschrieben.');
        }

        foreach ($teamIds as $teamId) {
            $this->migrateTeam((int) $teamId, $linker);
        }

        $this->info('Migration abgeschlossen.');
        return self::SUCCESS;
    }

    private function sourceTablesExist(): bool
    {
        return Schema::hasTable('brands_seo_keyword_clusters')
            && Schema::hasTable('brands_seo_keywords');
    }

    private function resolveTeamIds(): array
    {
        if ($teamId = $this->option('team')) {
            return [(int) $teamId];
        }

        return DB::table('brands_seo_keywords')->distinct()->pluck('team_id')
            ->merge(DB::table('brands_seo_keyword_clusters')->distinct()->pluck('team_id'))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function migrateTeam(int $teamId, SeoOrganizationLinker $linker): void
    {
        $this->clusterMap = [];
        $this->keywordMap = [];
        $this->briefMap = [];
        $this->nodeByBrand = [];

        $this->newLine();
        $this->info("Team {$teamId}");

        $this->resolveBrandNodes($teamId, $linker);
        $this->migrateClusters($teamId, $linker);
        $this->migrateKeywords($teamId);
        $this->migrateContentBriefs($teamId, $linker);
    }

    /**
     * Baut brand_id → Knoten-Map über den bestehenden Dimension-Link-Layer.
     */
    private function resolveBrandNodes(int $teamId, SeoOrganizationLinker $linker): void
    {
        $brandIds = DB::table('brands_seo_boards')->where('team_id', $teamId)->pluck('brand_id')
            ->merge(
                Schema::hasTable('brands_content_brief_boards')
                    ? DB::table('brands_content_brief_boards')->where('team_id', $teamId)->pluck('brand_id')
                    : collect()
            )
            ->filter()->unique()->values()->all();

        if (empty($brandIds)) {
            return;
        }

        foreach ($linker->nodeIdsForMany('brands_brand', $brandIds) as $brandId => $nodeIds) {
            if (! empty($nodeIds)) {
                $this->nodeByBrand[$brandId] = $nodeIds[0];
            }
        }
    }

    private function nodeForBrand(?int $brandId): ?int
    {
        return $brandId !== null ? ($this->nodeByBrand[$brandId] ?? null) : null;
    }

    // =========================================================================
    // Clusters
    // =========================================================================

    private function migrateClusters(int $teamId, SeoOrganizationLinker $linker): void
    {
        $imported = 0;
        $skipped = 0;

        // Board → brand für die Knoten-Auflösung.
        $boardBrand = DB::table('brands_seo_boards')->where('team_id', $teamId)->pluck('brand_id', 'id');

        DB::table('brands_seo_keyword_clusters')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, $boardBrand, $linker, &$imported, &$skipped) {
                foreach ($rows as $row) {
                    $existing = DB::table('seo_keyword_clusters')
                        ->where('team_id', $teamId)->where('name', $row->name)->first();

                    if ($existing) {
                        $this->clusterMap[$row->id] = $existing->id;
                        $skipped++;
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->clusterMap[$row->id] = -1;
                        $imported++;
                        continue;
                    }

                    $newId = DB::table('seo_keyword_clusters')->insertGetId([
                        'uuid' => (string) UuidV7::generate(),
                        'team_id' => $teamId,
                        'name' => $row->name,
                        'color' => $row->color ?? null,
                        'order' => $row->order ?? 0,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    $this->clusterMap[$row->id] = $newId;

                    $node = $this->nodeForBrand($boardBrand[$row->seo_board_id] ?? null);
                    if ($node) {
                        $linker->setNode(SeoOrganizationLinker::ALIAS_CLUSTER, $newId, $node);
                    }

                    $imported++;
                }
            });

        $this->printStep('Cluster', $imported, $skipped);
    }

    // =========================================================================
    // Keywords (+ URL/Position → Pivot)
    // =========================================================================

    private function migrateKeywords(int $teamId): void
    {
        $imported = 0;
        $skipped = 0;
        $pivots = 0;

        DB::table('brands_seo_keywords')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$imported, &$skipped, &$pivots) {
                foreach ($rows as $row) {
                    $clusterId = $this->mappedCluster($row->keyword_cluster_id ?? null);

                    $existing = DB::table('seo_keywords')
                        ->where('team_id', $teamId)->where('keyword', $row->keyword)->first();

                    if ($existing) {
                        $this->keywordMap[$row->id] = $existing->id;
                        $skipped++;
                    } elseif ($this->dryRun) {
                        $this->keywordMap[$row->id] = -1;
                        $imported++;
                    } else {
                        $newId = DB::table('seo_keywords')->insertGetId([
                            'uuid' => (string) UuidV7::generate(),
                            'team_id' => $teamId,
                            'cluster_id' => $clusterId,
                            'keyword' => $row->keyword,
                            'search_volume' => $row->search_volume ?? null,
                            'cpc_cents' => $row->cpc_cents ?? null,
                            'keyword_difficulty' => $row->keyword_difficulty ?? null,
                            'search_intent' => $row->search_intent ?? null,
                            'dataforseo_raw' => $row->dataforseo_raw ?? null,
                            'last_fetched_at' => $row->last_fetched_at ?? null,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ]);
                        $this->keywordMap[$row->id] = $newId;
                        $imported++;
                    }

                    // URL + Position → seo_urls / seo_url_keywords Pivot
                    if (! $this->dryRun && ! empty($row->url) && ($row->position ?? null) !== null) {
                        $pivots += $this->migrateKeywordUrl($teamId, $this->keywordMap[$row->id], $row);
                    }
                }
            });

        $this->printStep('Keywords', $imported, $skipped, $pivots > 0 ? "{$pivots} URL-Pivots" : null);
    }

    private function migrateKeywordUrl(int $teamId, int $keywordId, object $row): int
    {
        if ($keywordId <= 0) {
            return 0;
        }

        $normalized = SeoUrl::normalizeUrl($row->url);
        $hash = SeoUrl::hashUrl($row->url);

        $url = SeoUrl::firstOrCreate(
            ['team_id' => $teamId, 'url_hash' => $hash],
            [
                'url' => $normalized,
                'domain' => parse_url($normalized, PHP_URL_HOST) ?? '',
                'path' => parse_url($normalized, PHP_URL_PATH) ?? '/',
                'is_own' => true,
                'status' => 'active',
                'priority' => config('seo.priority.own_url_default', 70),
            ],
        );

        DB::table('seo_url_keywords')->updateOrInsert(
            [
                'url_id' => $url->id,
                'keyword_id' => $keywordId,
                'search_engine' => 'google',
                'device' => 'desktop',
            ],
            [
                'position' => $row->position,
                'position_updated_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );

        return 1;
    }

    // =========================================================================
    // Content-Briefs (+ Sections / Notes / Links / Revisions / Cluster-Pivot)
    // =========================================================================

    private function migrateContentBriefs(int $teamId, SeoOrganizationLinker $linker): void
    {
        if (! Schema::hasTable('brands_content_brief_boards')) {
            $this->printStep('Content-Briefs', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        $imported = 0;
        $skipped = 0;

        DB::table('brands_content_brief_boards')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, $linker, &$imported, &$skipped) {
                foreach ($rows as $row) {
                    $existing = DB::table('seo_content_briefs')
                        ->where('team_id', $teamId)->where('name', $row->name)->first();

                    if ($existing) {
                        $this->briefMap[$row->id] = $existing->id;
                        $skipped++;
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->briefMap[$row->id] = -1;
                        $imported++;
                        continue;
                    }

                    $newId = DB::table('seo_content_briefs')->insertGetId([
                        'uuid' => (string) UuidV7::generate(),
                        'team_id' => $teamId,
                        'user_id' => $row->user_id ?? null,
                        'name' => $row->name,
                        'description' => $row->description ?? null,
                        'content_type' => $row->content_type ?? 'guide',
                        'search_intent' => $row->search_intent ?? 'informational',
                        'status' => $row->status ?? 'draft',
                        'target_slug' => $row->target_slug ?? null,
                        'target_url' => $row->target_url ?? null,
                        'target_word_count' => $row->target_word_count ?? null,
                        'order' => $row->order ?? 0,
                        'done' => $row->done ?? false,
                        'done_at' => $row->done_at ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    $this->briefMap[$row->id] = $newId;

                    $node = $this->nodeForBrand($row->brand_id ?? null);
                    if ($node) {
                        $linker->setNode(SeoOrganizationLinker::ALIAS_CONTENT_BRIEF, $newId, $node);
                    }

                    $imported++;
                }
            });

        $this->printStep('Content-Briefs', $imported, $skipped);

        if (! $this->dryRun) {
            $this->migrateBriefSections($teamId);
            $this->migrateBriefNotes($teamId);
            $this->migrateBriefLinks($teamId);
            $this->migrateBriefRevisions($teamId);
            $this->migrateBriefClusters($teamId);
        }
    }

    private function migrateBriefSections(int $teamId): void
    {
        if (! Schema::hasTable('brands_content_brief_sections')) {
            return;
        }

        $count = 0;
        DB::table('brands_content_brief_sections')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $briefId = $this->mappedBrief($row->content_brief_id);
                    if (! $briefId) {
                        continue;
                    }
                    $batch[] = [
                        'content_brief_id' => $briefId,
                        'order' => $row->order ?? 0,
                        'heading' => $row->heading,
                        'heading_level' => $row->heading_level ?? 'h2',
                        'description' => $row->description ?? null,
                        'target_keywords' => $row->target_keywords ?? null,
                        'notes' => $row->notes ?? null,
                        'team_id' => $teamId,
                        'user_id' => $row->user_id ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    $count++;
                }
                if (! empty($batch)) {
                    DB::table('seo_content_brief_sections')->insert($batch);
                }
            });

        $this->printStep('  Sections', $count);
    }

    private function migrateBriefNotes(int $teamId): void
    {
        if (! Schema::hasTable('brands_content_brief_notes')) {
            return;
        }

        $count = 0;
        DB::table('brands_content_brief_notes')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $briefId = $this->mappedBrief($row->content_brief_id);
                    if (! $briefId) {
                        continue;
                    }
                    $batch[] = [
                        'content_brief_id' => $briefId,
                        'note_type' => $row->note_type,
                        'content' => $row->content,
                        'order' => $row->order ?? 0,
                        'team_id' => $teamId,
                        'user_id' => $row->user_id ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    $count++;
                }
                if (! empty($batch)) {
                    DB::table('seo_content_brief_notes')->insert($batch);
                }
            });

        $this->printStep('  Notes', $count);
    }

    private function migrateBriefLinks(int $teamId): void
    {
        if (! Schema::hasTable('brands_content_brief_links')) {
            return;
        }

        $count = 0;
        DB::table('brands_content_brief_links')->where('team_id', $teamId)->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $source = $this->mappedBrief($row->source_content_brief_id);
                    $target = $this->mappedBrief($row->target_content_brief_id);
                    if (! $source || ! $target) {
                        continue;
                    }
                    $batch[] = [
                        'source_content_brief_id' => $source,
                        'target_content_brief_id' => $target,
                        'link_type' => $row->link_type,
                        'anchor_hint' => $row->anchor_hint ?? null,
                        'team_id' => $teamId,
                        'user_id' => $row->user_id ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    $count++;
                }
                if (! empty($batch)) {
                    DB::table('seo_content_brief_links')->insertOrIgnore($batch);
                }
            });

        $this->printStep('  Links', $count);
    }

    private function migrateBriefRevisions(int $teamId): void
    {
        if (! Schema::hasTable('brands_content_brief_revisions')) {
            return;
        }

        $count = 0;
        DB::table('brands_content_brief_revisions')->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $briefId = $this->mappedBrief($row->content_brief_board_id);
                    if (! $briefId) {
                        continue;
                    }
                    $batch[] = [
                        'uuid' => (string) UuidV7::generate(),
                        'content_brief_id' => $briefId,
                        'revision_type' => $row->revision_type ?? 'optimization',
                        'summary' => $row->summary,
                        'metrics_before' => $row->metrics_before ?? null,
                        'metrics_after' => $row->metrics_after ?? null,
                        'changes' => $row->changes ?? null,
                        'user_id' => $row->user_id ?? null,
                        'revised_at' => $row->revised_at,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];
                    $count++;
                }
                if (! empty($batch)) {
                    DB::table('seo_content_brief_revisions')->insert($batch);
                }
            });

        $this->printStep('  Revisions', $count);
    }

    private function migrateBriefClusters(int $teamId): void
    {
        if (! Schema::hasTable('brands_content_brief_keyword_clusters')) {
            return;
        }

        $count = 0;
        DB::table('brands_content_brief_keyword_clusters')->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use (&$count) {
                $batch = [];
                foreach ($rows as $row) {
                    $briefId = $this->mappedBrief($row->content_brief_id);
                    $clusterId = $this->mappedCluster($row->seo_keyword_cluster_id);
                    if (! $briefId || ! $clusterId) {
                        continue;
                    }
                    $batch[] = [
                        'content_brief_id' => $briefId,
                        'cluster_id' => $clusterId,
                        'role' => $row->role ?? 'primary',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                    $count++;
                }
                if (! empty($batch)) {
                    DB::table('seo_content_brief_clusters')->insertOrIgnore($batch);
                }
            });

        $this->printStep('  Brief↔Cluster', $count);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function mappedCluster(?int $brandsClusterId): ?int
    {
        if ($brandsClusterId === null) {
            return null;
        }
        $mapped = $this->clusterMap[$brandsClusterId] ?? null;

        return ($mapped && $mapped > 0) ? $mapped : null;
    }

    private function mappedBrief(?int $brandsBriefId): ?int
    {
        if ($brandsBriefId === null) {
            return null;
        }
        $mapped = $this->briefMap[$brandsBriefId] ?? null;

        return ($mapped && $mapped > 0) ? $mapped : null;
    }

    private function printStep(string $label, int $imported, int $skipped = 0, ?string $note = null): void
    {
        $parts = ["{$imported} importiert"];
        if ($skipped > 0) {
            $parts[] = "{$skipped} übersprungen";
        }
        if ($note) {
            $parts[] = $note;
        }
        $suffix = $this->dryRun ? ' [DRY-RUN]' : '';
        $this->line("  {$label}: ".implode(', ', $parts).$suffix);
    }
}
