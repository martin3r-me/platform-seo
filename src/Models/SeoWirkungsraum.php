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
class SeoWirkungsraum extends Model
{
    protected $table = 'seo_wirkungsraeume';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'slug',
        'description',
        'goal',
        'parent_id',
    ];

    protected $casts = [
        'uuid' => 'string',
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
        return $this->belongsToMany(SeoUrl::class, 'seo_wirkungsraum_urls', 'wirkungsraum_id', 'url_id')
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
}
