<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Verknüpft gefeuerte Signale mit ihrer Definition. Nullable, damit der Bestand
 * (regelgenerierte Signale ohne Definition) gültig bleibt — Migrationspfad, kein Bruch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_signals', function (Blueprint $table) {
            $table->unsignedBigInteger('signal_definition_id')->nullable()->after('team_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_signals', function (Blueprint $table) {
            $table->dropColumn('signal_definition_id');
        });
    }
};
