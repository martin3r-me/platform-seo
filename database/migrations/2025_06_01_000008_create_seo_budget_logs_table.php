<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_budget_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('seo_projects')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 50);
            $table->unsignedInteger('keyword_count');
            $table->unsignedInteger('cost_cents');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_budget_logs');
    }
};
