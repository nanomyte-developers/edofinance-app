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
        Schema::create('economy_code_items', function (Blueprint $table) {
            $table->id();
            // $table->foreignId('economic_code_id')->constrained('economy_codes')->onDelete('cascade');
            $table->foreignId('economy_code_id');
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            
            // Indexes for performance
            $table->index('code');
            $table->index('economy_code_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('economy_code_items');
    }
};
