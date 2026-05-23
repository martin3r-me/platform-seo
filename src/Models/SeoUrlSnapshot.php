<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlSnapshot extends Model
{
    protected $table = 'seo_url_snapshots';

    protected $fillable = [
        'url_id',
        'snapshot_date',
        'keyword_count',
        'total_search_volume',
        'visibility_score',
        'backlink_count',
        'on_page_score',
        'top_keywords',
        'position_distribution',
    ];

    protected $casts = [
        'snapshot_date' => 'date',
        'keyword_count' => 'integer',
        'total_search_volume' => 'integer',
        'visibility_score' => 'decimal:4',
        'backlink_count' => 'integer',
        'on_page_score' => 'integer',
        'top_keywords' => 'array',
        'position_distribution' => 'array',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
