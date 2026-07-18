<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoContentBriefSection extends Model
{
    protected $table = 'seo_content_brief_sections';

    protected $fillable = [
        'content_brief_id',
        'order',
        'heading',
        'heading_level',
        'description',
        'target_keywords',
        'notes',
        'team_id',
        'user_id',
    ];

    protected $casts = [
        'order' => 'integer',
        'target_keywords' => 'array',
    ];

    public function brief(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'content_brief_id');
    }
}
