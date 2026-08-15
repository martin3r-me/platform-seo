<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * SERP-Features eines Keywords (neueste Momentaufnahme) — People-Also-Ask,
 * Related Searches, Featured Snippet, AI-Overview. Gefüllt beim SERP-Fetch
 * (SeoKeywordService::fetchRankings) aus dem ohnehin bezahlten Call.
 */
class SeoKeywordSerp extends Model
{
    protected $table = 'seo_keyword_serp';

    protected $fillable = [
        'keyword_id',
        'team_id',
        'item_types',
        'people_also_ask',
        'related_searches',
        'featured_snippet',
        'has_ai_overview',
        'ai_overview_references',
        'fetched_at',
    ];

    protected $casts = [
        'item_types' => 'array',
        'people_also_ask' => 'array',
        'related_searches' => 'array',
        'featured_snippet' => 'array',
        'has_ai_overview' => 'boolean',
        'ai_overview_references' => 'array',
        'fetched_at' => 'datetime',
    ];

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }
}
