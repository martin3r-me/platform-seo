<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keywords', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('project_id')->constrained('seo_projects')->cascadeOnDelete();
            $table->foreignId('cluster_id')->nullable()->constrained('seo_keyword_clusters')->nullOnDelete();
            $table->string('keyword');
            $table->unsignedInteger('search_volume')->nullable();
            $table->unsignedInteger('cpc_cents')->nullable();
            $table->decimal('competition', 4, 3)->nullable();
            $table->unsignedTinyInteger('keyword_difficulty')->nullable();
            $table->string('search_intent', 30)->nullable();
            $table->string('topic', 100)->nullable();
            $table->json('monthly_volumes')->nullable();
            $table->unsignedTinyInteger('peak_month')->nullable();
            $table->decimal('seasonality_index', 3, 2)->nullable();
            $table->json('google_trends_data')->nullable();
            $table->unsignedTinyInteger('trends_average_interest')->nullable();
            $table->unsignedTinyInteger('trends_peak_interest')->nullable();
            $table->timestamp('trends_fetched_at')->nullable();
            $table->unsignedSmallInteger('position')->nullable();
            $table->string('ranked_url', 500)->nullable();
            $table->string('priority', 20)->nullable();
            $table->text('notes')->nullable();
            $table->string('content_status', 20)->nullable();
            $table->string('target_url', 500)->nullable();
            $table->string('published_url', 500)->nullable();
            $table->json('dataforseo_raw')->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'keyword']);
            $table->index(['project_id', 'cluster_id']);
            $table->index(['project_id', 'search_intent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keywords');
    }
};
