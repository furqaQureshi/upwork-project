<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'seller_type')) {
                $table->string('seller_type', 30)->nullable()->after('seller_verification_note')->index();
            }
        });

        Schema::table('subscription_packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('subscription_packages', 'seller_type')) {
                $table->string('seller_type', 30)->nullable()->after('is_seller_verification')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('subscription_packages', function (Blueprint $table): void {
            if (Schema::hasColumn('subscription_packages', 'seller_type')) {
                $table->dropColumn('seller_type');
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'seller_type')) {
                $table->dropColumn('seller_type');
            }
        });
    }
};
