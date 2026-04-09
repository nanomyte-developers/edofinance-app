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
        Schema::table('voucher_items', function (Blueprint $table) {
            if (!Schema::hasColumn('voucher_items', 'economy_code_id')) {
                $table->foreignId('economy_code_id')->nullable()->constrained()->nullOnDelete();
            }
            
            if (!Schema::hasColumn('voucher_items', 'economy_code_item_id')) {
                $table->foreignId('economy_code_item_id')->nullable()->constrained('economy_code_items')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_items', function (Blueprint $table) {
            $columnsToDrop = [];
            
            if (Schema::hasColumn('voucher_items', 'economy_code_id')) {
                $table->dropForeign(['economy_code_id']);
                $columnsToDrop[] = 'economy_code_id';
            }
            
            if (Schema::hasColumn('voucher_items', 'economy_code_item_id')) {
                $table->dropForeign(['economy_code_item_id']);
                $columnsToDrop[] = 'economy_code_item_id';
            }
            
            if (Schema::hasColumn('voucher_items', 'budget_code')) {
                $columnsToDrop[] = 'budget_code';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
