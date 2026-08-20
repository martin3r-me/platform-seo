<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Geo-Katalog — driftfreie Referenzschicht für die GEO-Dimension. Spiegelt
 * DataForSEOs Orts-Datenbank (location_code + Name + Typ), damit eine GEO-
 * Einstellung nicht als Freitext getippt, sondern als exakter Code gewählt
 * wird. Der `code` ist maschinen-fertig für ortsgenaues Targeting (Weg 2),
 * der `name` speist die Seed-Phrasen (Weg 1), das `level` (country/region/
 * city, aus DataForSEOs location_type normalisiert) trägt Hub/Spoke-Ableitung.
 *
 * Global (kein team_id): Ortscodes sind universelle Referenzdaten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_geo_locations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code')->unique();      // DataForSEO location_code (z. B. 2276 = Deutschland)
            $table->string('name');                          // location_name
            $table->string('country_iso', 3)->nullable();    // country_iso_code, z. B. "DE"
            $table->string('type')->nullable();              // roher DataForSEO location_type
            $table->string('level', 20)->nullable();         // normalisiert: country | region | city
            $table->timestamps();

            $table->index(['country_iso', 'level']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_geo_locations');
    }
};
