<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maßnahmen-Log je Wirkungsraum — das Atom der Steuer-Produktionslinie.
 * Jede Maßnahme hat einen standardisierten Typ, ein Ziel (Property/Cluster),
 * eine Begründung, eine Quelle und einen Status. Die Entscheidung (angenommen/
 * abgelehnt+Grund) bleibt als persistenter Wirkungsraum-Kontext erhalten:
 * abgelehntes wird nicht neu vorgeschlagen (Dedup über source_key).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_portfolio_measures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('portfolio_id')->constrained('seo_portfolios')->cascadeOnDelete();

            $table->string('type');                 // brief_existing|brief_new_subpage|change_page|retire_page|new_property|structure_owner
            $table->unsignedBigInteger('target_url_id')->nullable();
            $table->unsignedBigInteger('target_cluster_id')->nullable();

            $table->string('title');
            $table->text('rationale')->nullable();

            $table->string('source')->default('signal');   // ai|signal|human
            $table->string('source_key')->nullable();       // z. B. conflict:cluster:42 (Dedup)
            $table->integer('score')->default(0);           // ordnet die Prioritäts-Queue
            $table->string('route')->default('internal');   // flynk|internal|human
            $table->string('status')->default('proposed');  // proposed|accepted|released|done|rejected
            $table->text('reject_reason')->nullable();

            $table->timestamp('decided_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['portfolio_id', 'status']);
            $table->index(['portfolio_id', 'source_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_portfolio_measures');
    }
};
