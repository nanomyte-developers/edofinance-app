<?php
// database/migrations/2026_01_15_000001_create_programme_codes_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('programme_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 500);
            $table->text('description')->nullable();
            $table->string('budget_code', 100)->nullable();
            // $table->foreignId('economic_code_id')->nullable()->constrained('economy_codes')->onDelete('restrict');
            $table->foreignId('economic_code_id')->nullable();
            $table->decimal('approved_budget', 15, 2)->default(0);
            $table->decimal('utilized_budget', 15, 2)->default(0);
            $table->decimal('remaining_budget', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            // $table->foreignId('financial_year_id')->constrained('financial_years')->onDelete('restrict');
            $table->foreignId('financial_year_id');
            
            // New fields for hierarchy
            $table->string('sector', 100)->nullable(); // ADMIN SECTOR, ECONOMIC SECTOR, etc.
            $table->string('mda_name', 255)->nullable(); // MDA name (parent)
            $table->string('mda_admin_code', 50)->nullable(); // MDA admin code
            // $table->foreignId('parent_programme_code_id')->nullable()->constrained('programme_codes')->onDelete('cascade');
            $table->foreignId('parent_programme_code_id')->nullable();
            $table->boolean('is_mda_parent')->default(false); // True for MDA rows, false for project rows
            $table->string('project_description', 500)->nullable(); // Project description for children
            
            $table->timestamps();
            
            $table->index(['sector', 'financial_year_id']);
            $table->index(['mda_name', 'financial_year_id']);
            $table->index(['code', 'financial_year_id']);
            $table->index(['parent_programme_code_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('programme_codes');
    }
};