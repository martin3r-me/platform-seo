<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei Conversion-Sichten: conversions_30d = GESAMT (Geschäftswert der Property,
 * inkl. App/Direkt/Referral) · organic_* = der reine SEO-Anteil (organische
 * Suche). Endpunkt-Properties (Buchung) haben viel Gesamt, wenig organisch —
 * beides ist wichtig zu sehen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('organic_conversions_30d')->default(0)->after('conversion_rate');
            $table->decimal('organic_conversion_rate', 5, 2)->nullable()->after('organic_conversions_30d');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['organic_conversions_30d', 'organic_conversion_rate']);
        });
    }
};
