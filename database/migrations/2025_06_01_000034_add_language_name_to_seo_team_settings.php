<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_team_settings', function (Blueprint $table) {
            $table->string('language_name', 50)->nullable()->after('language_code');
        });
    }

    public function down(): void
    {
        Schema::table('seo_team_settings', function (Blueprint $table) {
            $table->dropColumn('language_name');
        });
    }
};
