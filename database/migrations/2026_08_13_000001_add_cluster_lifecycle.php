<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cluster-Lifecycle (s. docs/CLUSTER-PLAYBOOK.md):
 * - status: candidate → active → monitored → stalled → archived
 * - pillar_url_id: die eine Ziel-URL eines aktiven Clusters
 * - penetration: Erfolgs-Quotient „Durchdringung" (0–100), ersetzt health_score
 *   semantisch; health_score bleibt vorerst als Alias erhalten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->string('status', 20)->default('candidate')->index()->after('order');
            $table->foreignId('pillar_url_id')->nullable()->after('status')
                ->constrained('seo_urls')->nullOnDelete();
            $table->unsignedTinyInteger('penetration')->nullable()->after('health_score');
            $table->timestamp('status_changed_at')->nullable()->after('measured_at');
        });

        Schema::table('seo_cluster_snapshots', function (Blueprint $table) {
            $table->unsignedTinyInteger('penetration')->nullable()->after('health_score');
        });
    }

    public function down(): void
    {
        Schema::table('seo_keyword_clusters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pillar_url_id');
            $table->dropColumn(['status', 'penetration', 'status_changed_at']);
        });

        Schema::table('seo_cluster_snapshots', function (Blueprint $table) {
            $table->dropColumn('penetration');
        });
    }
};
