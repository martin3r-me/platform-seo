<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            // Drop old unique constraint and indexes that reference project_id
            $table->dropUnique(['project_id', 'keyword']);
            $table->dropIndex(['project_id', 'cluster_id']);
            $table->dropIndex(['project_id', 'search_intent']);

            // Drop the foreign key + column for project_id
            $table->dropForeign(['project_id']);
            $table->dropColumn([
                'project_id',
                'position',
                'ranked_url',
                'priority',
                'notes',
                'content_status',
                'target_url',
                'published_url',
            ]);

            // New unique constraint: one keyword per team
            $table->unique(['team_id', 'keyword']);

            // New indexes
            $table->index(['team_id', 'cluster_id']);
            $table->index(['team_id', 'search_intent']);
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'keyword']);
            $table->dropIndex(['team_id', 'cluster_id']);
            $table->dropIndex(['team_id', 'search_intent']);

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
        });
    }
};
