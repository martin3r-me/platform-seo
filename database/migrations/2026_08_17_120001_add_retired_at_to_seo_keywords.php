<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Abstellen" auf der Nachfrage-Achse: ein Keyword als Außenseiter/Rausch
 * stilllegen. Abgestellte Keywords fliegen aus der semantischen Karte
 * (Frontier) — thematisch irrelevant (kellner jobs, blähen kirschen), aber
 * umkehrbar (retired_at = null → wieder drin).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->timestamp('retired_at')->nullable()->after('origin')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->dropIndex(['retired_at']);
            $table->dropColumn('retired_at');
        });
    }
};
