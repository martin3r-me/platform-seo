<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2-Spine (1/3): Entität = das Nachfrage-Atom (was gefragt/gewusst wird —
 * nicht das Keyword). Siehe docs/NORDSTERN-v2.md. Brücke zu v1: cluster_id
 * (Cluster ≈ Entity-Bündel). offer/action sind der Transaktions-Slot für die
 * agent-actionable Zukunft (heute nullable/ungenutzt).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_entities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->string('name');
            $table->string('entity_type')->nullable();      // question|product|brand|place|concept
            $table->json('aliases')->nullable();
            $table->string('intent')->nullable();            // dominanter Intent (gewichtet Surfaces)
            $table->unsignedBigInteger('cluster_id')->nullable()->index(); // Brücke zu v1
            $table->integer('search_volume')->default(0);    // klassische Nachfrage
            $table->integer('ai_ask_volume')->default(0);    // KI-Nachfrage (geschätzt)

            // Transaktions-Slot (agent-actionable Zukunft — heute ungenutzt):
            $table->json('offer')->nullable();               // Preis/Verfügbarkeit/Konditionen
            $table->json('action')->nullable();              // schema.org Action / Buchungs-Endpoint

            $table->timestamps();
            $table->index(['team_id', 'entity_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_entities');
    }
};
