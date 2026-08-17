<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC-Verlauf: je URL je Snapshot-Tag die 28-Tage-Kennzahlen (Clicks,
 * Impressionen, CTR, Ø-Position). Damit die echte Google-Sichtbarkeit zur
 * ENTWICKLUNG wird, nicht nur Momentaufnahme. Geschrieben vom GscCollector —
 * event-getrieben im Takt der Datensammlung (Snapshot-Kadenz-Leitsatz),
 * analog zu seo_conversion_snapshots.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_gsc_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('clicks_28d')->default(0);
            $table->unsignedInteger('impressions_28d')->default(0);
            $table->decimal('ctr', 6, 4)->nullable();
            $table->decimal('avg_position', 6, 2)->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'snapshot_date']);
            $table->index(['url_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_gsc_snapshots');
    }
};
