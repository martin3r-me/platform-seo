<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('domain', 255)->nullable();
            $table->string('industry_preset', 50)->nullable();
            $table->unsignedInteger('budget_limit_cents')->default(5000);
            $table->unsignedInteger('budget_spent_cents')->default(0);
            $table->unsignedInteger('refresh_interval_hours')->default(168);
            $table->timestamp('next_refresh_at')->nullable();
            $table->unsignedBigInteger('dataforseo_connection_id')->nullable();
            $table->unsignedInteger('location_code')->default(2276);
            $table->unsignedInteger('language_code')->default(1001);
            $table->string('clustering_status', 20)->nullable();
            $table->json('clustering_result')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_projects');
    }
};
