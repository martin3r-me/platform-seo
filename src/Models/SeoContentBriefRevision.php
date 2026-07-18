<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SeoContentBriefRevision extends Model
{
    protected $table = 'seo_content_brief_revisions';

    protected $fillable = [
        'uuid',
        'content_brief_id',
        'revision_type',
        'summary',
        'metrics_before',
        'metrics_after',
        'changes',
        'user_id',
        'revised_at',
    ];

    protected $casts = [
        'uuid' => 'string',
        'metrics_before' => 'array',
        'metrics_after' => 'array',
        'changes' => 'array',
        'revised_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = UuidV7::generate();
            }
        });
    }

    public function brief(): BelongsTo
    {
        return $this->belongsTo(SeoContentBrief::class, 'content_brief_id');
    }
}
