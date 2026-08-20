<?php

namespace Platform\Seo\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ein Ort aus DataForSEOs Orts-Katalog — die Referenzschicht hinter der
 * GEO-Dimension. `code` = exakter DataForSEO location_code (Targeting),
 * `name` = Anzeige + Seed-Phrasen, `level` = country|region|city (aus dem
 * rohen `type` normalisiert), trägt später die Hub/Spoke-Ableitung.
 *
 * Global (nicht team-scoped): universelle Referenzdaten.
 */
class SeoGeoLocation extends Model
{
    protected $table = 'seo_geo_locations';

    public const LEVEL_COUNTRY = 'country';

    public const LEVEL_REGION = 'region';

    public const LEVEL_CITY = 'city';

    protected $fillable = [
        'code',
        'name',
        'country_iso',
        'type',
        'level',
    ];

    protected $casts = [
        'code' => 'integer',
    ];

    /**
     * DataForSEOs `location_type` (Country, Region, City, Municipality,
     * Autonomous Community, County, …) auf unsere drei Ebenen normalisieren.
     * Unbekanntes fällt auf null (bleibt wählbar, trägt nur keine Hierarchie).
     */
    public static function normalizeLevel(?string $type): ?string
    {
        $t = strtolower(trim((string) $type));

        return match (true) {
            $t === '' => null,
            str_contains($t, 'country') => self::LEVEL_COUNTRY,
            str_contains($t, 'city'), str_contains($t, 'municipal'), str_contains($t, 'neighborhood'), str_contains($t, 'borough') => self::LEVEL_CITY,
            str_contains($t, 'region'), str_contains($t, 'state'), str_contains($t, 'province'), str_contains($t, 'community'), str_contains($t, 'county'), str_contains($t, 'district') => self::LEVEL_REGION,
            default => null,
        };
    }
}
