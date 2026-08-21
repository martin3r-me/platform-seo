<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * seasonality_index war decimal(3,2) → max 9.99. Echte Saisonalität (Peak ÷
 * Durchschnitt) kann bei stark saisonalen Terms deutlich höher liegen (z. B.
 * ein März-Peak von 22.200 gegen ~1.900 Schnitt = ~11.6×) und sprengte die
 * Spalte beim Schreiben. Auf decimal(6,2) geweitet (bis 9999.99).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->decimal('seasonality_index', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->decimal('seasonality_index', 3, 2)->nullable()->change();
        });
    }
};
