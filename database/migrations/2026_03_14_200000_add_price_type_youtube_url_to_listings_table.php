<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->enum('price_type', ['fixed', 'negotiable', 'free'])->default('fixed')->after('price');
            $table->string('youtube_url', 500)->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['price_type', 'youtube_url']);
        });
    }
};
