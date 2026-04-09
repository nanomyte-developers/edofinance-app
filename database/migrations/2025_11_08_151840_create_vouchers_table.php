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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();

            // --- Required Year Link ---
            $table->foreignId('year_id')
                //   ->constrained('years')
                  ->comment('Year the voucher was created in (Financial Period)');

            // --- Relationship Links ---
            $table->foreignId('mda_id')
                //   ->constrained('mdas')
                  ->comment('The MDA submitting the voucher');
            
            // Assuming a users table for tracking who initiated the submission
            $table->foreignId('created_by_user_id')
                  ->nullable()
                  ->comment('User who initially submitted the voucher'); // Add ->constrained('users') if you have a users table

            // --- Voucher Details ---
            $table->string('voucher_number')->unique()->comment('Unique voucher identifier');
            $table->date('voucher_date');
            $table->decimal('total_amount', 15, 2)->default(0.00);
            $table->text('narration')->comment('Brief explanation of the purpose of the voucher');

            // --- Workflow and Status (Reflecting the multi-stage approval flow) ---
            $table->string('status')->default('Draft')->comment('Current stage: Draft, Pending Audit, Pending AG, Approved, Rejected, etc.');
            $table->text('rejection_reason')->nullable()->comment('Reason provided if the voucher was rejected at any stage.');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};