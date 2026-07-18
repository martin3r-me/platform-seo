<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SeoKeywordCluster extends Model
{
    protected $table = 'seo_keyword_clusters';

    protected $fillable = [
        'uuid',
        'team_id',
        'name',
        'description',
        'color',
        'order',
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
        'measured_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
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
        'measured_at' => 'datetime',
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

    public function snapshots(): HasMany
    {
        return $this->hasMany(SeoClusterSnapshot::class, 'cluster_id')->orderByDesc('snapshot_date');
    }
}
