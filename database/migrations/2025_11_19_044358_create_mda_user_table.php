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
        Schema::create('mda_user', function (Blueprint $table) {
            // Primary Key: Still recommended for custom pivot tables
            $table->id();

            // Foreign Keys: Laravel defaults assume 'user_id' and 'mda_id'
            $table->foreignId('mda_id') // 👈 mda_id first for alphabetical convention
                //   ->constrained('mdas')
                  ->onDelete('cascade');

            $table->foreignId('user_id')
                //   ->constrained() // Assumes 'users' table
                  ->onDelete('cascade');

            // --- Additional Pivot Data (As requested) ---
            $table->boolean('is_primary')->default(false);
            $table->date('effective_date')->nullable();

            // --- Indexes and Timestamps ---

            // Ensures a user can only be assigned to a specific MDA once
            $table->unique(['user_id', 'mda_id']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mda_user');
    }
};
