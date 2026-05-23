<?php

namespace Platform\Seo\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Platform\Seo\Models\SeoKeyword;
use Platform\Seo\Models\SeoTeamSettings;
use Platform\Seo\Models\SeoUrl;
use Symfony\Component\Uid\UuidV7;

class MigrateFromSyltjunkie extends Command
{
    protected $signature = 'seo:migrate-from-syltjunkie
                            {--team= : Nur für ein bestimmtes Team}
                            {--dry-run : Nur anzeigen, nicht importieren}
                            {--batch-size=100 : Batch-Größe}';

    protected $description = 'Migriert SEO-Daten aus dem Syltjunkie-Modul ins zentrale SEO-Modul';

    private array $keywordMap = [];
    private array $urlMap = [];
    private int $batchSize;
    private bool $dryRun;

    public function handle(): int
    {
        $this->batchSize = (int) $this->option('batch-size');
        $this->dryRun = (bool) $this->option('dry-run');

        if (! $this->sourceTablesExist()) {
            $this->error('Syltjunkie-Tabellen nicht gefunden. Ist das Syltjunkie-Modul installiert?');
            return self::FAILURE;
        }

        $teams = $this->resolveTeams();
        if ($teams->isEmpty()) {
            $this->error('Keine Teams gefunden.');
            return self::FAILURE;
        }

        $this->info('SEO-Migration von Syltjunkie');
        $this->info('============================');

        if ($this->dryRun) {
            $this->warn('[DRY-RUN] Keine Daten werden geschrieben.');
            $this->newLine();
        }

        foreach ($teams as $team) {
            $this->migrateTeam($team);
        }

        $this->info('Migration abgeschlossen.');
        return self::SUCCESS;
    }

    private function sourceTablesExist(): bool
    {
        return Schema::hasTable('sj_keywords')
            && Schema::hasTable('sj_entity_urls');
    }

    private function resolveTeams()
    {
        $query = DB::table('teams');

        if ($teamId = $this->option('team')) {
            $query->where('id', $teamId);
        } else {
            // Only teams that have Syltjunkie data
            $teamIds = DB::table('sj_keywords')->distinct()->pluck('team_id')
                ->merge(DB::table('sj_entity_urls')->distinct()->pluck('team_id'))
                ->unique();
            $query->whereIn('id', $teamIds);
        }

        return $query->get();
    }

    private function migrateTeam(object $team): void
    {
        $this->keywordMap = [];
        $this->urlMap = [];

        $settings = SeoTeamSettings::firstOrCreate(
            ['team_id' => $team->id],
            [
                'budget_limit_cents' => config('seo.budget.default_limit_cents', 5000),
                'refresh_interval_hours' => 168,
                'location_code' => 2276,
                'language_code' => 1001,
            ]
        );

        $this->newLine();
        $this->info("Team: {$team->name} (ID: {$team->id})");
        $this->info("Settings ID: {$settings->id}");
        $this->newLine();

        $this->migrateKeywords($team->id);
        $this->migrateUrls($team->id);
        $this->migrateRankings($team->id);
        $this->migrateUrlSnapshots($team->id);
        $this->migrateOnPage($team->id);
        $this->migratePageChanges($team->id);
        $this->migrateSignals($team->id);
    }

    // =========================================================================
    // Step 1: Keywords
    // =========================================================================

    private function migrateKeywords(int $teamId): void
    {
        $imported = 0;
        $skipped = 0;

        DB::table('sj_keywords')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$imported, &$skipped) {
                foreach ($rows as $row) {
                    $existing = DB::table('seo_keywords')
                        ->where('team_id', $teamId)
                        ->where('keyword', $row->keyword)
                        ->first();

                    if ($existing) {
                        $this->keywordMap[$row->id] = $existing->id;
                        $skipped++;
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->keywordMap[$row->id] = -1;
                        $imported++;
                        continue;
                    }

                    $newId = DB::table('seo_keywords')->insertGetId([
                        'uuid' => UuidV7::generate(),
                        'team_id' => $teamId,
                        'cluster_id' => null,
                        'keyword' => $row->keyword,
                        'search_volume' => $row->search_volume,
                        'cpc_cents' => $row->cpc_cents,
                        'competition' => $row->competition,
                        'keyword_difficulty' => $row->keyword_difficulty,
                        'search_intent' => $row->search_intent,
                        'topic' => $row->topic,
                        'monthly_volumes' => $row->monthly_volumes,
                        'peak_month' => $row->peak_month,
                        'seasonality_index' => $row->seasonality_index,
                        'google_trends_data' => $row->google_trends_data,
                        'trends_average_interest' => $row->trends_average_interest,
                        'trends_peak_interest' => $row->trends_peak_interest,
                        'trends_fetched_at' => $row->trends_fetched_at,
                        'last_fetched_at' => $row->last_fetched_at,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    $this->keywordMap[$row->id] = $newId;
                    $imported++;
                }
            });

        $this->printStep(1, 'Keywords', $imported, $skipped);
    }

    // =========================================================================
    // Step 2: URLs
    // =========================================================================

    private function migrateUrls(int $teamId): void
    {
        $imported = 0;
        $skipped = 0;

        DB::table('sj_entity_urls')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$imported, &$skipped) {
                foreach ($rows as $row) {
                    $normalizedUrl = SeoUrl::normalizeUrl($row->url);
                    $urlHash = hash('sha256', $normalizedUrl);

                    $existing = DB::table('seo_urls')
                        ->where('team_id', $teamId)
                        ->where('url_hash', $urlHash)
                        ->first();

                    if ($existing) {
                        $this->urlMap[$row->id] = $existing->id;
                        $skipped++;
                        continue;
                    }

                    if ($this->dryRun) {
                        $this->urlMap[$row->id] = -1;
                        $imported++;
                        continue;
                    }

                    $meta = array_filter([
                        'entity_id' => $row->entity_id ?? null,
                        'platform' => $row->platform ?? null,
                        'is_primary' => $row->is_primary ?? null,
                        'google_place_id' => $row->google_place_id ?? null,
                    ], fn ($v) => $v !== null);

                    $newId = DB::table('seo_urls')->insertGetId([
                        'uuid' => UuidV7::generate(),
                        'team_id' => $teamId,
                        'url' => $normalizedUrl,
                        'url_hash' => $urlHash,
                        'domain' => parse_url($normalizedUrl, PHP_URL_HOST) ?? '',
                        'path' => parse_url($normalizedUrl, PHP_URL_PATH) ?? '/',
                        'is_own' => true,
                        'status' => ($row->is_active ?? true) ? 'active' : 'deleted',
                        'last_crawled_at' => $row->last_checked_at ?? null,
                        'meta' => ! empty($meta) ? json_encode($meta) : null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ]);

                    $this->urlMap[$row->id] = $newId;
                    $imported++;
                }
            });

        $this->printStep(2, 'URLs', $imported, $skipped);
    }

    // =========================================================================
    // Step 3: Rankings
    // =========================================================================

    private function migrateRankings(int $teamId): void
    {
        $imported = 0;

        if (! Schema::hasTable('sj_keyword_rankings')) {
            $this->printStep(3, 'Rankings', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        DB::table('sj_keyword_rankings')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$imported) {
                $positionBatch = [];
                $historyBatch = [];

                foreach ($rows as $row) {
                    $keywordId = $this->keywordMap[$row->keyword_id] ?? null;
                    if (! $keywordId || $keywordId === -1) {
                        continue;
                    }

                    $urlId = isset($row->entity_url_id) ? ($this->urlMap[$row->entity_url_id] ?? null) : null;

                    $positionBatch[] = [
                        'keyword_id' => $keywordId,
                        'team_id' => $teamId,
                        'position' => $row->position,
                        'previous_position' => $row->previous_position ?? null,
                        'ranked_url' => $row->ranked_url ?? null,
                        'serp_features' => $row->serp_features ?? null,
                        'search_engine' => $row->search_engine ?? 'google',
                        'device' => $row->device ?? 'desktop',
                        'tracked_at' => $row->captured_at,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];

                    if ($urlId && $urlId !== -1) {
                        $historyBatch[] = [
                            'url_id' => $urlId,
                            'keyword_id' => $keywordId,
                            'position' => $row->position,
                            'previous_position' => $row->previous_position ?? null,
                            'search_engine' => $row->search_engine ?? 'google',
                            'device' => $row->device ?? 'desktop',
                            'serp_features' => $row->serp_features ?? null,
                            'tracked_at' => $row->captured_at,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => $row->updated_at ?? now(),
                        ];
                    }

                    $imported++;
                }

                if (! $this->dryRun) {
                    if (! empty($positionBatch)) {
                        DB::table('seo_keyword_positions')->insert($positionBatch);
                    }
                    if (! empty($historyBatch)) {
                        DB::table('seo_ranking_history')->insert($historyBatch);
                    }
                }
            });

        $this->printStep(3, 'Rankings', $imported);
    }

    // =========================================================================
    // Step 4: URL Snapshots
    // =========================================================================

    private function migrateUrlSnapshots(int $teamId): void
    {
        $imported = 0;

        if (! Schema::hasTable('sj_url_snapshots')) {
            $this->printStep(4, 'URL-Snapshots', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        DB::table('sj_url_snapshots')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use (&$imported) {
                $batch = [];

                foreach ($rows as $row) {
                    $urlId = $this->urlMap[$row->entity_url_id] ?? null;
                    if (! $urlId || $urlId === -1) {
                        continue;
                    }

                    $batch[] = [
                        'url_id' => $urlId,
                        'snapshot_date' => $row->captured_at,
                        'keyword_count' => $row->keywords_count ?? 0,
                        'total_search_volume' => 0,
                        'visibility_score' => 0,
                        'backlink_count' => $row->backlinks_count ?? 0,
                        'on_page_score' => null,
                        'top_keywords' => $row->keywords ?? null,
                        'position_distribution' => null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];

                    $imported++;
                }

                if (! $this->dryRun && ! empty($batch)) {
                    DB::table('seo_url_snapshots')->insert($batch);
                }
            });

        $this->printStep(4, 'URL-Snapshots', $imported);
    }

    // =========================================================================
    // Step 5: On-Page (only latest per URL)
    // =========================================================================

    private function migrateOnPage(int $teamId): void
    {
        $imported = 0;

        if (! Schema::hasTable('sj_page_snapshots')) {
            $this->printStep(5, 'On-Page', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        // Get only the latest snapshot per entity_url_id
        $latestSnapshots = DB::table('sj_page_snapshots')
            ->select('sj_page_snapshots.*')
            ->joinSub(
                DB::table('sj_page_snapshots')
                    ->select('entity_url_id', DB::raw('MAX(id) as max_id'))
                    ->where('team_id', $teamId)
                    ->groupBy('entity_url_id'),
                'latest',
                fn ($join) => $join->on('sj_page_snapshots.id', '=', 'latest.max_id')
            )
            ->get();

        foreach ($latestSnapshots as $row) {
            $urlId = $this->urlMap[$row->entity_url_id] ?? null;
            if (! $urlId || $urlId === -1) {
                continue;
            }

            // Check if on-page data already exists for this URL
            $exists = DB::table('seo_url_on_page')->where('url_id', $urlId)->exists();
            if ($exists) {
                continue;
            }

            // Extract h1 from headings JSON
            $h1 = null;
            $headings = is_string($row->headings) ? json_decode($row->headings, true) : $row->headings;
            if (is_array($headings)) {
                foreach ($headings as $heading) {
                    if (isset($heading['level']) && $heading['level'] === 1 && isset($heading['text'])) {
                        $h1 = $heading['text'];
                        break;
                    }
                    if (isset($heading['h1'])) {
                        $h1 = $heading['h1'];
                        break;
                    }
                }
            }

            // Convert decimal score to tinyint (0-100)
            $overallScore = null;
            if ($row->onpage_score !== null) {
                $score = (float) $row->onpage_score;
                $overallScore = ($score <= 1) ? (int) round($score * 100) : (int) min(100, round($score));
            }

            if (! $this->dryRun) {
                DB::table('seo_url_on_page')->insert([
                    'url_id' => $urlId,
                    'title' => $row->title ?? null,
                    'meta_description' => $row->meta_description ? mb_substr($row->meta_description, 0, 1000) : null,
                    'h1' => $h1,
                    'headings' => is_string($row->headings) ? $row->headings : json_encode($headings),
                    'word_count' => $row->word_count ?? null,
                    'overall_score' => $overallScore,
                    'analyzed_at' => $row->captured_at,
                    'created_at' => $row->created_at ?? now(),
                    'updated_at' => $row->updated_at ?? now(),
                ]);
            }

            $imported++;
        }

        $this->printStep(5, 'On-Page', $imported, 0, 'nur letzte pro URL');
    }

    // =========================================================================
    // Step 6: Page Changes
    // =========================================================================

    private function migratePageChanges(int $teamId): void
    {
        $imported = 0;

        if (! Schema::hasTable('sj_page_changes')) {
            $this->printStep(6, 'Page Changes', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        DB::table('sj_page_changes')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, &$imported) {
                $batch = [];

                foreach ($rows as $row) {
                    $urlId = $this->urlMap[$row->entity_url_id] ?? null;
                    if (! $urlId || $urlId === -1) {
                        continue;
                    }

                    $batch[] = [
                        'uuid' => UuidV7::generate(),
                        'team_id' => $teamId,
                        'url_id' => $urlId,
                        'detected_at' => $row->detected_at,
                        'change_type' => $row->change_type,
                        'severity' => $row->severity ?? 'minor',
                        'old_value' => $row->old_value ?? null,
                        'new_value' => $row->new_value ?? null,
                        'delta' => $row->delta ?? null,
                        'context' => $row->context ?? null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];

                    $imported++;
                }

                if (! $this->dryRun && ! empty($batch)) {
                    DB::table('seo_page_changes')->insert($batch);
                }
            });

        $this->printStep(6, 'Page Changes', $imported);
    }

    // =========================================================================
    // Step 7: Signals
    // =========================================================================

    private function migrateSignals(int $teamId): void
    {
        $imported = 0;

        if (! Schema::hasTable('sj_trend_signals')) {
            $this->printStep(7, 'Signals', 0, 0, 'Tabelle nicht vorhanden');
            return;
        }

        $severityMap = [
            'info' => 'info',
            'watch' => 'warning',
            'action' => 'critical',
        ];

        DB::table('sj_trend_signals')
            ->where('team_id', $teamId)
            ->orderBy('id')
            ->chunk($this->batchSize, function ($rows) use ($teamId, $severityMap, &$imported) {
                $batch = [];

                foreach ($rows as $row) {
                    $keywordId = isset($row->keyword_id) ? ($this->keywordMap[$row->keyword_id] ?? null) : null;
                    $urlId = isset($row->entity_url_id) ? ($this->urlMap[$row->entity_url_id] ?? null) : null;

                    // Skip if remapped to dry-run placeholder
                    if ($keywordId === -1) {
                        $keywordId = null;
                    }
                    if ($urlId === -1) {
                        $urlId = null;
                    }

                    // Pack entity_id into context
                    $context = is_string($row->context) ? json_decode($row->context, true) : ($row->context ?? []);
                    if (! is_array($context)) {
                        $context = [];
                    }
                    if (isset($row->entity_id)) {
                        $context['entity_id'] = $row->entity_id;
                    }

                    $batch[] = [
                        'uuid' => UuidV7::generate(),
                        'team_id' => $teamId,
                        'keyword_id' => $keywordId,
                        'url_id' => $urlId,
                        'signal_type' => $row->signal_type,
                        'severity' => $severityMap[$row->severity] ?? $row->severity,
                        'title' => $row->title,
                        'description' => $row->description ?? null,
                        'metric_before' => $row->metric_before ?? null,
                        'metric_after' => $row->metric_after ?? null,
                        'metric_delta' => $row->metric_delta ?? null,
                        'detected_at' => $row->detected_at,
                        'status' => $row->status ?? 'new',
                        'context' => ! empty($context) ? json_encode($context) : null,
                        'created_at' => $row->created_at ?? now(),
                        'updated_at' => $row->updated_at ?? now(),
                    ];

                    $imported++;
                }

                if (! $this->dryRun && ! empty($batch)) {
                    DB::table('seo_signals')->insert($batch);
                }
            });

        $this->printStep(7, 'Signals', $imported);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function printStep(int $step, string $label, int $imported, int $skipped = 0, ?string $note = null): void
    {
        $parts = ["{$imported} importiert"];

        if ($skipped > 0) {
            $parts[] = "{$skipped} übersprungen (Duplikate)";
        }

        if ($note) {
            $parts[] = $note;
        }

        $suffix = $this->dryRun ? ' [DRY-RUN]' : '';
        $detail = implode(', ', $parts);

        $this->line("[{$step}/7] {$label} " . str_pad('.', max(1, 20 - mb_strlen($label)), '.') . " {$detail}{$suffix}");
    }
}
