<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content-Brief-Datenkern im SEO-Modul (aus brands portiert, entkoppelt).
 *
 * Kein brand_id (der Knoten ist der Anker, D3), kein seo_board_id (das Board
 * entfällt, D2). Team-owned; die Verlinkung an Organisations-Knoten läuft über
 * den Dimension-Link-Layer (Alias seo_content_brief), nicht über eine Spalte.
 * Der Content-Lifecycle (status, target_url) lebt hier — nicht am Keyword.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_content_briefs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('content_type', 30)->default('guide');          // pillar|how-to|listicle|faq|comparison|deep-dive|guide
            $table->string('search_intent', 30)->default('informational');  // informational|commercial|transactional|navigational
            $table->string('status', 20)->default('draft');                 // draft|briefed|in_production|review|published
            $table->string('target_slug')->nullable();
            $table->string('target_url', 2048)->nullable();                 // publizierte URL → wird zur seo_url (Loop)
            $table->unsignedInteger('target_word_count')->nullable();
            $table->integer('order')->default(0);
            $table->boolean('done')->default(false);
            $table->timestamp('done_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['team_id', 'order']);
        });

        Schema::create('seo_content_brief_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->unsignedInteger('order')->default(0);
            $table->string('heading');
            $table->string('heading_level', 4)->default('h2');  // h2|h3|h4
            $table->text('description')->nullable();
            $table->json('target_keywords')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['content_brief_id', 'order']);
        });

        Schema::create('seo_content_brief_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->foreignId('target_content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->string('link_type', 30);   // pillar_to_cluster|cluster_to_pillar|related|see_also
            $table->string('anchor_hint')->nullable();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->unique(['source_content_brief_id', 'target_content_brief_id', 'link_type'], 'seo_cb_links_unique');
        });

        Schema::create('seo_content_brief_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->string('note_type', 30);   // instruction|source|constraint|example|avoid
            $table->text('content');
            $table->unsignedInteger('order')->default(0);
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['content_brief_id', 'note_type']);
            $table->index(['content_brief_id', 'order']);
        });

        // Pivot Brief ↔ Cluster (primary|secondary|supporting).
        Schema::create('seo_content_brief_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->foreignId('cluster_id')->constrained('seo_keyword_clusters')->cascadeOnDelete();
            $table->string('role', 20)->default('primary');  // primary|secondary|supporting
            $table->timestamps();

            $table->unique(['content_brief_id', 'cluster_id'], 'seo_cb_clusters_unique');
            $table->index(['cluster_id']);
            $table->index(['role']);
        });

        Schema::create('seo_content_brief_revisions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('content_brief_id')->constrained('seo_content_briefs')->cascadeOnDelete();
            $table->string('revision_type', 30)->default('optimization');
            $table->text('summary');
            $table->json('metrics_before')->nullable();
            $table->json('metrics_after')->nullable();
            $table->json('changes')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('revised_at');
            $table->timestamps();

            $table->index(['content_brief_id', 'revised_at']);
            $table->index(['revision_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_content_brief_revisions');
        Schema::dropIfExists('seo_content_brief_clusters');
        Schema::dropIfExists('seo_content_brief_notes');
        Schema::dropIfExists('seo_content_brief_links');
        Schema::dropIfExists('seo_content_brief_sections');
        Schema::dropIfExists('seo_content_briefs');
    }
};
