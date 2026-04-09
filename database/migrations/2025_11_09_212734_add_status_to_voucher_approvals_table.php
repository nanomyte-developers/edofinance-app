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
            $table->string('status')->after('action')->comment('Use to know status and use it for scope query in the database table');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('voucher_approvals', function (Blueprint $table) {
            $table->dropColumn(['status']);
        });
    }
};
