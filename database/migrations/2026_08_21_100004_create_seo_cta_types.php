<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * CTA-Typen — die kuratierte Mechanik/Absicht eines Call-to-Action (anruf,
 * kontakt, angebot …). Bewusst als DATEN (Tabelle) statt config: per MCP
 * pflegbar (kein Deploy), team-scoped, wächst mit dem Feld. Ein SeoCta
 * referenziert genau einen Typ; wir steuern die Typen (Strategie), Flynk die
 * Platzierung/Copy (Handwerk). Bestehende Teams bekommen einen Default-Satz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_cta_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('team_id');
            $table->string('code', 40);              // anruf, kontakt, angebot …
            $table->string('label');
            $table->string('mechanism', 20)->default('link'); // tel | form | link | email
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['team_id', 'code']);
            $table->index(['team_id', 'active']);
        });

        // Default-Satz für bestehende Teams (danach per MCP pflegbar).
        $defaults = [
            ['anruf', 'Anruf', 'tel'],
            ['kontakt', 'Kontakt/Anfrage', 'form'],
            ['angebot', 'Angebot anfordern', 'form'],
            ['termin', 'Termin buchen', 'link'],
            ['menu', 'Menü/Speisekarte', 'link'],
            ['download', 'Download', 'link'],
            ['newsletter', 'Newsletter', 'form'],
            ['whatsapp', 'WhatsApp', 'link'],
            ['route', 'Anfahrt/Standort', 'link'],
            ['kauf', 'Kauf/Bestellen', 'link'],
        ];
        $now = now();
        foreach (DB::table('seo_team_settings')->pluck('team_id')->unique() as $teamId) {
            foreach ($defaults as $i => [$code, $label, $mechanism]) {
                DB::table('seo_cta_types')->insertOrIgnore([
                    'team_id' => $teamId,
                    'code' => $code,
                    'label' => $label,
                    'mechanism' => $mechanism,
                    'sort' => $i,
                    'active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_cta_types');
    }
};
