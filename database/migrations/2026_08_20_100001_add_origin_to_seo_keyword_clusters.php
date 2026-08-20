<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Herkunft eines Clusters: 'harvested' (aus dem Bestands-Ranking geerntet, via
 * übernehmen/SERP) vs. 'build' (bewusst als Netto-neu-Bauziel angelegt, für ein
 * Kopfthema, das wir besitzen wollen, aber noch nicht ranken). Trennt die zwei
 * legitimen Wege sauber — erntet-vs-baut — für UI und spätere Auswertung.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->string('origin', 20)->default('harvested')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->dropColumn('origin');
        });
    }
};
