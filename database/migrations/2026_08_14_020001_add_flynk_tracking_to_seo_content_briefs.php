<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Produktions-Tracking für Content-Briefs (SEO ↔ Flynk-Loop, docs/CONTENT-BRIEF-TRACKING.md).
 *
 * Vorwärts-Referenz: die IDs der Flynk-Aufgabe/-Dokument, die aus dem Brief entstehen.
 * Rückwärts-Verifikation: published_url wird beim Crawl über den x-content-brief-Marker
 * bestätigt und die Seite als eigene, getrackte URL registriert.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_content_briefs', function (Blueprint $table) {
            $table->string('external_project_ref')->nullable()->after('status');
            $table->string('external_task_ref')->nullable()->after('external_project_ref');
            $table->string('external_document_ref')->nullable()->after('external_task_ref');
            $table->string('published_url', 1024)->nullable()->after('external_document_ref');
            $table->timestamp('published_at')->nullable()->after('published_url');
        });
    }

    public function down(): void
    {
        Schema::table('seo_content_briefs', function (Blueprint $table) {
            $table->dropColumn([
                'external_project_ref',
                'external_task_ref',
                'external_document_ref',
                'published_url',
                'published_at',
            ]);
        });
    }
};
