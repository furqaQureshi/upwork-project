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
        Schema::create('seller_verification_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_verification_id')->constrained('seller_verifications')->cascadeOnDelete();
            $table->string('document_type', 50); // company_certificate, aadhar, pan
            $table->string('document_path');
            $table->string('document_number', 120)->nullable(); // For Aadhar/PAN
            $table->string('verification_status', 20)->default('pending'); // pending, verified, rejected
            $table->text('verification_note')->nullable();
            $table->timestamps();

            $table->index(['seller_verification_id', 'document_type']);
            $table->index(['verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seller_verification_documents');
    }
};
