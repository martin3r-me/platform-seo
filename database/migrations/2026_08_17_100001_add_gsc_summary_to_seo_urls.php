<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalisierte GSC-Schicht je URL — spiegelt das Plausible-Muster:
 * der Collector rollt ein 28-Tage-Fenster (GSC-nativ) auf und legt hier die
 * lesefertigen Kennzahlen + JSON-Auswertungen ab, damit der GSC-Tab ohne
 * Live-API sofort rendert.
 *
 *  - gsc_*_28d/avg_position: skalare Gesamtwerte (echte Google-Zahlen)
 *  - gsc_top_queries: Begriffe, für die diese Seite tatsächlich rankt
 *  - gsc_discovered_queries: Ranking-Begriffe OHNE getracktes Keyword
 *    (Query-Discovery — die eigentliche Goldader Richtung Cluster)
 *  - gsc_ctr_opportunities: Seite-1-Positionen mit schwacher CTR
 *    (billigster Optimierungs-Hebel: Title/Snippet)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('gsc_clicks_28d')->default(0)->after('plausible_site_id');
            $table->unsignedInteger('gsc_impressions_28d')->default(0)->after('gsc_clicks_28d');
            $table->decimal('gsc_ctr_28d', 6, 4)->nullable()->after('gsc_impressions_28d');
            $table->decimal('gsc_avg_position', 6, 2)->nullable()->after('gsc_ctr_28d');
            $table->json('gsc_top_queries')->nullable()->after('gsc_avg_position');
            $table->json('gsc_discovered_queries')->nullable()->after('gsc_top_queries');
            $table->json('gsc_ctr_opportunities')->nullable()->after('gsc_discovered_queries');
            $table->timestamp('gsc_fetched_at')->nullable()->after('gsc_ctr_opportunities');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn([
                'gsc_clicks_28d',
                'gsc_impressions_28d',
                'gsc_ctr_28d',
                'gsc_avg_position',
                'gsc_top_queries',
                'gsc_discovered_queries',
                'gsc_ctr_opportunities',
                'gsc_fetched_at',
            ]);
        });
    }
};
