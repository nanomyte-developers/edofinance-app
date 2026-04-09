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
        Schema::table('voucher_approvals', function (Blueprint $table) {
            $table->timestamp('approved_at')->after('action_at')->useCurrent();
            $table->timestamp('rejected_at')->after('approved_at')->useCurrent();
            $table->integer('approval_level')->nullable();
            $table->integer('next_approval_user_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_approvals', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rejected_at', 'approval_level', 'next_approval_user_id']);
        });
    }
};
