<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_url_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('seo_url_list_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('list_id')->constrained('seo_url_lists')->cascadeOnDelete();
            $table->foreignId('url_id')->constrained('seo_urls')->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();

            $table->unique(['list_id', 'url_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_url_list_entries');
        Schema::dropIfExists('seo_url_lists');
    }
};
