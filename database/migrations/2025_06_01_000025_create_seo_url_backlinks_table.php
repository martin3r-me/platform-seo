<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_backlinks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->string('source_url', 2048);
            $table->string('source_url_hash', 64);
            $table->string('source_domain', 255);
            $table->string('anchor_text', 500)->nullable();
            $table->string('link_type', 20)->default('dofollow'); // dofollow|nofollow|ugc|sponsored
            $table->unsignedTinyInteger('source_domain_authority')->nullable();
            $table->date('first_seen_at')->nullable();
            $table->date('last_seen_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'source_url_hash'], 'seo_url_bl_unique');
            $table->index('source_domain');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_backlinks');
    }
};
