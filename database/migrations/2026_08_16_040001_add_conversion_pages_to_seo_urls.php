<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion-Attribution je Landingpage: pro Goal die Top-konvertierenden Seiten
 * (welche SEO-Seite bringt die Bewerbungen). Site-Level auf der Root-URL als
 * JSON — der stärkste Hebel aus den Plausible-Daten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->json('conversion_pages')->nullable()->after('top_goals');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn('conversion_pages');
        });
    }
};
