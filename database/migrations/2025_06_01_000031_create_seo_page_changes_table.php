<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_page_changes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->date('detected_at');
            $table->string('change_type');
            $table->string('severity', 10)->default('minor');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->integer('delta')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['url_id', 'detected_at']);
            $table->index(['team_id', 'detected_at', 'severity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_page_changes');
    }
};
