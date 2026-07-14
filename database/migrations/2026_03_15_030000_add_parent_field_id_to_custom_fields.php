<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->foreignId('parent_field_id')
                ->nullable()
                ->after('category_id')
                ->constrained('custom_fields')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table): void {
            $table->dropForeign(['parent_field_id']);
            $table->dropColumn('parent_field_id');
        });
    }
};
