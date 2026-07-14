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
        Schema::table('subscription_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('subscription_packages', 'required_documents')) {
                // Add JSON column to store required documents for seller verification
                $table->json('required_documents')->nullable()->after('key_points');
            }
            
            if (!Schema::hasColumn('subscription_packages', 'is_seller_verification')) {
                // Flag to identify seller verification packages
                $table->boolean('is_seller_verification')->default(false)->index()->after('required_documents');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_packages', 'required_documents')) {
                $table->dropColumn('required_documents');
            }
            
            if (Schema::hasColumn('subscription_packages', 'is_seller_verification')) {
                $table->dropColumn('is_seller_verification');
            }
        });
    }
};
