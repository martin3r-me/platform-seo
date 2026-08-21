<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CTAs je URL — Call-to-Action als eigene Referenz (wie Pages/Keywords), damit
 * sie messbar/testbar sind. `source` trennt observed (aus dem Crawl gezogen)
 * von target (geplant → geht per Flynk-Push in Produktion). `cta_type_id` zeigt
 * auf den kuratierten Typ (seo_cta_types), `prominence` auf die Wichtigkeit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_ctas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id');
            $table->unsignedBigInteger('team_id');
            $table->unsignedBigInteger('cta_type_id')->nullable();  // → seo_cta_types
            $table->string('prominence', 20)->default('primary');   // primary | secondary | tertiary
            $table->string('label')->nullable();                    // Copy/Beschriftung
            $table->string('target', 512)->nullable();              // href / tel: / mailto:
            $table->string('source', 12)->default('target');        // observed | target
            $table->unsignedInteger('clicks_30d')->default(0);
            $table->unsignedInteger('conversions_30d')->default(0);
            $table->timestamp('first_seen')->nullable();
            $table->timestamp('last_seen')->nullable();
            $table->timestamps();

            $table->index(['url_id', 'source']);
            $table->index(['team_id', 'cta_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_ctas');
    }
};
