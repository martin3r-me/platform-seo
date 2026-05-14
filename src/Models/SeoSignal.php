<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SeoSignal extends Model
{
    protected $table = 'seo_signals';

    protected $fillable = [
        'uuid',
        'team_id',
        'project_id',
        'keyword_id',
        'signal_type',
        'severity',
        'title',
        'description',
        'metric_before',
        'metric_after',
        'metric_delta',
        'detected_at',
        'status',
        'context',
    ];

    protected $casts = [
        'uuid' => 'string',
        'metric_before' => 'decimal:4',
        'metric_after' => 'decimal:4',
        'metric_delta' => 'decimal:4',
        'detected_at' => 'date',
        'context' => 'array',
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

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(SeoKeyword::class, 'keyword_id');
    }
}
