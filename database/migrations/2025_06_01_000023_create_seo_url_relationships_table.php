<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_relationships', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('source_url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->foreignId('target_url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->string('type', 30); // parent_child|competitor|cannibalization|redirect
            $table->unsignedTinyInteger('strength')->default(0);
            $table->json('meta')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamps();

            $table->unique(['source_url_id', 'target_url_id', 'type'], 'seo_url_rel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_relationships');
    }
};
