<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rename Wirkungsraum → Portfolio auf Code-/Schema-Ebene (englische Konvention;
 * die deutsche UI-Beschriftung „Wirkungsraum" bleibt). Datenerhaltend: Tabellen
 * umbenennen, Pivot-FK-Spalte umbenennen, persistierte Org-Links (morph-Alias)
 * mitziehen. Läuft für Prod (Tabellen existieren) wie Fresh-Install (die
 * vorherige create/alter-Migration legt sie zuerst deutsch an).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('seo_wirkungsraeume') && ! Schema::hasTable('seo_portfolios')) {
            Schema::rename('seo_wirkungsraeume', 'seo_portfolios');
        }

        if (Schema::hasTable('seo_wirkungsraum_urls') && ! Schema::hasTable('seo_portfolio_urls')) {
            Schema::rename('seo_wirkungsraum_urls', 'seo_portfolio_urls');
        }

        if (Schema::hasColumn('seo_portfolio_urls', 'wirkungsraum_id')) {
            Schema::table('seo_portfolio_urls', function (Blueprint $table) {
                $table->renameColumn('wirkungsraum_id', 'portfolio_id');
            });
        }

        // Persistierte Org-Links (organization_dimension_links.linkable_type = morph-Alias) mitziehen.
        if (Schema::hasTable('organization_dimension_links')) {
            DB::table('organization_dimension_links')
                ->where('linkable_type', 'seo_wirkungsraum')
                ->update(['linkable_type' => 'seo_portfolio']);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('seo_portfolio_urls', 'portfolio_id')) {
            Schema::table('seo_portfolio_urls', function (Blueprint $table) {
                $table->renameColumn('portfolio_id', 'wirkungsraum_id');
            });
        }

        if (Schema::hasTable('seo_portfolio_urls') && ! Schema::hasTable('seo_wirkungsraum_urls')) {
            Schema::rename('seo_portfolio_urls', 'seo_wirkungsraum_urls');
        }

        if (Schema::hasTable('seo_portfolios') && ! Schema::hasTable('seo_wirkungsraeume')) {
            Schema::rename('seo_portfolios', 'seo_wirkungsraeume');
        }

        if (Schema::hasTable('organization_dimension_links')) {
            DB::table('organization_dimension_links')
                ->where('linkable_type', 'seo_portfolio')
                ->update(['linkable_type' => 'seo_wirkungsraum']);
        }
    }
};
