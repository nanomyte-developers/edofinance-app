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
        Schema::table('vouchers', function (Blueprint $table) {
            if (!Schema::hasColumn('vouchers', 'schedule_id')) {
                // $table->foreignId('schedule_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('schedule_id')->nullable()->after('id');
            }
            
            if (!Schema::hasColumn('vouchers', 'requires_retirement')) {
                $table->boolean('requires_retirement')->default(false);
            }
            
            if (!Schema::hasColumn('vouchers', 'retired_at')) {
                $table->timestamp('retired_at')->nullable();
            }
            
            if (!Schema::hasColumn('vouchers', 'retirement_voucher_id')) {
                // $table->foreignId('retirement_voucher_id')->nullable()->constrained('vouchers')->nullOnDelete();
                $table->foreignId('retirement_voucher_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropForeign(['schedule_id']);
            $table->dropForeign(['retirement_voucher_id']);
            $table->dropColumn(['schedule_id', 'requires_retirement', 'retired_at', 'retirement_voucher_id']);
        });
    }
};
