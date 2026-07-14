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
        if (! Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::table('push_subscriptions', function (Blueprint $table): void {
            if (! Schema::hasColumn('push_subscriptions', 'provider')) {
                $table->string('provider', 32)->default('webpush')->after('user_id');
                $table->index('provider');
            }

            if (! Schema::hasColumn('push_subscriptions', 'device_token')) {
                $table->string('device_token', 512)->nullable()->after('endpoint');
                $table->unique('device_token');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('push_subscriptions')) {
            return;
        }

        Schema::table('push_subscriptions', function (Blueprint $table): void {
            if (Schema::hasColumn('push_subscriptions', 'device_token')) {
                $table->dropUnique(['device_token']);
                $table->dropColumn('device_token');
            }

            if (Schema::hasColumn('push_subscriptions', 'provider')) {
                $table->dropIndex(['provider']);
                $table->dropColumn('provider');
            }
        });
    }
};
