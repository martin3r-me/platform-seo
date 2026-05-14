<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->string('ranked_url', 500)->nullable();
            $table->json('serp_features')->nullable();
            $table->string('search_engine', 20)->default('google');
            $table->string('device', 20)->default('desktop');
            $table->string('location', 100)->nullable();
            $table->date('tracked_at');
            $table->timestamps();

            $table->unique(['keyword_id', 'tracked_at', 'search_engine', 'device'], 'seo_kw_pos_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_positions');
    }
};
