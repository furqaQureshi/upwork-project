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
        Schema::create('featured_ad_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('gateway', 20)->index();
            $table->string('merchant_order_id')->unique();
            $table->string('provider_order_id')->nullable()->index();
            $table->string('provider_payment_id')->nullable()->index();
            $table->string('provider_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 8)->default('INR');
            $table->unsignedInteger('feature_days')->default(7);
            $table->enum('status', ['initiated', 'paid', 'failed', 'expired'])->default('initiated')->index();
            $table->json('meta')->nullable();
            $table->json('callback_payload')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('featured_ad_payments');
    }
};
