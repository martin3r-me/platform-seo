<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wirkungsraum — der Steuer-Scope (im Gegensatz zur Liste = Beobachtung).
 *
 * SEO-native Entität: eine Zusammenstellung KONTROLLIERTER URLs + ein ZIEL
 * (max. Sichtbarkeit im Verbund für definierte Themen). Komponiert hier im
 * SEO-Modul (SEO-getrieben, darf quer zum Org-Baum liegen) — der Org-Baum ist
 * Berichts-Ziel via Dimension-Link (Alias seo_wirkungsraum), nicht Bauplan.
 * Verschachtelbar (parent_id): Wirkungsräume gruppieren → Verbund; Analyse
 * „überlappt oder komplementiert?" rekursiv auf jeder Ebene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_wirkungsraeume', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->text('goal')->nullable();                 // das ZIEL: welche Themen dominiert werden sollen
            $table->foreignId('parent_id')->nullable()        // Verschachtelung: Wirkungsraum → Verbund
                ->constrained('seo_wirkungsraeume')->nullOnDelete();
            $table->timestamps();

            $table->unique(['team_id', 'slug']);
            $table->index(['team_id', 'parent_id']);
        });

        Schema::create('seo_wirkungsraum_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wirkungsraum_id')->constrained('seo_wirkungsraeume')->cascadeOnDelete();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->string('role', 20)->nullable();           // z.B. core|support (Owner vs. Zulieferer)
            $table->timestamp('added_at')->useCurrent();

            $table->unique(['wirkungsraum_id', 'url_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_wirkungsraum_urls');
        Schema::dropIfExists('seo_wirkungsraeume');
    }
};
