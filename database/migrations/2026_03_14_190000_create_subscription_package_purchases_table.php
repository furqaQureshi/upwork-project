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
        Schema::create('subscription_package_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20)->index();
            $table->string('merchant_order_id')->unique();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('INR');
            $table->enum('status', ['initiated', 'paid', 'failed', 'expired', 'refunded'])->default('initiated')->index();
            $table->unsignedInteger('used_items')->default(0);
            $table->unsignedInteger('remaining_items')->nullable();
            $table->unsignedInteger('used_ai_items')->default(0);
            $table->unsignedInteger('remaining_ai_items')->nullable();
            $table->timestamp('package_started_at')->nullable();
            $table->timestamp('package_expires_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->json('meta')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'spp_user_status_idx');
            $table->index(['subscription_package_id', 'status'], 'spp_package_status_idx');
            $table->index(['package_expires_at', 'status'], 'spp_expires_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_package_purchases');
    }
};
