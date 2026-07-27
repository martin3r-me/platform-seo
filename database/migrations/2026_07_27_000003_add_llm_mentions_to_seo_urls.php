<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // AI-Auffindbarkeit pro Domain (DataForSEO LLM Mentions target_metrics):
        // wie sichtbar ist die Domain in LLM-Antworten (ChatGPT + Google AI Overview).
        // Denormalisiert auf der (Root-)URL; llm_mentions_data hält den Breakdown
        // je Plattform als Rohobjekt.
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('llm_mentions')->nullable()->after('backlinks_fetched_at');
            $table->unsignedInteger('llm_ai_search_volume')->nullable()->after('llm_mentions');
            $table->json('llm_mentions_data')->nullable()->after('llm_ai_search_volume');
            $table->timestamp('llm_mentions_fetched_at')->nullable()->after('llm_mentions_data');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn([
                'llm_mentions',
                'llm_ai_search_volume',
                'llm_mentions_data',
                'llm_mentions_fetched_at',
            ]);
        });
    }
};
