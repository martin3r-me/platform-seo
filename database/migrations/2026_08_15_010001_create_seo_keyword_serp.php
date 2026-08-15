<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SERP-Features je Keyword — aus demselben SERP-Call, den wir für Rankings/
 * Competitors ohnehin bezahlen (bisher verworfen). People-Also-Ask (Content-/
 * Keyword-Ideen), Related Searches (Expansion), Featured Snippet (Optimierungs-
 * ziel) und AI-Overview-Präsenz (AEO-Tracking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_serp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->unique()->constrained('seo_keywords')->cascadeOnDelete();
            $table->unsignedBigInteger('team_id')->index();
            $table->json('item_types')->nullable();
            $table->json('people_also_ask')->nullable();
            $table->json('related_searches')->nullable();
            $table->json('featured_snippet')->nullable();
            $table->boolean('has_ai_overview')->default(false);
            $table->json('ai_overview_references')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_serp');
    }
};
