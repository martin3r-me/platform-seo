<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Signal-Definitionen als DB-Objekt — das Herz des SEO-nativen Signal-Systems.
 * Form vom Organization-Modul geliehen (pattern_type / conditions / scope / frequency),
 * aber SEO-eigen und domänennativ. Siehe docs/SIGNALS-CONCEPT.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_signal_definitions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->string('name');
            $table->text('description')->nullable();

            // Welches domänennative Muster? striking_distance, position_drop,
            // cannibalization, thin_content, ... (selektiert den Evaluator).
            $table->string('pattern_type', 50);
            // Tunbare Parameter des Musters (min_position, min_volume, drop_threshold, ...).
            $table->json('conditions')->nullable();

            // Geltungsbereich: all | entity | entity_subtree | list
            $table->string('scope_type', 20)->default('all');
            // {entity_id} bzw. {list_id} — je nach scope_type.
            $table->json('scope_value')->nullable();

            // Wann ausgewertet: every_snapshot | daily | weekly
            $table->string('frequency', 20)->default('daily');
            // Default-Severity beim Feuern (Evaluator darf anheben): info | watch | warning | critical
            $table->string('severity', 10)->default('warning');

            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'is_active']);
            $table->index(['team_id', 'pattern_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_signal_definitions');
    }
};
