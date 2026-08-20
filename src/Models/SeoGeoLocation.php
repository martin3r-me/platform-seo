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
        if ($t === '') {
            return null;
        }

        // Nicht-geografische / Marketing-Typen bewusst ausschließen — sie sollen
        // nicht in den Ortspicker: Postleitzahlen (~8.000 allein für DE),
        // Flughäfen, Unis, Nielsen-DMA/TV-Regionen, US-Wahlkreise.
        foreach (['postal', 'airport', 'university', 'dma', 'tv region', 'congressional'] as $skip) {
            if (str_contains($t, $skip)) {
                return null;
            }
        }

        return match (true) {
            str_contains($t, 'country') => self::LEVEL_COUNTRY,
            // Stadt & Sub-Stadt (Stadtteil/Bezirk/Nachbarschaft) = city — ein
            // Stadtteil ist keine Region (Bundesland), sondern city-nah.
            str_contains($t, 'city'), str_contains($t, 'municipal'), str_contains($t, 'neighborhood'), str_contains($t, 'borough'), str_contains($t, 'district'), str_contains($t, 'ward') => self::LEVEL_CITY,
            str_contains($t, 'region'), str_contains($t, 'state'), str_contains($t, 'province'), str_contains($t, 'community'), str_contains($t, 'county'), str_contains($t, 'department'), str_contains($t, 'prefecture') => self::LEVEL_REGION,
            default => null,
        };
    }
}
