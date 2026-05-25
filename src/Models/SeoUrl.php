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
        'visibility_score',
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
        'visibility_score' => 'decimal:4',
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
}
