<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeoUrlRegistration extends Model
{
    protected $table = 'seo_url_registrations';

    protected $fillable = [
        'url_id',
        'source_module',
        'source_type',
        'source_id',
        'reason',
        'meta',
    ];

    protected $casts = [
        'source_id' => 'integer',
        'meta' => 'array',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }
}
