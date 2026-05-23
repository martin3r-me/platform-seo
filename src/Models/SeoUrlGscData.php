<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlGscData extends Model
{
    protected $table = 'seo_url_gsc_data';

    protected $fillable = [
        'url_id',
        'keyword_id',
        'date',
        'impressions',
        'clicks',
        'ctr',
        'avg_position',
        'device',
        'country',
    ];

    protected $casts = [
        'date' => 'date',
        'impressions' => 'integer',
        'clicks' => 'integer',
        'ctr' => 'decimal:4',
        'avg_position' => 'decimal:2',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }
}
