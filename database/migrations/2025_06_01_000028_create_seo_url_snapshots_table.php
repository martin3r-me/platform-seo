<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('keyword_count')->default(0);
            $table->unsignedInteger('total_search_volume')->default(0);
            $table->decimal('visibility_score', 12, 4)->default(0);
            $table->unsignedInteger('backlink_count')->default(0);
            $table->unsignedTinyInteger('on_page_score')->nullable();
            $table->json('top_keywords')->nullable();
            $table->json('position_distribution')->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_snapshots');
    }
};
