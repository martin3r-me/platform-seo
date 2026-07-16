<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zeitreihe: Traffic pro URL und Tag (analog zu seo_url_gsc_data).
        // 'source' erlaubt künftig weitere Analytics-Quellen (GA4 etc.) auf derselben URL.
        Schema::create('seo_url_traffic', function (Blueprint $table) {
            $table->id();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->date('date');
            $table->string('source', 20)->default('plausible');
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('pageviews')->default(0);
            $table->decimal('bounce_rate', 5, 2)->default(0);   // Prozent 0–100
            $table->unsignedInteger('visit_duration')->default(0); // Ø Sekunden
            $table->timestamps();

            $table->unique(['url_id', 'date', 'source'], 'seo_url_traffic_unique');
            $table->index(['url_id', 'date']);
        });

        // Denormalisierte "aktuell"-Werte auf der URL selbst — für schnelle Anzeige.
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('visitors_30d')->default(0)->after('visibility_score');
            $table->unsignedInteger('pageviews_30d')->default(0)->after('visitors_30d');
            $table->timestamp('traffic_fetched_at')->nullable()->after('pageviews_30d');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['visitors_30d', 'pageviews_30d', 'traffic_fetched_at']);
        });

        Schema::dropIfExists('seo_url_traffic');
    }
};
