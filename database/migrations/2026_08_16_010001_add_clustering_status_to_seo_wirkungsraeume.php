<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nach-Clustern des ungeclusterten Rests eines Wirkungsraums läuft als
 * Hintergrund-Job (1 SERP-Call je Keyword). Status/Ergebnis WR-scoped
 * ablegen, damit die UI Fortschritt zeigen kann.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_wirkungsraeume', function (Blueprint $table) {
            $table->string('clustering_status')->nullable()->after('goal');
            $table->timestamp('clustering_started_at')->nullable()->after('clustering_status');
            $table->json('clustering_result')->nullable()->after('clustering_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('seo_wirkungsraeume', function (Blueprint $table) {
            $table->dropColumn(['clustering_status', 'clustering_started_at', 'clustering_result']);
        });
    }
};
