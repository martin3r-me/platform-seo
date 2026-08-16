<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Conversion-Verlauf: je URL je Snapshot-Tag die 30-Tage-Conversions + Rate.
 * Damit die Wirkung zur ENTWICKLUNG wird (steigt sie?), nicht nur Momentaufnahme.
 * Geschrieben vom PlausibleCollector — event-getrieben, im Takt der Datensammlung
 * (nicht blind-daily), gemäß Snapshot-Kadenz-Leitsatz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_conversion_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedInteger('conversions_30d')->default(0);
            $table->decimal('conversion_rate', 5, 2)->nullable();
            $table->timestamps();

            $table->unique(['url_id', 'snapshot_date']);
            $table->index(['url_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_conversion_snapshots');
    }
};
