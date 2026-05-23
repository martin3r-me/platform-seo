<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_signals', function (Blueprint $table) {
            $table->foreignId('url_id')->nullable()->after('keyword_id')->constrained('seo_urls')->nullOnDelete();
            $table->index('url_id');
        });
    }

    public function down(): void
    {
        Schema::table('seo_signals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('url_id');
        });
    }
};
