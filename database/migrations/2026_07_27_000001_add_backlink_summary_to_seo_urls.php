<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Denormalisierte Domain-Autoritäts-Signale pro URL aus dem DataForSEO
        // Backlinks-Summary-Endpoint (/v3/backlinks/summary/live). backlink_count
        // existiert bereits; hier kommen die aussagekräftigeren Aggregate dazu.
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->unsignedInteger('referring_domains')->nullable()->after('backlink_count');
            $table->unsignedSmallInteger('backlink_rank')->nullable()->after('referring_domains'); // Autorität 0–1000
            $table->unsignedTinyInteger('backlink_spam_score')->nullable()->after('backlink_rank'); // 0–100
            $table->unsignedInteger('broken_backlinks')->nullable()->after('backlink_spam_score');
            $table->timestamp('backlinks_fetched_at')->nullable()->after('broken_backlinks');
        });
    }

    public function down(): void
    {
        Schema::table('seo_urls', function (Blueprint $table) {
            $table->dropColumn([
                'referring_domains',
                'backlink_rank',
                'backlink_spam_score',
                'broken_backlinks',
                'backlinks_fetched_at',
            ]);
        });
    }
};
