<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            if (!Schema::hasColumn('seo_keywords', 'competitor_tracking_depth')) {
                $table->unsignedTinyInteger('competitor_tracking_depth')->nullable()->after('keyword_difficulty');
            }
        });

        Schema::table('seo_team_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('seo_team_settings', 'default_competitor_tracking_depth')) {
                $table->unsignedTinyInteger('default_competitor_tracking_depth')->default(5)->after('language_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            if (Schema::hasColumn('seo_keywords', 'competitor_tracking_depth')) {
                $table->dropColumn('competitor_tracking_depth');
            }
        });

        Schema::table('seo_team_settings', function (Blueprint $table) {
            if (Schema::hasColumn('seo_team_settings', 'default_competitor_tracking_depth')) {
                $table->dropColumn('default_competitor_tracking_depth');
            }
        });
    }
};
