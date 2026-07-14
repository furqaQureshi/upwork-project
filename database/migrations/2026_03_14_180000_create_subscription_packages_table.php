<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('subscription_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->enum('package_type', ['listing', 'featured']);
            $table->decimal('price', 12, 2);
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('final_price', 12, 2);
            $table->enum('package_duration_type', ['limited', 'unlimited'])->default('limited');
            $table->unsignedInteger('package_duration_days')->nullable();

            $table->enum('item_limit_type', ['limited', 'unlimited'])->default('limited');
            $table->unsignedInteger('item_limit_count')->nullable();

            $table->enum('listing_duration_type', ['standard', 'custom'])->default('standard');
            $table->unsignedInteger('listing_duration_days')->default(30);

            $table->enum('category_scope', ['global', 'specific'])->default('global');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();

            $table->json('key_points')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('allows_ai')->default(false);
            $table->enum('ai_usage_limit_type', ['limited', 'unlimited'])->default('limited');
            $table->unsignedInteger('ai_usage_limit_count')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['package_type', 'is_active']);
            $table->index('category_scope');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_packages');
    }
};
