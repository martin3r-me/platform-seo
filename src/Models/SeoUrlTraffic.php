<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlTraffic extends Model
{
    protected $table = 'seo_url_traffic';

    protected $fillable = [
        'url_id',
        'date',
        'source',
        'visitors',
        'pageviews',
        'bounce_rate',
        'visit_duration',
    ];

    protected $casts = [
        'date' => 'date',
        'visitors' => 'integer',
        'pageviews' => 'integer',
        'bounce_rate' => 'decimal:2',
        'visit_duration' => 'integer',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
