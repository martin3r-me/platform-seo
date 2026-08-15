<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\UuidV7;

class SeoUrl extends Model
{
    use SoftDeletes;

    protected $table = 'seo_urls';

    protected $fillable = [
        'uuid',
        'team_id',
        'url',
        'url_hash',
        'domain',
        'path',
        'is_own',
        'status',
        'http_status',
        'priority',
        'last_crawled_at',
        'next_crawl_at',
        'keyword_count',
        'total_search_volume',
        'backlink_count',
        'referring_domains',
        'backlink_rank',
        'backlink_spam_score',
        'broken_backlinks',
        'backlinks_fetched_at',
        'llm_mentions',
        'llm_ai_search_volume',
        'llm_mentions_data',
        'llm_mentions_fetched_at',
        'visibility_score',
        'visitors_30d',
        'pageviews_30d',
        'organic_visitors_30d',
        'organic_pageviews_30d',
        'traffic_fetched_at',
        'plausible_enabled',
        'plausible_site_id',
        'redirect_url',
        'redirect_detected_at',
        'meta',
    ];

    protected $casts = [
        'uuid' => 'string',
        'is_own' => 'boolean',
        'http_status' => 'integer',
        'priority' => 'integer',
        'last_crawled_at' => 'datetime',
        'next_crawl_at' => 'datetime',
        'keyword_count' => 'integer',
        'total_search_volume' => 'integer',
        'backlink_count' => 'integer',
        'referring_domains' => 'integer',
        'backlink_rank' => 'integer',
        'backlink_spam_score' => 'integer',
        'broken_backlinks' => 'integer',
        'backlinks_fetched_at' => 'datetime',
        'llm_mentions' => 'integer',
        'llm_ai_search_volume' => 'integer',
        'llm_mentions_data' => 'array',
        'llm_mentions_fetched_at' => 'datetime',
        'visibility_score' => 'decimal:4',
        'visitors_30d' => 'integer',
        'organic_visitors_30d' => 'integer',
        'organic_pageviews_30d' => 'integer',
        'pageviews_30d' => 'integer',
        'traffic_fetched_at' => 'datetime',
        'plausible_enabled' => 'boolean',
        'redirect_detected_at' => 'datetime',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
            if (empty($model->url_hash)) {
                $model->url_hash = hash('sha256', $model->url);
            }
            if (empty($model->domain)) {
                $model->domain = parse_url($model->url, PHP_URL_HOST) ?? '';
            }
            if (is_null($model->path)) {
                $model->path = parse_url($model->url, PHP_URL_PATH) ?? '/';
            }
        });
    }

    /**
     * Kurzes, sprechendes Label für Listen/Sidebar: Pfad bei Unterseiten,
     * sonst die Domain (Root-URLs sollen nicht als „/" erscheinen).
     */
    public function getDisplayLabelAttribute(): string
    {
        return ($this->path && $this->path !== '/') ? $this->path : $this->domain;
    }

    public static function normalizeUrl(string $url): string
    {
        $url = trim($url);

        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }

        $parsed = parse_url($url);
        $scheme = strtolower($parsed['scheme'] ?? 'https');
        $host = strtolower($parsed['host'] ?? '');
        $host = preg_replace('/^www\./', '', $host);
        $path = $parsed['path'] ?? '/';
        $path = rtrim($path, '/') ?: '/';
        $query = isset($parsed['query']) ? '?'.$parsed['query'] : '';

        return $scheme.'://'.$host.$path.$query;
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', self::normalizeUrl($url));
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(SeoUrlRegistration::class, 'url_id');
    }

    public function keywords(): BelongsToMany
    {
        return $this->belongsToMany(SeoKeyword::class, 'seo_url_keywords', 'url_id', 'keyword_id')
            ->withPivot('position', 'previous_position', 'search_engine', 'device', 'position_updated_at')
            ->withTimestamps();
    }

    public function rankingHistory(): HasMany
    {
        return $this->hasMany(SeoRankingHistory::class, 'url_id')->orderByDesc('tracked_at');
    }

    public function backlinks(): HasMany
    {
        return $this->hasMany(SeoUrlBacklink::class, 'url_id');
    }

    public function gscData(): HasMany
    {
        return $this->hasMany(SeoUrlGscData::class, 'url_id');
    }

    public function traffic(): HasMany
    {
        return $this->hasMany(SeoUrlTraffic::class, 'url_id')->orderByDesc('date');
    }

    public function onPage(): HasOne
    {
        return $this->hasOne(SeoUrlOnPage::class, 'url_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SeoUrlSnapshot::class, 'url_id')->orderByDesc('snapshot_date');
    }

    public function signals(): HasMany
    {
        return $this->hasMany(SeoSignal::class, 'url_id');
    }

    public function pageChanges(): HasMany
    {
        return $this->hasMany(SeoPageChange::class, 'url_id');
    }

    public function sourceRelationships(): HasMany
    {
        return $this->hasMany(SeoUrlRelationship::class, 'source_url_id');
    }

    public function targetRelationships(): HasMany
    {
        return $this->hasMany(SeoUrlRelationship::class, 'target_url_id');
    }

    public function lists(): BelongsToMany
    {
        return $this->belongsToMany(SeoUrlList::class, 'seo_url_list_entries', 'url_id', 'list_id')
            ->withPivot('added_at');
    }

    public function getEffectiveRefreshInterval(int $baseIntervalHours): int
    {
        return (int) ($baseIntervalHours * (1 + (100 - $this->priority) / 100));
    }

    public function isDueForRefresh(int $baseIntervalHours): bool
    {
        if (! $this->last_crawled_at) {
            return true;
        }

        $effectiveInterval = $this->getEffectiveRefreshInterval($baseIntervalHours);

        return $this->last_crawled_at->addHours($effectiveInterval)->isPast();
    }

    /**
     * Check if a specific collector is due, using per-collector timestamps in meta.
     */
    public function isDueForCollector(string $collectorKey, int $baseIntervalHours): bool
    {
        $lastRan = $this->getCollectorTimestamp($collectorKey);
        if (! $lastRan) {
            return true;
        }

        $effectiveInterval = $this->getEffectiveRefreshInterval($baseIntervalHours);

        return $lastRan->addHours($effectiveInterval)->isPast();
    }

    public function getCollectorTimestamp(string $collectorKey): ?\Carbon\Carbon
    {
        $meta = $this->meta ?? [];
        $timestamp = $meta['collector_ran_at'][$collectorKey] ?? null;

        return $timestamp ? \Carbon\Carbon::parse($timestamp) : null;
    }

    public function setCollectorTimestamp(string $collectorKey): void
    {
        $meta = $this->meta ?? [];
        $meta['collector_ran_at'][$collectorKey] = now()->toIso8601String();
        $this->update(['meta' => $meta, 'last_crawled_at' => now()]);
    }

    /**
     * Per-URL-Status der Cluster-Discovery (running/completed/failed) samt
     * Ergebnis — in meta abgelegt, damit die UI pro URL (nicht team-weit) anzeigt.
     */
    public function markClustering(string $status, ?array $result = null): void
    {
        $meta = $this->meta ?? [];
        $meta['clustering'] = [
            'status' => $status,
            'result' => $result,
            'at' => now()->toIso8601String(),
        ];
        $this->update(['meta' => $meta]);
    }

    /**
     * Per-collector freshness map, mirroring the pipeline's due-logic.
     * Returns [collectorKey => ['last' => ?Carbon, 'due' => Carbon, 'overdue' => bool]].
     */
    public function collectorFreshness(): array
    {
        $intervals = config('seo.refresh_intervals', []);
        $out = [];

        foreach ($intervals as $key => $baseHours) {
            $last = $this->getCollectorTimestamp($key);
            $effective = $this->getEffectiveRefreshInterval((int) $baseHours);
            // No timestamp → the collector has never run for this URL and is due now.
            $due = $last ? $last->copy()->addHours($effective) : now();

            $out[$key] = [
                'last' => $last,
                'due' => $due,
                'overdue' => $due->isPast(),
            ];
        }

        return $out;
    }

    /**
     * Earliest upcoming (or overdue) refresh across all configured collectors.
     * Null when the URL has never been crawled and no collector has run.
     */
    public function getNextRefreshDueAtAttribute(): ?\Carbon\Carbon
    {
        $earliest = null;

        foreach ($this->collectorFreshness() as $info) {
            if ($earliest === null || $info['due']->lt($earliest)) {
                $earliest = $info['due'];
            }
        }

        if ($earliest === null) {
            return null;
        }

        // Never crawled at all → treat as "never", not "overdue".
        if (! $this->last_crawled_at && ! $this->hasAnyCollectorTimestamp()) {
            return null;
        }

        return $earliest;
    }

    /**
     * Coarse freshness state for UI: never | overdue | due_soon | fresh.
     */
    public function getFreshnessStatusAttribute(): string
    {
        $due = $this->next_refresh_due_at;

        if ($due === null) {
            return 'never';
        }

        if ($due->isPast()) {
            return 'overdue';
        }

        return $due->lte(now()->addDay()) ? 'due_soon' : 'fresh';
    }

    protected function hasAnyCollectorTimestamp(): bool
    {
        return ! empty($this->meta['collector_ran_at'] ?? []);
    }
}
