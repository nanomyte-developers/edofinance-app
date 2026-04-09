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
        Schema::create('mdas', function (Blueprint $table) {
            // Primary Key
            $table->id();

            // Unique identifiers for the MDA
            $table->string('name')->unique()->comment('Full name of the MDA (Ministry, Department, or Agency)');
            $table->string('code', 200)->unique()->comment('Short code or identifier for the MDA');
            $table->string('initials', 200)->unique()->comment('Abbreviated initials (e.g., MOF, FIRS)');

            // Other details
            $table->string('location')->nullable()->comment('Physical location or address of the MDA');

            // Status (Using tinyInteger as requested)
            $table->tinyInteger('status')->default(0)->comment('1=Active, 0=Inactive');

            // Timestamps
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdas');
    }
};
