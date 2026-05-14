<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('keyword_id')->constrained('seo_keywords')->cascadeOnDelete();
            $table->string('domain');
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('position');
            $table->date('tracked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_competitors');
    }
};
