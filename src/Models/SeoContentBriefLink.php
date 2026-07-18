<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoContentBriefLink extends Model
{
    protected $table = 'seo_content_brief_links';

    protected $fillable = [
        'source_content_brief_id',
        'target_content_brief_id',
        'link_type',
        'anchor_hint',
        'team_id',
        'user_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'source_content_brief_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'target_content_brief_id');
    }
}
