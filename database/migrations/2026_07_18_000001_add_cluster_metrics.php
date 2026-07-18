<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zeitreihe der Cluster-Erfolgsmessung — je Cluster und Tag (Trajektorie).
        Schema::create('seo_cluster_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('seo_keyword_clusters')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('keyword_count')->default(0);
            $table->unsignedInteger('covered_keywords')->default(0);   // Keywords mit rankender eigener URL
            $table->decimal('coverage_pct', 5, 2)->default(0);          // Abdeckungsgrad %
            $table->unsignedInteger('top3_count')->default(0);
            $table->unsignedInteger('top10_count')->default(0);
            $table->decimal('avg_position', 5, 2)->nullable();          // Ø beste eigene Position (abgedeckt)
            $table->decimal('visibility', 12, 4)->default(0);           // volumen-gewichtete Sichtbarkeit
            $table->unsignedInteger('clicks')->default(0);              // GSC-Clicks (30d)
            $table->unsignedInteger('visitors')->default(0);           // Plausible-Visitors der Cluster-URLs
            $table->unsignedTinyInteger('health_score')->nullable();    // 0–100 zusammengesetzt
            $table->timestamps();

            $table->unique(['cluster_id', 'snapshot_date'], 'seo_cluster_snap_unique');
            $table->index(['cluster_id', 'snapshot_date']);
        });

        // Denormalisierte "aktuell"-Werte auf dem Cluster — für schnelle Anzeige & Roll-up.
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->unsignedInteger('keyword_count')->default(0)->after('order');
            $table->unsignedInteger('covered_keywords')->default(0)->after('keyword_count');
            $table->decimal('coverage_pct', 5, 2)->default(0)->after('covered_keywords');
            $table->unsignedInteger('top3_count')->default(0)->after('coverage_pct');
            $table->unsignedInteger('top10_count')->default(0)->after('top3_count');
            $table->decimal('avg_position', 5, 2)->nullable()->after('top10_count');
            $table->decimal('visibility', 12, 4)->default(0)->after('avg_position');
            $table->unsignedInteger('clicks_30d')->default(0)->after('visibility');
            $table->unsignedInteger('visitors_30d')->default(0)->after('clicks_30d');
            $table->unsignedTinyInteger('health_score')->nullable()->after('visitors_30d');
            $table->timestamp('measured_at')->nullable()->after('health_score');
        });
    }

    public function down(): void
    {
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->dropColumn([
                'keyword_count', 'covered_keywords', 'coverage_pct', 'top3_count', 'top10_count',
                'avg_position', 'visibility', 'clicks_30d', 'visitors_30d', 'health_score', 'measured_at',
            ]);
        });

        Schema::dropIfExists('seo_cluster_snapshots');
    }
};
