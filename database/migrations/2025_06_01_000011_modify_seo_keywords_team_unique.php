<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Drop foreign key first (MySQL requires FK dropped before its index)
        if ($this->hasForeignKey('seo_keywords', 'seo_keywords_project_id_foreign')) {
            Schema::table('seo_keywords', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }

        // Step 2: Drop indexes that reference project_id
        Schema::table('seo_keywords', function (Blueprint $table) {
            if ($this->hasIndex('seo_keywords', 'seo_keywords_project_id_keyword_unique')) {
                $table->dropUnique(['project_id', 'keyword']);
            }
            if ($this->hasIndex('seo_keywords', 'seo_keywords_project_id_cluster_id_index')) {
                $table->dropIndex(['project_id', 'cluster_id']);
            }
            if ($this->hasIndex('seo_keywords', 'seo_keywords_project_id_search_intent_index')) {
                $table->dropIndex(['project_id', 'search_intent']);
            }
        });

        // Step 3: Drop columns
        $columnsToDrop = array_filter(
            ['project_id', 'position', 'ranked_url', 'priority', 'notes', 'content_status', 'target_url', 'published_url'],
            fn ($col) => Schema::hasColumn('seo_keywords', $col),
        );

        if (! empty($columnsToDrop)) {
            Schema::table('seo_keywords', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }

        // Step 4: Add new indexes (if not already present)
        Schema::table('seo_keywords', function (Blueprint $table) {
            if (! $this->hasIndex('seo_keywords', 'seo_keywords_team_id_keyword_unique')) {
                $table->unique(['team_id', 'keyword']);
            }
            if (! $this->hasIndex('seo_keywords', 'seo_keywords_team_id_cluster_id_index')) {
                $table->index(['team_id', 'cluster_id']);
            }
            if (! $this->hasIndex('seo_keywords', 'seo_keywords_team_id_search_intent_index')) {
                $table->index(['team_id', 'search_intent']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            if ($this->hasIndex('seo_keywords', 'seo_keywords_team_id_keyword_unique')) {
                $table->dropUnique(['team_id', 'keyword']);
            }
            if ($this->hasIndex('seo_keywords', 'seo_keywords_team_id_cluster_id_index')) {
                $table->dropIndex(['team_id', 'cluster_id']);
            }
            if ($this->hasIndex('seo_keywords', 'seo_keywords_team_id_search_intent_index')) {
                $table->dropIndex(['team_id', 'search_intent']);
            }

            if (! Schema::hasColumn('seo_keywords', 'project_id')) {
                $table->foreignId('project_id')->nullable()->after('team_id')->constrained('seo_projects')->cascadeOnDelete();
                $table->unsignedSmallInteger('position')->nullable();
                $table->string('ranked_url', 500)->nullable();
                $table->string('priority', 20)->nullable();
                $table->text('notes')->nullable();
                $table->string('content_status', 20)->nullable();
                $table->string('target_url', 500)->nullable();
                $table->string('published_url', 500)->nullable();

                $table->unique(['project_id', 'keyword']);
                $table->index(['project_id', 'cluster_id']);
                $table->index(['project_id', 'search_intent']);
            }
        });
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);

        return count($indexes) > 0;
    }

    protected function hasForeignKey(string $table, string $keyName): bool
    {
        $database = DB::getDatabaseName();
        $fks = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $keyName],
        );

        return count($fks) > 0;
    }
};
