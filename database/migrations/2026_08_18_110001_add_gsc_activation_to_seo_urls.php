<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GSC symmetrisch zu Plausible: per-URL Opt-in (gsc_enabled) + explizite
 * Property-URL (gsc_property), analog plausible_enabled/plausible_site_id.
 * Default enabled=true, damit die bisherige Auto-Domain-Zuordnung erhalten
 * bleibt; die Property überschreibt das Domain-Matching (Alias-Fälle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            if (! Schema::hasColumn('seo_urls', 'gsc_enabled')) {
                $table->boolean('gsc_enabled')->default(true)->after('gsc_fetched_at');
            }
            if (! Schema::hasColumn('seo_urls', 'gsc_property')) {
                $table->string('gsc_property')->nullable()->after('gsc_enabled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            foreach (['gsc_enabled', 'gsc_property'] as $col) {
                if (Schema::hasColumn('seo_urls', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
