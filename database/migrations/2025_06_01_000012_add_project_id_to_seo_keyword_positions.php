<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- seo_keyword_positions ---

        // Add project_id column if missing
        if (! Schema::hasColumn('seo_keyword_positions', 'project_id')) {
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->unsignedBigInteger('project_id')->nullable()->after('keyword_id');
            });
        }

        // Drop FK on keyword_id first (MySQL needs it gone before dropping the unique index it backs)
        if ($this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_keyword_id_foreign')) {
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->dropForeign(['keyword_id']);
            });
        }

        // Drop old unique index
        if ($this->hasIndex('seo_keyword_positions', 'seo_kw_pos_unique')) {
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->dropUnique('seo_kw_pos_unique');
            });
        }

        // Re-add FK on keyword_id + add FK on project_id + new unique index
        Schema::table('seo_keyword_positions', function (Blueprint $table) {
            if (! $this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_keyword_id_foreign')) {
                $table->foreign('keyword_id')->references('id')->on('seo_keywords')->cascadeOnDelete();
            }
            if (! $this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_project_id_foreign')) {
                $table->foreign('project_id')->references('id')->on('seo_projects')->cascadeOnDelete();
            }
            if (! $this->hasIndex('seo_keyword_positions', 'seo_kw_pos_unique')) {
                $table->unique(['keyword_id', 'project_id', 'tracked_at', 'search_engine', 'device'], 'seo_kw_pos_unique');
            }
        });

        // --- seo_keyword_competitors ---

        if (! Schema::hasColumn('seo_keyword_competitors', 'project_id')) {
            Schema::table('seo_keyword_competitors', function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('keyword_id')->constrained('seo_projects')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // --- seo_keyword_competitors ---
        if (Schema::hasColumn('seo_keyword_competitors', 'project_id')) {
            Schema::table('seo_keyword_competitors', function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }

        // --- seo_keyword_positions ---
        if ($this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_keyword_id_foreign')) {
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->dropForeign(['keyword_id']);
            });
        }

        if ($this->hasIndex('seo_keyword_positions', 'seo_kw_pos_unique')) {
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->dropUnique('seo_kw_pos_unique');
            });
        }

        if (Schema::hasColumn('seo_keyword_positions', 'project_id')) {
            if ($this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_project_id_foreign')) {
                Schema::table('seo_keyword_positions', function (Blueprint $table) {
                    $table->dropForeign(['project_id']);
                });
            }
            Schema::table('seo_keyword_positions', function (Blueprint $table) {
                $table->dropColumn('project_id');
            });
        }

        Schema::table('seo_keyword_positions', function (Blueprint $table) {
            if (! $this->hasForeignKey('seo_keyword_positions', 'seo_keyword_positions_keyword_id_foreign')) {
                $table->foreign('keyword_id')->references('id')->on('seo_keywords')->cascadeOnDelete();
            }
            if (! $this->hasIndex('seo_keyword_positions', 'seo_kw_pos_unique')) {
                $table->unique(['keyword_id', 'tracked_at', 'search_engine', 'device'], 'seo_kw_pos_unique');
            }
        });
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        return count(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName])) > 0;
    }

    protected function hasForeignKey(string $table, string $keyName): bool
    {
        $database = DB::getDatabaseName();

        return count(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$database, $table, $keyName],
        )) > 0;
    }
};
