<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\UuidV7;

class SeoPageChange extends Model
{
    protected $table = 'seo_page_changes';

    protected $fillable = [
        'uuid',
        'team_id',
        'url_id',
        'detected_at',
        'change_type',
        'severity',
        'old_value',
        'new_value',
        'delta',
        'context',
    ];

    protected $casts = [
        'uuid' => 'string',
        'detected_at' => 'date',
        'delta' => 'integer',
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

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
