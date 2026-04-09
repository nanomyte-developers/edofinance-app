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
        Schema::create('schedule_items', function (Blueprint $table) {
            $table->id();

            // --- Link to Parent Schedule ---
            $table->foreignId('schedule_id')
                //   ->constrained('schedules')
                //   ->onDelete('cascade')
                  ->comment('Parent schedule reference');

            // --- Columns matching the physical document Image ---
            $table->date('item_date')->comment('Column: DATE');
            $table->string('serial_number')->comment('Column: Serial No. (e.g., 15)');
            $table->string('economy_head_code')->comment('Column: Economy Head (e.g., 22020301)');
            $table->string('payee_name')->comment('Column: Name of Payee');
            
            $table->decimal('amount', 15, 2)->comment('Column: Amount');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schedule_items');
    }
};
