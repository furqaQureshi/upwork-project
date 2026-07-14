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
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'verification_document_type')) {
                $table->string('verification_document_type', 100)->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('users', 'verification_document_number')) {
                $table->string('verification_document_number', 120)->nullable()->after('verification_document_type');
            }

            if (! Schema::hasColumn('users', 'verification_document_path')) {
                $table->string('verification_document_path')->nullable()->after('verification_document_number');
            }

            if (! Schema::hasColumn('users', 'seller_verification_status')) {
                $table->string('seller_verification_status', 20)->default('unsubmitted')->index()->after('verification_document_path');
            }

            if (! Schema::hasColumn('users', 'seller_verified_at')) {
                $table->timestamp('seller_verified_at')->nullable()->index()->after('seller_verification_status');
            }

            if (! Schema::hasColumn('users', 'seller_verification_note')) {
                $table->text('seller_verification_note')->nullable()->after('seller_verified_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'verification_document_type',
                'verification_document_number',
                'verification_document_path',
                'seller_verification_status',
                'seller_verified_at',
                'seller_verification_note',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
