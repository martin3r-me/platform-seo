<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Platform\Core\Contracts\HasDisplayName;
use Symfony\Component\Uid\UuidV7;

/**
 * Content-Brief — der Produktions-Plan für ein Stück Content.
 *
 * Trägt den Content-Lifecycle (status draft→…→published) und die target_url,
 * die bei Veröffentlichung zur getrackten seo_url wird (Loop). Zielt über den
 * Cluster-Pivot auf die strategischen Cluster.
 */
class SeoContentBrief extends Model implements HasDisplayName
{
    protected $table = 'seo_content_briefs';

    protected $fillable = [
        'uuid',
        'team_id',
        'user_id',
        'name',
        'description',
        'content_type',
        'search_intent',
        'status',
        'target_slug',
        'target_url',
        'target_word_count',
        'order',
        'done',
        'done_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'target_word_count' => 'integer',
        'order' => 'integer',
        'done' => 'boolean',
        'done_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function sections(): HasMany
    {
        return $this->hasMany(SeoContentBriefSection::class, 'content_brief_id')->orderBy('order');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SeoContentBriefNote::class, 'content_brief_id')
            ->orderBy('note_type')
            ->orderBy('order');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(SeoContentBriefRevision::class, 'content_brief_id')->orderByDesc('revised_at');
    }

    public function outgoingLinks(): HasMany
    {
        return $this->hasMany(SeoContentBriefLink::class, 'source_content_brief_id');
    }

    public function incomingLinks(): HasMany
    {
        return $this->hasMany(SeoContentBriefLink::class, 'target_content_brief_id');
    }

    public function clusters(): BelongsToMany
    {
        return $this->belongsToMany(
            SeoKeywordCluster::class,
            'seo_content_brief_clusters',
            'content_brief_id',
            'cluster_id',
        )->withPivot('role')->withTimestamps();
    }

    public function getDisplayName(): ?string
    {
        return $this->name;
    }
}
