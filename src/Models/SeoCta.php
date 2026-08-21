<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ein Call-to-Action einer URL — eigene Referenz (messbar/testbar). observed =
 * aus dem Crawl gezogen (Ist), target = geplant und per Flynk-Push in Produktion.
 * Zeigt auf einen kuratierten Typ (SeoCtaType), trägt Prominenz + Copy + Ziel.
 */
class SeoCta extends Model
{
    protected $table = 'seo_ctas';

    public const SOURCE_OBSERVED = 'observed';

    public const SOURCE_TARGET = 'target';

    public const PROMINENCES = ['primary', 'secondary', 'tertiary'];

    protected $fillable = [
        'url_id',
        'team_id',
        'cta_type_id',
        'prominence',
        'label',
        'target',
        'source',
        'clicks_30d',
        'conversions_30d',
        'first_seen',
        'last_seen',
    ];

    protected $casts = [
        'url_id' => 'integer',
        'team_id' => 'integer',
        'cta_type_id' => 'integer',
        'clicks_30d' => 'integer',
        'conversions_30d' => 'integer',
        'first_seen' => 'datetime',
        'last_seen' => 'datetime',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }

    public function ctaType(): BelongsTo
    {
        return $this->belongsTo(SeoCtaType::class, 'cta_type_id');
    }
}
