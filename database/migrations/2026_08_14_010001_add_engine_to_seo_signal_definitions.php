<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Macht die KI-Sparte im Datenmodell sichtbar (docs/SIGNALS-CONCEPT.md §6a):
 *  - engine: computed (berechnet, deterministisch) | ai (KI-nativ, später Rolle 2)
 *  - enrich_with_ai: berechnetes Signal zusätzlich per generativer KI anreichern (Rolle 1)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_signal_definitions', function (Blueprint $table) {
            $table->string('engine', 20)->default('computed')->after('pattern_type');
            $table->boolean('enrich_with_ai')->default(false)->after('engine');
        });
    }

    public function down(): void
    {
        Schema::table('seo_signal_definitions', function (Blueprint $table) {
            $table->dropColumn(['engine', 'enrich_with_ai']);
        });
    }
};
