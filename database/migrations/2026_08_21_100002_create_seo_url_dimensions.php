<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * URL-Dimensionen — die deklarierte SEO-Absicht einer Seite als Key-Value:
 * je Zeile eine (Dimension → Wert)-Facette. Dimensionen sind ein fester Satz
 * aus config('seo.dimensions') (basis/geo/anlass/typ/zielgruppe); Key-Value
 * statt fester Spalten, damit ein neuer Slot keine Migration kostet.
 *
 * `value` trägt den Begriff bzw. bei geo den Ortsnamen; `geo_location_id`
 * verweist zusätzlich auf den exakten seo_geo_locations-Eintrag (nur geo).
 * team_id denormalisiert aus der URL für tenant-sichere Abfragen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_dimensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('dimension', 30);
            $table->string('value', 191);
            $table->unsignedBigInteger('geo_location_id')->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'dimension', 'value']);
            $table->index(['team_id', 'dimension']);
            $table->index(['dimension', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_dimensions');
    }
};
