<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->nullable();
            $table->unsignedSmallInteger('previous_position')->nullable();
            $table->string('search_engine', 20)->default('google');
            $table->string('device', 20)->default('desktop');
            $table->timestamp('position_updated_at')->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'keyword_id', 'search_engine', 'device'], 'seo_url_kw_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_keywords');
    }
};
