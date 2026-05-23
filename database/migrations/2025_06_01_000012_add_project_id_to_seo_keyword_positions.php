<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keyword_positions', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('keyword_id')->constrained('seo_projects')->cascadeOnDelete();

            // Drop old unique and create new one including project_id
            $table->dropUnique('seo_kw_pos_unique');
            $table->unique(['keyword_id', 'project_id', 'tracked_at', 'search_engine', 'device'], 'seo_kw_pos_unique');
        });

        // Also add project_id to competitors table
        Schema::table('seo_keyword_competitors', function (Blueprint $table) {
            $table->foreignId('project_id')->nullable()->after('keyword_id')->constrained('seo_projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seo_keyword_competitors', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
        });

        Schema::table('seo_keyword_positions', function (Blueprint $table) {
            $table->dropUnique('seo_kw_pos_unique');
            $table->dropForeign(['project_id']);
            $table->dropColumn('project_id');
            $table->unique(['keyword_id', 'tracked_at', 'search_engine', 'device'], 'seo_kw_pos_unique');
        });
    }
};
