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
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            if (! Schema::hasColumn('messages', 'attachment_path')) {
                $table->string('attachment_path')->nullable();
            }

            if (! Schema::hasColumn('messages', 'attachment_name')) {
                $table->string('attachment_name')->nullable();
            }

            if (! Schema::hasColumn('messages', 'attachment_mime')) {
                $table->string('attachment_mime', 160)->nullable();
            }

            if (! Schema::hasColumn('messages', 'attachment_size')) {
                $table->unsignedBigInteger('attachment_size')->nullable();
            }

            if (! Schema::hasColumn('messages', 'attachment_kind')) {
                $table->string('attachment_kind', 24)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('messages')) {
            return;
        }

        Schema::table('messages', function (Blueprint $table): void {
            $columns = [
                'attachment_path',
                'attachment_name',
                'attachment_mime',
                'attachment_size',
                'attachment_kind',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('messages', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
