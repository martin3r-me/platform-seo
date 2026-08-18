<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2-Spine (3/3): Präsenz = die Messung je Surface × Zeit. Ersetzt „Ranking"
 * durch Multi-Surface-Präsenz: sind wir da / zitiert, an welcher Position, und
 * wie viel des Antwort-Raums gehört uns (share_of_answer). Siehe
 * docs/NORDSTERN-v2.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_answer_presence', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('entity_id')->index();
            $table->unsignedBigInteger('answer_unit_id')->nullable()->index();

            $table->string('surface');                       // serp|ai_overview|chatgpt|perplexity|knowledge_panel
            $table->boolean('present')->default(false);
            $table->integer('position')->nullable();
            $table->boolean('cited')->default(false);
            $table->string('citation_url')->nullable();
            $table->decimal('share_of_answer', 5, 2)->nullable(); // %
            $table->timestamp('checked_at')->nullable();

            $table->timestamps();
            $table->index(['entity_id', 'surface', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_answer_presence');
    }
};
