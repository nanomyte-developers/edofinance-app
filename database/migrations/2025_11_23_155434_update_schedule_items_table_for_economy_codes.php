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
        Schema::table('schedule_items', function (Blueprint $table) {
            // Remove old economy_head_code column
            $table->dropColumn('economy_head_code');
            
            // Add new columns for Economic Codes
            $table->foreignId('economy_code_id')->nullable()->after('serial_number');
            $table->foreignId('economy_code_item_id')->nullable()->after('economy_code_id');
            
            // Add foreign key constraints
            // $table->foreign('economy_code_id')->references('id')->on('economy_codes')->onDelete('restrict');
            // $table->foreign('economy_code_item_id')->references('id')->on('economy_code_items')->onDelete('restrict');
            
            // Also rename item_date to payment_date if needed (based on your service class)
            // $table->renameColumn('item_date', 'payment_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('schedule_items', function (Blueprint $table) {
            // Reverse the changes
            $table->dropForeign(['economy_code_id']);
            $table->dropForeign(['economy_code_item_id']);
            
            $table->dropColumn(['economy_code_id', 'economy_code_item_id']);
            
            // Add back the old column
            $table->string('economy_head_code')->after('serial_number');
            
            // Reverse rename
            // $table->renameColumn('payment_date', 'item_date');
        });
    }
};
