<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Contracts\HasDisplayName;
use Symfony\Component\Uid\UuidV7;

class SeoKeyword extends Model implements HasDisplayName
{
    protected $table = 'seo_keywords';

    protected $fillable = [
        'uuid',
        'team_id',
        'cluster_id',
        'keyword',
        'search_volume',
        'cpc_cents',
        'competition',
        'keyword_difficulty',
        'competitor_tracking_depth',
        'search_intent',
        'topic',
        'monthly_volumes',
        'peak_month',
        'seasonality_index',
        'google_trends_data',
        'trends_average_interest',
        'trends_peak_interest',
        'trends_fetched_at',
        'dataforseo_raw',
        'last_fetched_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'search_volume' => 'integer',
        'cpc_cents' => 'integer',
        'competition' => 'decimal:3',
        'keyword_difficulty' => 'integer',
        'competitor_tracking_depth' => 'integer',
        'monthly_volumes' => 'array',
        'peak_month' => 'integer',
        'seasonality_index' => 'decimal:2',
        'google_trends_data' => 'array',
        'trends_average_interest' => 'integer',
        'trends_peak_interest' => 'integer',
        'trends_fetched_at' => 'datetime',
        'dataforseo_raw' => 'array',
        'last_fetched_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordCluster::class, 'cluster_id');
    }

    public function positions(): HasMany
    {
        return $this->hasMany(SeoKeywordPosition::class, 'keyword_id')->orderByDesc('tracked_at');
    }

    public function competitors(): HasMany
    {
        return $this->hasMany(SeoKeywordCompetitor::class, 'keyword_id')->orderByDesc('tracked_at');
    }

    /** @deprecated Use SeoUrlRegistration instead */
    public function contexts(): HasMany
    {
        return $this->hasMany(SeoKeywordContext::class, 'keyword_id')->orderByDesc('created_at');
    }

    /**
     * URLs that rank for this keyword (new URL-centric relationship).
     */
    public function urls(): BelongsToMany
    {
        return $this->belongsToMany(SeoUrl::class, 'seo_url_keywords', 'keyword_id', 'url_id')
            ->withPivot('position', 'previous_position', 'search_engine', 'device', 'position_updated_at')
            ->withTimestamps();
    }

    /**
     * Ranking history entries for this keyword.
     */
    public function rankingHistory(): HasMany
    {
        return $this->hasMany(SeoRankingHistory::class, 'keyword_id')->orderByDesc('tracked_at');
    }

    /**
     * GSC data entries for this keyword.
     */
    public function gscData(): HasMany
    {
        return $this->hasMany(SeoUrlGscData::class, 'keyword_id');
    }

    // =========================================================================
    // Accessors
    // =========================================================================

    public function getCpcEuroAttribute(): ?float
    {
        return $this->cpc_cents !== null ? $this->cpc_cents / 100 : null;
    }

    public function getMedianVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || count($mv) < 2) {
            return $this->search_volume;
        }

        $values = array_values($mv);
        sort($values);
        $count = count($values);
        $mid = intdiv($count, 2);

        return $count % 2 === 0
            ? (int) round(($values[$mid - 1] + $values[$mid]) / 2)
            : $values[$mid];
    }

    public function getMinVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || empty($mv)) {
            return null;
        }
        return (int) min($mv);
    }

    public function getMaxVolumeAttribute(): ?int
    {
        $mv = $this->monthly_volumes;
        if (!is_array($mv) || empty($mv)) {
            return null;
        }
        return (int) max($mv);
    }

    public function getTrendsSparklineAttribute(): ?array
    {
        $data = $this->google_trends_data;
        if (!is_array($data) || empty($data)) {
            return null;
        }

        return array_values(array_filter(
            array_map(fn($point) => $point['value'] ?? null, $data),
            fn($v) => $v !== null
        ));
    }

    public function getCompetitorGapAttribute(): bool
    {
        $hasCompetitors = $this->competitors()->exists();

        if (!$hasCompetitors) {
            return false;
        }

        // Check via pivot if available, otherwise fall back
        $publishedUrl = $this->pivot->target_url ?? null;
        $position = $this->pivot->position ?? null;

        return empty($publishedUrl) || $position === null;
    }

    public function getDisplayName(): ?string
    {
        return $this->keyword;
    }
}
