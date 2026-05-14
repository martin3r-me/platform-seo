<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordPosition extends Model
{
    protected $table = 'seo_keyword_positions';

    protected $fillable = [
        'keyword_id',
        'position',
        'previous_position',
        'ranked_url',
        'serp_features',
        'search_engine',
        'device',
        'location',
        'tracked_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'previous_position' => 'integer',
        'serp_features' => 'array',
        'tracked_at' => 'date',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }

    public function getPositionDeltaAttribute(): ?int
    {
        if ($this->previous_position === null) {
            return null;
        }

        return $this->previous_position - $this->position;
    }
}
