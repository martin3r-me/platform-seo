<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * v2-Spine (2/3): Antwort-Einheit = unser Asset — der atomare, strukturierte
 * Antwort-Baustein, den WIR für eine Entität besitzen. Liegt auf einer Seite
 * (url_id = Behälter), gehört einem Wirkungsraum, kann eine SOLL-Spezifikation
 * (brief_id) tragen. Siehe docs/NORDSTERN-v2.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_answer_units', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('entity_id')->constrained('seo_entities')->cascadeOnDelete();
            $table->unsignedBigInteger('url_id')->nullable()->index();       // Host-Seite (Behälter)
            $table->unsignedBigInteger('portfolio_id')->nullable()->index(); // Wirkungsraum
            $table->unsignedBigInteger('brief_id')->nullable();              // SOLL-Spezifikation

            $table->text('claim')->nullable();               // der Kern-Antwort-Baustein
            $table->text('evidence')->nullable();            // Beleg/Quelle
            $table->string('schema_type')->nullable();       // FAQPage|HowTo|Product|...
            $table->string('status')->default('draft');      // draft|live|stale
            $table->timestamp('verified_at')->nullable();    // Frische
            $table->string('content_hash')->nullable();      // IST-Version

            $table->timestamps();
            $table->index(['portfolio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_answer_units');
    }
};
