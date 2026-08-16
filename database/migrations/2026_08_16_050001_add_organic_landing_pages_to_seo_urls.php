<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organische Landingpages + Engagement: je organischer Einstiegsseite Besucher,
 * Verweildauer und Bounce-Rate. Zeigt, welche SEO-Türen den Traffic halten —
 * das Bindeglied zwischen Ranking und Conversion. Site-Level auf der Root-URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->json('organic_landing_pages')->nullable()->after('conversion_pages');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn('organic_landing_pages');
        });
    }
};
