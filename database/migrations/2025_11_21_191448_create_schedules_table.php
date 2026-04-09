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
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();

            // --- Organization & Period Links ---
            $table->foreignId('year_id')
                  ->comment('Financial Year link');
            
            $table->foreignId('mda_id')
                  ->comment('The Ministry/Agency owning this schedule');

            // --- Source of Funds (The "CODE: 0233..." from your image) ---
            // Assuming you have a budget_heads table. If not, change to: $table->string('budget_code');
            $table->foreignId('budget_code_id')
                  ->nullable()
                  ->comment('Links to the Budget Head code (e.g., 023300100100)');

            $table->foreignId('created_by_user_id')
                  ->nullable()
                  ->comment('User who raised the schedule');

            // --- Schedule Details ---
            $table->string('schedule_number')->unique()->comment('e.g., SCH. NO.M.M.E/15/2025');
            $table->date('schedule_date')->default(now());
            $table->decimal('total_amount', 15, 2)->default(0.00);
            
            // --- Workflow Status ---
            $table->string('status')->default('Draft')->comment('Draft, Processed, Voucher Raised, etc.');
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
