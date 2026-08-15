<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daten-Profile: EIN Knopf pro URL statt verstreuter Feinregler. Das Profil
 * bestimmt, welche Collectoren wie oft laufen (→ Kadenz → Monatskosten).
 * Default-Profil auf Liste und Team; Boost = zeitlich begrenzt täglich SERP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->string('data_profile')->nullable()->after('priority');
            $table->timestamp('boost_until')->nullable()->after('data_profile');
        });

        Schema::table('seo_url_lists', function (Blueprint $table) {
            $table->string('default_data_profile')->nullable();
        });

        Schema::table('seo_team_settings', function (Blueprint $table) {
            $table->string('default_data_profile')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['data_profile', 'boost_until']);
        });
        Schema::table('seo_url_lists', function (Blueprint $table) {
            $table->dropColumn('default_data_profile');
        });
        Schema::table('seo_team_settings', function (Blueprint $table) {
            $table->dropColumn('default_data_profile');
        });
    }
};
