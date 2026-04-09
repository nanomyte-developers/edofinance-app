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
        Schema::create('voucher_items', function (Blueprint $table) {
            $table->id();

            // --- Relationship Link to Parent Voucher ---
            $table->foreignId('voucher_id')
                //   ->constrained('vouchers')
                //   ->onDelete('cascade') // If the voucher is deleted, delete its items
                  ->comment('Foreign key linking item to its parent voucher');
            
            // --- Item Details ---
            $table->string('description');
            $table->string('details')->nullable();
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('sub_total', 15, 2)->comment('quantity * unit_price');

            // You might link this to a chart of accounts/budget line item later
            $table->string('budget_code')->nullable()->comment('The specific budget head/code for this item');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_items');
    }
};