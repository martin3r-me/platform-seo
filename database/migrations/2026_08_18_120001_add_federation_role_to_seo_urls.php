<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Föderations-Rolle je Property (URL) im Verbund: Fundament der Orchestrierung.
 * brand = Brand/Spoke (besitzt differenzierte Themen) · hub = Hub/Pillar
 * (zentrale Seite, sammelt Kopf-Nachfrage, verlinkt nach unten) · external =
 * außerhalb (anderes Feld, z. B. Agentur/Admin — spielt nicht im selben Spiel).
 * Null = noch nicht zugeordnet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            if (! Schema::hasColumn('seo_urls', 'federation_role')) {
                $table->string('federation_role')->nullable()->after('disposition_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            if (Schema::hasColumn('seo_urls', 'federation_role')) {
                $table->dropColumn('federation_role');
            }
        });
    }
};
