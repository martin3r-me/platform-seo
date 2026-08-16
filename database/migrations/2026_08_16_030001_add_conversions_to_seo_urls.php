<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wirkung: Plausible-Goals/Conversions je Site an der URL ablegen. Die Daten
 * liegen längst in Plausible (CTA-Clicks, Formular-Submits, …) — bisher zogen
 * wir sie nur nicht. Site-Level auf der Root-URL; top_goals als JSON-Detail.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('conversions_30d')->default(0)->after('pageviews_30d');
            $table->decimal('conversion_rate', 5, 2)->nullable()->after('conversions_30d');
            $table->string('primary_goal')->nullable()->after('conversion_rate');
            $table->json('top_goals')->nullable()->after('primary_goal');
            $table->timestamp('conversions_fetched_at')->nullable()->after('top_goals');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['conversions_30d', 'conversion_rate', 'primary_goal', 'top_goals', 'conversions_fetched_at']);
        });
    }
};
