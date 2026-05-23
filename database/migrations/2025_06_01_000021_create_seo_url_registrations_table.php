<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->string('source_module', 50); // seo|cms|brands|...
            $table->string('source_type', 100)->nullable(); // morph type
            $table->unsignedBigInteger('source_id')->nullable(); // morph id
            $table->string('reason', 50)->default('manual'); // manual|auto_discovery|content_published|...
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'source_module', 'source_type', 'source_id'], 'seo_url_reg_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_registrations');
    }
};
