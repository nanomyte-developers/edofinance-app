<?php

namespace App\Policies;

use App\Models\RetirementVoucher;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Auth\Access\HandlesAuthorization;

class RetirementPolicy
{
    use HandlesAuthorization;

    /**
     * Determine if the user can retire a voucher.
     */
    public function retire(User $user, Voucher $voucher): bool
    {
        // Check if voucher is prepayment
        if ($voucher->voucher_type !== 'prepayment') {
            return false;
        }

        // Check if voucher is approved
        if ($voucher->status !== 'approved') {
            return false;
        }

        // Check if already fully retired
        if ($voucher->is_fully_retired) {
            return false;
        }

        // Check MDA access
        if ($user->mda_id && $voucher->mda_id !== $user->mda_id) {
            return false;
        }

        dd($user->roles);

        // Check user roles/permissions
        return $user->hasAnyRole(['Final Account', 'auditor', 'director'])
            || $user->can('retire-vouchers');
    }

    /**
     * Determine if the user can view a retirement voucher.
     */
    public function view(User $user, RetirementVoucher $retirementVoucher): bool
    {
        // Creator can always view
        if ($retirementVoucher->created_by === $user->id) {
            return true;
        }

        // MDA access
        if ($user->mda_id && $retirementVoucher->mda_id === $user->mda_id) {
            return true;
        }

        // Admin/Auditor/Director roles
        return $user->hasAnyRole(['admin', 'auditor', 'director'])
            || $user->can('view-retirements');
    }

    /**
     * Determine if the user can approve a retirement.
     */
    public function approveRetirement(User $user, RetirementVoucher $retirementVoucher): bool
    {
        // Check if retirement is pending
        if (! $retirementVoucher->canBeApproved()) {
            return false;
        }

        // MDA access
        if ($user->mda_id && $retirementVoucher->mda_id !== $user->mda_id) {
            return false;
        }

        // Only auditors and directors can approve
        return $user->hasAnyRole(['Final Account', 'auditor', 'director'])
            || $user->can('approve-retirements');
    }

    /**
     * Determine if the user can delete a retirement.
     */
    public function delete(User $user, RetirementVoucher $retirementVoucher): bool
    {
        // Only creator can delete unapproved retirements
        if ($retirementVoucher->created_by !== $user->id) {
            return false;
        }

        // Check status allows deletion
        if (! $retirementVoucher->canBeDeleted()) {
            return false;
        }

        // Check if within allowed timeframe (24 hours)
        $timeLimit = now()->subHours(24);
        if ($retirementVoucher->created_at < $timeLimit) {
            return false;
        }

        return true;
    }
}
