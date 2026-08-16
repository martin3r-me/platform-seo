<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

/**
 * Wirkungsraum — der Steuer-Scope: kontrollierte URLs + Ziel, verschachtelbar.
 * Siehe Migration 2026_08_15_030001 / docs/WIRKUNGSRAUM-CONCEPT.md.
 */
class SeoPortfolio extends Model
{
    protected $table = 'seo_portfolios';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'slug',
        'description',
        'goal',
        'parent_id',
        'clustering_status',
        'clustering_started_at',
        'clustering_result',
    ];

    protected $casts = [
        'uuid' => 'string',
        'clustering_started_at' => 'datetime',
        'clustering_result' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    /** Kontrollierte URLs dieses Wirkungsraums. */
    public function urls(): BelongsToMany
    {
        return $this->belongsToMany(SeoUrl::class, 'seo_portfolio_urls', 'portfolio_id', 'url_id')
            ->withPivot('role', 'added_at');
    }

    /** Übergeordneter Wirkungsraum (Verbund). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Untergeordnete Wirkungsräume (Gruppierung). */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }

    /** Status des Nach-Clusterns (running/completed/failed) festhalten. */
    public function markClustering(string $status, ?array $result = null): void
    {
        $this->forceFill([
            'clustering_status' => $status,
            'clustering_started_at' => $status === 'running' ? now() : $this->clustering_started_at,
            'clustering_result' => $result,
        ])->save();
    }
}
