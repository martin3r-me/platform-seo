<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SERP-Cache je Keyword — macht das Nach-Clustern wiederaufsetzbar: jeder
 * (teure, live) DataForSeo-SERP-Abruf wird persistiert, damit ein Timeout/
 * Neustart nur noch das Fehlende holt statt erneut zu zahlen. Reicht fürs
 * Gruppieren (Top-10-URLs); TTL wird im Service geprüft (kein Kalender-Zwang).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_keyword_serps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('keyword_id')->unique(); // ein SERP-Stand je Keyword
            $table->json('urls'); // normalisierte Top-10 (host+path), kann [] sein
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_keyword_serps');
    }
};
