<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manuelles Opt-in für Plausible auf der (Parent-)URL: der Nutzer weiß,
        // welche Domains in Plausible liegen, und hakt sie aktiv an. Der Collector
        // probt dann nicht mehr blind alle Domains (viel 401-Rauschen), sondern
        // sammelt nur für aktivierte Domains. plausible_site_id erlaubt eine
        // abweichende Site-ID; default ist die Domain.
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->boolean('plausible_enabled')->default(false)->after('pageviews_30d');
            $table->string('plausible_site_id')->nullable()->after('plausible_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn(['plausible_enabled', 'plausible_site_id']);
        });
    }
};
