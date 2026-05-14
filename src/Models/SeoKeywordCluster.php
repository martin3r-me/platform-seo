<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Symfony\Component\Uid\UuidV7;

class SeoKeywordCluster extends Model
{
    protected $table = 'seo_keyword_clusters';

    protected $fillable = [
        'uuid',
        'team_id',
        'project_id',
        'name',
        'description',
        'color',
        'order',
    ];

    protected $casts = [
        'uuid' => 'string',
        'order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SeoProject::class, 'project_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(SeoKeyword::class, 'cluster_id');
    }
}
