<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_project_keyword', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('seo_projects')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('ranked_url', 500)->nullable();
            $table->string('target_url', 500)->nullable();
            $table->string('content_status', 20)->nullable();
            $table->string('priority', 20)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'keyword_id']);
        });

        // Migrate existing data: copy keyword->project associations into pivot
        if (Schema::hasColumn('seo_keywords', 'project_id')) {
            $keywords = \Illuminate\Support\Facades\DB::table('seo_keywords')
                ->whereNotNull('project_id')
                ->get(['id', 'project_id', 'position', 'ranked_url', 'target_url', 'content_status', 'priority', 'notes']);

            foreach ($keywords as $kw) {
                \Illuminate\Support\Facades\DB::table('seo_project_keyword')->insert([
                    'project_id' => $kw->project_id,
                    'keyword_id' => $kw->id,
                    'position' => $kw->position,
                    'ranked_url' => $kw->ranked_url,
                    'target_url' => $kw->target_url,
                    'content_status' => $kw->content_status,
                    'priority' => $kw->priority,
                    'notes' => $kw->notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_project_keyword');
    }
};
