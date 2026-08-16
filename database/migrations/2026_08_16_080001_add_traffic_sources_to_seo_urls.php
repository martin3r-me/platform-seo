<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Traffic-Quellen je Site (visit:source, 30 T) — Basis für den Verbund-Referral:
 * kommt der Traffic einer Property per Verweis von ANDEREN Verbund-Properties?
 * Das ist der Verbund bei der Arbeit (Ranker speist Endpunkt). Auf der Root-URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->json('traffic_sources')->nullable()->after('organic_landing_pages');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn('traffic_sources');
        });
    }
};
