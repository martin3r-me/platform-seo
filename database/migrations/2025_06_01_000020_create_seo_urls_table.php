<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_urls', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->string('url', 2048);
            $table->string('url_hash', 64);
            $table->string('domain', 255);
            $table->string('path', 2048)->nullable();
            $table->boolean('is_own')->default(true);
            $table->string('status', 20)->default('active'); // active|redirected|deleted|error
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedTinyInteger('priority')->default(50);
            $table->timestamp('last_crawled_at')->nullable();
            $table->timestamp('next_crawl_at')->nullable();
            $table->unsignedInteger('keyword_count')->default(0);
            $table->unsignedInteger('total_search_volume')->default(0);
            $table->unsignedInteger('backlink_count')->default(0);
            $table->decimal('visibility_score', 12, 4)->default(0);
            $table->string('redirect_url', 2048)->nullable();
            $table->timestamp('redirect_detected_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['team_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_urls');
    }
};
