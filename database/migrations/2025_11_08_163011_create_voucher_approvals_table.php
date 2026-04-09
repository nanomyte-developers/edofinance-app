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
        Schema::create('voucher_approvals', function (Blueprint $table) {
            $table->id();

            // --- Foreign Keys ---

            // Link to the parent Voucher
            $table->foreignId('voucher_id')
                //   ->constrained('vouchers')
                //   ->onDelete('cascade')
                  ->comment('The voucher being approved/rejected.');

            // Link to the User who took the action (Crucial for auditing)
            $table->foreignId('user_id')
                  ->comment('User who performed the approval/rejection.'); // Add ->constrained('users') if you have a users table

            // --- Workflow Details ---

            // The specific role/level that performed the action (e.g., 'DFA', 'Auditor', 'AG')
            $table->string('approval_role')->comment('The designated role in the workflow (e.g., Auditor, DFA, AG).');
            
            // The chronological step number (useful for enforcing order)
            $table->unsignedSmallInteger('approval_step')->comment('The sequence number of this step in the overall workflow (e.g., 1, 2, 3).');
            
            // The action taken: 'Approved', 'Declined', 'Forwarded', 'Pending Review'
            $table->enum('action', ['Approved', 'Declined', 'Sent Back', 'Forwarded', 'Closed'])
                  ->comment('The decision made by the user.');

            // Mandatory comments for rejection or important context for approval
            $table->text('comment')->nullable();
            
            // Record when the action was taken
            $table->timestamp('action_at')->useCurrent();
            
            // Optional unique constraint to prevent double-approvals for the same step/role by the same voucher
            $table->unique(['voucher_id', 'approval_step'], 'unique_voucher_step_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_approvals');
    }
};