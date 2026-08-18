<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * „Zurückbauen" auf der Angebots-Achse: eine Seite, für die wir NICHT (mehr)
 * ranken wollen — abschaffen (retire), umbauen (rebuild) oder auf ein anderes
 * Thema setzen (retarget). Das Flag macht die Absicht sichtbar (Werkbank) und
 * speist später den Flynk-Auftrag. Umkehrbar (disposition = null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->string('disposition', 20)->nullable()->after('status')->index();
            $table->timestamp('disposition_at')->nullable()->after('disposition');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropIndex(['disposition']);
            $table->dropColumn(['disposition', 'disposition_at']);
        });
    }
};
