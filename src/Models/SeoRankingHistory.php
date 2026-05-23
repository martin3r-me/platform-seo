<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoRankingHistory extends Model
{
    protected $table = 'seo_ranking_history';

    protected $fillable = [
        'url_id',
        'keyword_id',
        'position',
        'previous_position',
        'search_engine',
        'device',
        'serp_features',
        'tracked_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'previous_position' => 'integer',
        'serp_features' => 'array',
        'tracked_at' => 'date',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }

    public function getPositionDeltaAttribute(): ?int
    {
        if ($this->previous_position === null || $this->position === null) {
            return null;
        }

        return $this->previous_position - $this->position;
    }
}
