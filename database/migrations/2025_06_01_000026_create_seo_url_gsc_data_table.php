<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_gsc_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('seo_keywords')->nullOnDelete();
            $table->date('date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('ctr', 6, 4)->default(0);
            $table->decimal('avg_position', 6, 2)->default(0);
            $table->string('device', 20)->default('all');
            $table->string('country', 5)->default('all');
            $table->timestamps();

            $table->unique(['url_id', 'keyword_id', 'date', 'device', 'country'], 'seo_url_gsc_unique');
            $table->index(['url_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_gsc_data');
    }
};
