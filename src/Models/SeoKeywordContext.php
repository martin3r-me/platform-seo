<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordContext extends Model
{
    protected $table = 'seo_keyword_contexts';

    protected $fillable = [
        'keyword_id',
        'context_type',
        'context_id',
        'label',
        'url',
        'meta',
    ];

    protected $casts = [
        'context_id' => 'integer',
        'meta' => 'array',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }
}
