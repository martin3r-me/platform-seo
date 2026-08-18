<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2 — Experiment-Loop je Antwort (docs/NORDSTERN-v2.md §4). Eine Optimierung
 * als messbares Experiment: Hypothese → Änderung → Baseline (Presence vorher) →
 * Ergebnis (nachher) → Verdict + Learning. A/B ehrlich = kontrolliertes
 * Vorher/Nachher + Kontrollgruppe, kein Split-Test.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_answer_experiments', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('portfolio_id')->nullable()->index();
            $table->unsignedBigInteger('entity_id')->nullable()->index();
            $table->unsignedBigInteger('answer_unit_id')->nullable()->index();
            $table->unsignedBigInteger('measure_id')->nullable();   // Auslöser (Posteingang)
            $table->unsignedBigInteger('brief_id')->nullable();     // SOLL-Spezifikation

            $table->text('hypothesis')->nullable();
            $table->text('change_summary')->nullable();
            $table->string('status')->default('planned');           // planned|running|done
            $table->string('verdict')->nullable();                  // worked|flat|hurt
            $table->json('baseline')->nullable();                   // Presence-Snapshot vorher
            $table->json('result')->nullable();                     // Presence-Snapshot nachher
            $table->text('learning')->nullable();

            $table->timestamp('applied_at')->nullable();
            $table->timestamp('measured_at')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_answer_experiments');
    }
};
