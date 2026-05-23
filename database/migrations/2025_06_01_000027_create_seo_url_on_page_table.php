<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_on_page', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->unique()->constrained('seo_urls')->cascadeOnDelete();
            $table->string('title', 500)->nullable();
            $table->string('meta_description', 1000)->nullable();
            $table->string('h1', 500)->nullable();
            $table->json('headings')->nullable();
            $table->unsignedInteger('word_count')->nullable();
            $table->unsignedTinyInteger('page_speed_score')->nullable();
            $table->unsignedTinyInteger('mobile_score')->nullable();
            $table->json('structured_data_types')->nullable();
            $table->json('issues')->nullable();
            $table->unsignedTinyInteger('overall_score')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_on_page');
    }
};
