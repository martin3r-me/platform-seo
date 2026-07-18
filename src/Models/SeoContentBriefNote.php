<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoContentBriefNote extends Model
{
    protected $table = 'seo_content_brief_notes';

    protected $fillable = [
        'content_brief_id',
        'note_type',
        'content',
        'order',
        'team_id',
        'user_id',
    ];

    protected $casts = [
        'order' => 'integer',
    ];

    public function brief(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'content_brief_id');
    }
}
