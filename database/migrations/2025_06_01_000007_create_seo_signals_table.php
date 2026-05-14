<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_signals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('team_id')->index();
            $table->foreignId('project_id')->constrained('seo_projects')->cascadeOnDelete();
            $table->foreignId('keyword_id')->nullable()->constrained('seo_keywords')->nullOnDelete();
            $table->string('signal_type', 50);
            $table->string('severity', 10)->default('info');
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('metric_before', 12, 4)->nullable();
            $table->decimal('metric_after', 12, 4)->nullable();
            $table->decimal('metric_delta', 12, 4)->nullable();
            $table->date('detected_at');
            $table->string('status', 20)->default('new');
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status', 'detected_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_signals');
    }
};
