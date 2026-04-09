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
        Schema::table('voucher_documents', function (Blueprint $table) {
            $table->string('file_size')->after('file_path')->comment('Original file size that is uploaded');
            $table->string('description')->after('document_type')->nullable()->comment('Any comment or description of the file)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_documents', function (Blueprint $table) {
            $table->dropColumn(['file_size', 'description']);
        });
    }
};
