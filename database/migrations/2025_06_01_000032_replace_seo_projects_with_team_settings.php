<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1a. Rename seo_projects → seo_team_settings and drop unused columns
        Schema::rename('seo_projects', 'seo_team_settings');

        Schema::table('seo_team_settings', function (Blueprint $table) {
            // Drop soft deletes
            $table->dropSoftDeletes();

            // Drop columns no longer relevant
            $table->dropColumn(['user_id', 'uuid', 'name', 'description', 'industry_preset']);
        });

        // 1b. Add team_id to tables that only have project_id, backfill from seo_team_settings
        $tablesNeedingTeamId = [
            'seo_keyword_positions',
            'seo_keyword_competitors',
            'seo_budget_logs',
            'seo_keyword_clusters',
        ];

        foreach ($tablesNeedingTeamId as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('team_id')->nullable()->after('id');
            });

            // Backfill team_id from seo_team_settings via project_id
            DB::statement("
                UPDATE {$tableName}
                SET team_id = (
                    SELECT team_id FROM seo_team_settings
                    WHERE seo_team_settings.id = {$tableName}.project_id
                )
                WHERE project_id IS NOT NULL
            ");
        }

        // 1c. Drop project_id FK from all tables

        // seo_urls — drop project_id
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // seo_signals — drop project_id (has team_id already)
        Schema::table('seo_signals', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // seo_keyword_positions — drop project_id (now has team_id)
        Schema::table('seo_keyword_positions', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // seo_keyword_competitors — drop project_id (now has team_id)
        Schema::table('seo_keyword_competitors', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // seo_budget_logs — drop project_id (now has team_id)
        Schema::table('seo_budget_logs', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // seo_keyword_clusters — drop project_id (now has team_id)
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->dropColumn('project_id');
        });

        // 1d. Drop deprecated pivot table
        Schema::dropIfExists('seo_project_keyword');
    }

    public function down(): void
    {
        // Recreate pivot table
        Schema::create('seo_project_keyword', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('keyword_id');
            $table->integer('position')->nullable();
            $table->string('ranked_url', 2048)->nullable();
            $table->string('target_url', 2048)->nullable();
            $table->string('content_status', 50)->nullable();
            $table->string('priority', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['project_id', 'keyword_id']);
        });

        // Re-add project_id to tables
        $tablesWithTeamId = [
            'seo_keyword_positions',
            'seo_keyword_competitors',
            'seo_budget_logs',
            'seo_keyword_clusters',
        ];

        foreach ($tablesWithTeamId as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable();
            });

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('team_id');
            });
        }

        Schema::table('seo_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable();
        });

        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedBigInteger('project_id')->nullable();
        });

        // Restore seo_team_settings → seo_projects
        Schema::table('seo_team_settings', function (Blueprint $table) {
            $table->string('uuid', 36)->nullable()->after('id');
            $table->unsignedBigInteger('user_id')->nullable()->after('team_id');
            $table->string('name')->nullable()->after('user_id');
            $table->text('description')->nullable()->after('name');
            $table->string('industry_preset')->nullable()->after('description');
            $table->softDeletes();
        });

        Schema::rename('seo_team_settings', 'seo_projects');
    }
};
