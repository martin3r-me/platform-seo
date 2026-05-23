<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoKeywordCompetitor extends Model
{
    protected $table = 'seo_keyword_competitors';

    protected $fillable = [
        'keyword_id',
        'project_id',
        'domain',
        'url',
        'position',
        'tracked_at',
    ];

    protected $casts = [
        'position' => 'integer',
        'tracked_at' => 'date',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(\Platform\Seo\Models\SeoProject::class, 'project_id');
    }
}
