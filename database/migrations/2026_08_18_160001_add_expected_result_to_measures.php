<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maßnahme besser erklären: erwartetes Ergebnis (was soll die Maßnahme bewirken).
 * „Wo" liegt bereits in target_url_id/target_cluster_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_portfolio_measures', function (Blueprint $table) {
            if (! Schema::hasColumn('seo_portfolio_measures', 'expected_result')) {
                $table->text('expected_result')->nullable()->after('rationale');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_portfolio_measures', function (Blueprint $table) {
            if (Schema::hasColumn('seo_portfolio_measures', 'expected_result')) {
                $table->dropColumn('expected_result');
            }
        });
    }
};
