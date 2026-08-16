<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Semantische Karte je Wirkungsraum (die „Wirkungsraum-Linse" auf die Keyword-
 * Vektoren): Nachbarschaften, Ausreißer, themenferne Keywords. Async gebaut
 * (N Qdrant-Suchen), deshalb persistiert — die UI liest nur das Ergebnis.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_portfolios', function (Blueprint $table) {
            $table->string('semantic_status')->nullable()->after('clustering_result');
            $table->json('semantic_map')->nullable()->after('semantic_status');
            $table->timestamp('semantic_built_at')->nullable()->after('semantic_map');
        });
    }

    public function down(): void
    {
        Schema::table('seo_portfolios', function (Blueprint $table) {
            $table->dropColumn(['semantic_status', 'semantic_map', 'semantic_built_at']);
        });
    }
};
