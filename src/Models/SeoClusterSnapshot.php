<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoClusterSnapshot extends Model
{
    protected $table = 'seo_cluster_snapshots';

    protected $fillable = [
        'cluster_id',
        'snapshot_date',
        'keyword_count',
        'covered_keywords',
        'coverage_pct',
        'top3_count',
        'top10_count',
        'avg_position',
        'visibility',
        'clicks',
        'visitors',
        'health_score',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'keyword_count' => 'integer',
        'covered_keywords' => 'integer',
        'coverage_pct' => 'decimal:2',
        'top3_count' => 'integer',
        'top10_count' => 'integer',
        'avg_position' => 'decimal:2',
        'visibility' => 'decimal:4',
        'clicks' => 'integer',
        'visitors' => 'integer',
        'health_score' => 'integer',
    ];

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordCluster::class, 'cluster_id');
    }
}
