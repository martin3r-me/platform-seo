<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * Antwort-Einheit — unser Asset (v2, docs/NORDSTERN-v2.md): der atomare
 * strukturierte Antwort-Baustein für eine Entität, gehostet auf einer Seite.
 */
class SeoAnswerUnit extends Model
{
    protected $table = 'seo_answer_units';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_LIVE = 'live';
    public const STATUS_STALE = 'stale';

    protected $fillable = [
        'uuid', 'team_id', 'entity_id', 'url_id', 'portfolio_id', 'brief_id',
        'claim', 'evidence', 'schema_type', 'status', 'verified_at', 'content_hash',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
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

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(SeoPortfolio::class, 'portfolio_id');
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'brief_id');
    }

    public function presence(): HasMany
    {
        return $this->hasMany(SeoAnswerPresence::class, 'answer_unit_id');
    }
}
