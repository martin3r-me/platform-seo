<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * Entität — das Nachfrage-Atom von v2 (docs/NORDSTERN-v2.md). offer/action sind
 * der Transaktions-Slot der agent-actionable Zukunft (heute ungenutzt).
 */
class SeoEntity extends Model
{
    protected $table = 'seo_entities';

    protected $fillable = [
        'uuid', 'team_id', 'name', 'entity_type', 'aliases', 'intent',
        'cluster_id', 'search_volume', 'ai_ask_volume', 'offer', 'action',
    ];

    protected $casts = [
        'aliases' => 'array',
        'search_volume' => 'integer',
        'ai_ask_volume' => 'integer',
        'offer' => 'array',
        'action' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = UuidV7::generate();
            }
        });
    }

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordCluster::class, 'cluster_id');
    }

    public function answerUnits(): HasMany
    {
        return $this->hasMany(SeoAnswerUnit::class, 'entity_id');
    }

    public function presence(): HasMany
    {
        return $this->hasMany(SeoAnswerPresence::class, 'entity_id');
    }
}
