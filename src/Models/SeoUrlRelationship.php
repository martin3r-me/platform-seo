<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlRelationship extends Model
{
    protected $table = 'seo_url_relationships';

    protected $fillable = [
        'team_id',
        'source_url_id',
        'target_url_id',
        'type',
        'strength',
        'meta',
        'detected_at',
    ];

    protected $casts = [
        'strength' => 'integer',
        'meta' => 'array',
        'detected_at' => 'datetime',
    ];

    public function sourceUrl(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'source_url_id');
    }

    public function targetUrl(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'target_url_id');
    }
}
