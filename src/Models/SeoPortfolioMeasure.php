<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

/**
 * Eine Maßnahme — das Atom der Steuer-Produktionslinie eines Wirkungsraums.
 * Siehe Migration 2026_08_18_130001 + [[seo-orchestration-board]].
 */
class SeoPortfolioMeasure extends Model
{
    protected $table = 'seo_portfolio_measures';

    public const STATUS_PROPOSED = 'proposed';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_RELEASED = 'released';
    public const STATUS_DONE = 'done';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'uuid', 'team_id', 'portfolio_id',
        'type', 'target_url_id', 'target_cluster_id',
        'title', 'rationale', 'expected_result',
        'source', 'source_key', 'score', 'route', 'status', 'reject_reason',
        'decided_at', 'released_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'decided_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if (empty($m->uuid)) {
                $m->uuid = UuidV7::generate();
            }
        });
    }

    public function portfolio(): BelongsTo
    {
        return $this->belongsTo(SeoPortfolio::class, 'portfolio_id');
    }

    public function targetUrl(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'target_url_id');
    }

    public function targetCluster(): BelongsTo
    {
        return $this->belongsTo(SeoKeywordCluster::class, 'target_cluster_id');
    }

    /** Menschlich lesbares Typ-Label aus der Config. */
    public function typeLabel(): string
    {
        return (string) (config('seo.measure_types.'.$this->type.'.label') ?? $this->type);
    }
}
