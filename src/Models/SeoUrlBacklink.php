<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlBacklink extends Model
{
    protected $table = 'seo_url_backlinks';

    protected $fillable = [
        'url_id',
        'source_url',
        'source_url_hash',
        'source_domain',
        'anchor_text',
        'link_type',
        'source_domain_authority',
        'first_seen_at',
        'last_seen_at',
        'is_active',
        'meta',
    ];

    protected $casts = [
        'source_domain_authority' => 'integer',
        'first_seen_at' => 'date',
        'last_seen_at' => 'date',
        'is_active' => 'boolean',
        'meta' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model) {
            if (empty($model->source_url_hash)) {
                $model->source_url_hash = hash('sha256', $model->source_url);
            }
            if (empty($model->source_domain)) {
                $model->source_domain = parse_url($model->source_url, PHP_URL_HOST) ?? '';
            }
        });
    }

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
