<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Präsenz — die Multi-Surface-Messung (v2, docs/NORDSTERN-v2.md): sind wir da /
 * zitiert je Surface (klassische Suche · AI-Overview · ChatGPT/Perplexity · KG),
 * mit share_of_answer statt reinem Ranking.
 */
class SeoAnswerPresence extends Model
{
    protected $table = 'seo_answer_presence';

    protected $fillable = [
        'uuid', 'team_id', 'entity_id', 'answer_unit_id',
        'surface', 'present', 'position', 'cited', 'citation_url', 'share_of_answer', 'checked_at',
    ];

    protected $casts = [
        'present' => 'boolean',
        'cited' => 'boolean',
        'position' => 'integer',
        'share_of_answer' => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = UuidV7::generate();
            }
        });
    }

    public function entity(): BelongsTo
    {
        return $this->belongsTo(SeoEntity::class, 'entity_id');
    }

    public function answerUnit(): BelongsTo
    {
        return $this->belongsTo(SeoAnswerUnit::class, 'answer_unit_id');
    }
}
