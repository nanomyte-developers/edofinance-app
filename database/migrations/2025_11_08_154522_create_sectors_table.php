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
        Schema::create('sectors', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Foreign Key establishing the One-to-Many relationship (One MDA has Many Sectors)
            $table->foreignId('mda_id')
                //   ->constrained('mdas') // Links to the 'id' column on the 'mdas' table
                //   ->onUpdate('cascade')
                //   ->onDelete('cascade')
                  ->comment('Foreign key linking the Sector to its parent MDA');

            // Sector details
            $table->string('name')->unique()->comment('Full name of the Sector');
            $table->string('code', 200)->unique()->comment('Short code or identifier for the Sector');

            // Status and Closure (As previously defined)
            $table->tinyInteger('status')->default(0)->comment('1=Active, 0=Inactive');
            $table->text('closure_reason')->nullable()->comment('Mandatory reason if the status is set to inactive (0).');
            
            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sectors');
    }
};