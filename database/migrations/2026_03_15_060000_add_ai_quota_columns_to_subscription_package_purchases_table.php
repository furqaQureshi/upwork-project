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
        if (! Schema::hasTable('subscription_package_purchases')) {
            return;
        }

        $hasUsedAiItems = Schema::hasColumn('subscription_package_purchases', 'used_ai_items');
        $hasRemainingAiItems = Schema::hasColumn('subscription_package_purchases', 'remaining_ai_items');

        if ($hasUsedAiItems && $hasRemainingAiItems) {
            return;
        }

        Schema::table('subscription_package_purchases', function (Blueprint $table) use ($hasUsedAiItems, $hasRemainingAiItems) {
            if (! $hasUsedAiItems) {
                $table->unsignedInteger('used_ai_items')->default(0);
            }

            if (! $hasRemainingAiItems) {
                $table->unsignedInteger('remaining_ai_items')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('subscription_package_purchases')) {
            return;
        }

        $columnsToDrop = [];

        if (Schema::hasColumn('subscription_package_purchases', 'used_ai_items')) {
            $columnsToDrop[] = 'used_ai_items';
        }

        if (Schema::hasColumn('subscription_package_purchases', 'remaining_ai_items')) {
            $columnsToDrop[] = 'remaining_ai_items';
        }

        if ($columnsToDrop === []) {
            return;
        }

        Schema::table('subscription_package_purchases', function (Blueprint $table) use ($columnsToDrop) {
            $table->dropColumn($columnsToDrop);
        });
    }
};
