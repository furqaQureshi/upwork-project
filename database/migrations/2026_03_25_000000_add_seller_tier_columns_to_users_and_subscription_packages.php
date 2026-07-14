<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_packages', 'seller_tier')) {
                $table->string('seller_tier', 40)->nullable()->after('is_seller_verification');
            }

            if (! Schema::hasColumn('subscription_packages', 'seller_badge_label')) {
                $table->string('seller_badge_label', 120)->nullable()->after('seller_tier');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'seller_type')) {
                $table->string('seller_type', 40)->nullable()->after('seller_verified_at');
            }

            if (! Schema::hasColumn('users', 'seller_active_package_id')) {
                $table->foreignId('seller_active_package_id')
                    ->nullable()
                    ->after('seller_type')
                    ->constrained('subscription_packages')
                    ->nullOnDelete();
            }
        });

        Schema::table('seller_verifications', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('seller_verifications', function (Blueprint $table): void {
            $table->foreignId('category_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'seller_active_package_id')) {
                $table->dropConstrainedForeignId('seller_active_package_id');
            }

            if (Schema::hasColumn('users', 'seller_type')) {
                $table->dropColumn('seller_type');
            }
        });

        Schema::table('subscription_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_packages', 'seller_badge_label')) {
                $table->dropColumn('seller_badge_label');
            }

            if (Schema::hasColumn('subscription_packages', 'seller_tier')) {
                $table->dropColumn('seller_tier');
            }
        });
    }
};