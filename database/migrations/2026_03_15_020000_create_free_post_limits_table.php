<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('free_post_limits', function (Blueprint $table) {
            $table->id();
            // NULL = applies to ALL categories (global rule)
            $table->foreignId('category_id')->nullable()->constrained('categories')->cascadeOnDelete();
            // Rolling window: how many past days to count
            $table->unsignedSmallInteger('window_days')->default(30);
            // Max ads allowed within that window
            $table->unsignedSmallInteger('limit_count')->default(1);
            $table->timestamps();

            // One rule per (category_id, window_days) combination - allow upsert-style management
            $table->unique(['category_id', 'window_days']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('free_post_limits');
    }
};
