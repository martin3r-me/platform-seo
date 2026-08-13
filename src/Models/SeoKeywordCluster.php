<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SeoKeywordCluster extends Model
{
    protected $table = 'seo_keyword_clusters';

    // Lebenszyklus — s. docs/CLUSTER-PLAYBOOK.md §3
    public const STATUS_CANDIDATE = 'candidate';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_MONITORED = 'monitored';
    public const STATUS_STALLED = 'stalled';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'color',
        'order',
        'status',
        'pillar_url_id',
        'keyword_count',
        'covered_keywords',
        'coverage_pct',
        'top3_count',
        'top10_count',
        'avg_position',
        'visibility',
        'clicks_30d',
        'visitors_30d',
        'health_score',
        'penetration',
        'measured_at',
        'status_changed_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
        'pillar_url_id' => 'integer',
        'keyword_count' => 'integer',
        'covered_keywords' => 'integer',
        'coverage_pct' => 'decimal:2',
        'top3_count' => 'integer',
        'top10_count' => 'integer',
        'avg_position' => 'decimal:2',
        'visibility' => 'decimal:4',
        'clicks_30d' => 'integer',
        'visitors_30d' => 'integer',
        'health_score' => 'integer',
        'penetration' => 'integer',
        'measured_at' => 'datetime',
        'status_changed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class, 'cluster_id');
    }

    /**
     * Die eine Ziel-URL (Pillar) eines aktiven Clusters.
     */
    public function pillarUrl(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'pillar_url_id');
    }

    /**
     * Status setzen und Zeitpunkt stempeln (Lifecycle-Übergang).
     */
    public function transitionTo(string $status): void
    {
        $this->update(['status' => $status, 'status_changed_at' => now()]);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(SeoClusterSnapshot::class, 'cluster_id')->orderByDesc('snapshot_date');
    }

    public function contentBriefs(): BelongsToMany
    {
        return $this->belongsToMany(
            SeoContentBrief::class,
            'seo_content_brief_clusters',
            'cluster_id',
            'content_brief_id',
        )->withPivot('role')->withTimestamps();
    }
}
