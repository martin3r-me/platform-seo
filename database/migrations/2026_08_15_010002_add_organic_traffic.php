<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organischen Traffic separat führen — die SEO-relevante Zahl. Plausible liefert
 * bisher nur Gesamt-Traffic je Seite; hier kommt der organische Anteil (Channel-
 * Filter) dazu, pro Tag und als 30-Tage-Denormalisierung auf der URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_url_traffic', function (Blueprint $table) {
            $table->unsignedInteger('organic_visitors')->nullable()->after('pageviews');
            $table->unsignedInteger('organic_pageviews')->nullable()->after('organic_visitors');
        });

        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('organic_visitors_30d')->nullable()->after('pageviews_30d');
            $table->unsignedInteger('organic_pageviews_30d')->nullable()->after('organic_visitors_30d');
        });
    }

    public function down(): void
    {
        Schema::table('seo_url_traffic', function (Blueprint $table) {
            $table->dropColumn(['organic_visitors', 'organic_pageviews']);
        });
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['organic_visitors_30d', 'organic_pageviews_30d']);
        });
    }
};
