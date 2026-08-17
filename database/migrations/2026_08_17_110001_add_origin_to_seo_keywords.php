<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keyword-Herkunft. Bislang implizit (alle aus DataForSeo/kuratiert). Mit der
 * GSC-Query-Discovery kommen Keywords aus einer zweiten Welt (echte Google-
 * Anfragen, für die wir ranken) — die wollen wir als solche markieren:
 *  - 'gsc'         = aus GSC-Discovery promoviert (Volumen wird nachgezogen)
 *  - null/Bestand  = kuratiert / DataForSeo (unverändert)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->string('origin', 20)->nullable()->after('keyword')->index();
        });
    }

    public function down(): void
    {
        Schema::table('seo_keywords', function (Blueprint $table) {
            $table->dropIndex(['origin']);
            $table->dropColumn('origin');
        });
    }
};
