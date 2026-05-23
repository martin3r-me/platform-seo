<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_ranking_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->string('search_engine', 20)->default('google');
            $table->string('device', 20)->default('desktop');
            $table->json('serp_features')->nullable();
            $table->date('tracked_at');
            $table->timestamps();

            $table->unique(['url_id', 'keyword_id', 'tracked_at', 'search_engine', 'device'], 'seo_rank_hist_unique');
            $table->index(['keyword_id', 'tracked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ranking_history');
    }
};
