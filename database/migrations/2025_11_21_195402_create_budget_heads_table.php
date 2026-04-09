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
        Schema::create('budget_heads', function (Blueprint $table) {
            $table->id();

            // --- The Core Identity ---
            $table->string('code')->unique()->comment('The accounting code, e.g., 023300100100');
            $table->string('description')->comment('Human readable name, e.g., Office Stationery');
            
            // --- Classification ---
            $table->string('category')->default('recurrent')->comment('capital, recurrent, personnel, overhead');
            $table->boolean('is_active')->default(true);

            // --- Optional Restrictions ---
            // If null, this budget head is available to ALL MDAs (Global). 
            // If set, it's specific to that MDA.
            $table->foreignId('mda_id')
                  ->nullable()
                //   ->constrained('mdas')
                //   ->nullOnDelete()
                  ->comment('Optional: Limit this head to a specific MDA');

            // Optional: If budget heads change every year, link them. 
            // Usually, codes are static, but balances reset.
            // $table->foreignId('year_id')->nullable()->constrained('financial_years');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_heads');
    }
};