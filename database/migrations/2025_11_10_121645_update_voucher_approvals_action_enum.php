<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the enum to include the new action
        DB::statement("ALTER TABLE voucher_approvals MODIFY COLUMN action ENUM('Saved', 'Approved', 'Declined', 'Sent Back', 'Forwarded', 'Closed', 'Decline and Close')");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the enum to the previous state
        DB::statement("ALTER TABLE voucher_approvals MODIFY COLUMN action ENUM('Saved', 'Approved', 'Declined', 'Sent Back', 'Forwarded', 'Closed')");
    }
};
