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
        Schema::create('subscription_package_usages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('subscription_package_purchase_id');
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('usage_type', ['listing_create', 'featured_boost', 'ai_listing_draft', 'ai_price_recommendation', 'ai_compass_chat', 'ai_cv_match'])->index();
            $table->timestamp('consumed_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('subscription_package_purchase_id', 'spu_purchase_fk')
                ->references('id')
                ->on('subscription_package_purchases')
                ->cascadeOnDelete();
            $table->index(['subscription_package_purchase_id', 'usage_type'], 'spu_purchase_usage_idx');
            $table->index(['listing_id', 'usage_type'], 'spu_listing_usage_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_package_usages');
    }
};
