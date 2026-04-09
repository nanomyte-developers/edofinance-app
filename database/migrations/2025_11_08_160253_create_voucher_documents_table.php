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
        Schema::create('voucher_documents', function (Blueprint $table) {
            $table->id();

            // --- Relationship Link to Parent Voucher ---
            $table->foreignId('voucher_id')
                //   ->constrained('vouchers')
                //   ->onDelete('cascade') // If the voucher is deleted, delete its document records
                  ->comment('Foreign key linking the document to its parent voucher');
            
            // --- Document Details ---
            $table->string('file_name')->comment('Original name of the uploaded file');
            $table->string('file_path')->comment('Storage path (e.g., S3 or local disk path)');
            $table->string('mime_type', 200)->comment('Type of file (e.g., application/pdf, image/jpeg)');
            $table->string('document_type')->comment('Type of document (e.g., Invoice, Contract, Receipt)');
            
            // Optional: for verification
            $table->foreignId('uploaded_by_user_id')
                  ->nullable()
                  ->comment('User who uploaded this document'); // Add ->constrained('users') if you have a users table

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_documents');
    }
};