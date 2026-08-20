<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eine (Dimension → Wert)-Facette einer URL — die deklarierte SEO-Absicht,
 * aus der (mit dem Geo-Katalog) Seed-Phrasen und der gesperrte Basis-Cluster
 * abgeleitet werden. Dimensionen sind der feste Satz aus config('seo.dimensions').
 */
class SeoUrlDimension extends Model
{
    protected $table = 'seo_url_dimensions';

    public const DIM_BASIS = 'basis';

    public const DIM_GEO = 'geo';

    public const DIM_ANLASS = 'anlass';

    public const DIM_TYP = 'typ';

    public const DIM_ZIELGRUPPE = 'zielgruppe';

    protected $fillable = [
        'url_id',
        'team_id',
        'dimension',
        'value',
        'geo_location_id',
    ];

    protected $casts = [
        'url_id' => 'integer',
        'team_id' => 'integer',
        'geo_location_id' => 'integer',
    ];

    public function url(): BelongsTo
    {
        return $this->belongsTo(SeoUrl::class, 'url_id');
    }

    public function geoLocation(): BelongsTo
    {
        return $this->belongsTo(SeoGeoLocation::class, 'geo_location_id');
    }

    /** Der konfigurierte Dimensionen-Satz (fix zur Laufzeit, config-erweiterbar). */
    public static function catalog(): array
    {
        return (array) config('seo.dimensions', []);
    }
}
