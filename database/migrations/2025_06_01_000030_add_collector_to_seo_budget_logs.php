<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seo_budget_logs', function (Blueprint $table) {
            $table->string('collector', 50)->nullable()->after('action');
        });
    }

    public function down(): void
    {
        Schema::table('seo_budget_logs', function (Blueprint $table) {
            $table->dropColumn('collector');
        });
    }
};
