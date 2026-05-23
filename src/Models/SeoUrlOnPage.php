<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlOnPage extends Model
{
    protected $table = 'seo_url_on_page';

    protected $fillable = [
        'url_id',
        'title',
        'meta_description',
        'h1',
        'headings',
        'word_count',
        'page_speed_score',
        'mobile_score',
        'structured_data_types',
        'issues',
        'overall_score',
        'analyzed_at',
    ];

    protected $casts = [
        'headings' => 'array',
        'word_count' => 'integer',
        'page_speed_score' => 'integer',
        'mobile_score' => 'integer',
        'structured_data_types' => 'array',
        'issues' => 'array',
        'overall_score' => 'integer',
        'analyzed_at' => 'datetime',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
