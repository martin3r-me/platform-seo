<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->string('context_type', 50);
            $table->unsignedBigInteger('context_id');
            $table->string('label')->nullable();
            $table->string('url', 500)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_contexts');
    }
};
