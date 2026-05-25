<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_keyword_competitors', function (Blueprint $table) {
            $table->unsignedBigInteger('team_id')->after('keyword_id')->index()->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('seo_keyword_competitors', function (Blueprint $table) {
            $table->dropColumn('team_id');
        });
    }
};
