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
            if (!Schema::hasColumn('subscription_packages', 'allows_ai')) {
                $table->boolean('allows_ai')->default(false)->after('allows_call');
            }
            
            if (!Schema::hasColumn('subscription_packages', 'ai_usage_limit_type')) {
                $table->enum('ai_usage_limit_type', ['unlimited', 'limited'])->default('unlimited')->after('allows_ai');
            }
            
            if (!Schema::hasColumn('subscription_packages', 'ai_usage_limit_count')) {
                $table->integer('ai_usage_limit_count')->nullable()->after('ai_usage_limit_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table) {
            if (Schema::hasColumn('subscription_packages', 'allows_ai')) {
                $table->dropColumn('allows_ai');
            }
            
            if (Schema::hasColumn('subscription_packages', 'ai_usage_limit_type')) {
                $table->dropColumn('ai_usage_limit_type');
            }
            
            if (Schema::hasColumn('subscription_packages', 'ai_usage_limit_count')) {
                $table->dropColumn('ai_usage_limit_count');
            }
        });
    }
};
