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
        Schema::table('listings', function (Blueprint $table) {
            $table->timestamp('featured_until')->nullable()->after('is_featured')->index();
            $table->foreignId('last_featured_payment_id')->nullable()->after('featured_until')->constrained('featured_ad_payments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_featured_payment_id');
            $table->dropColumn('featured_until');
        });
    }
};
