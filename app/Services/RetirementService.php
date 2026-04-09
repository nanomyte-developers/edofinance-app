<?php

namespace App\Services;

use App\Models\RetirementItem;
use App\Models\RetirementLog;
use App\Models\RetirementVoucher;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;

class RetirementService
{
    /**
     * Create a new retirement voucher
     */
    public function createRetirement(array $data, Voucher $voucher, int $userId)
    {
        DB::beginTransaction();
        try {
            // Generate retirement number
            $retirementNumber = $this->generateRetirementNumber($voucher);

            // Calculate remaining balance
            $remainingBalance = $voucher->total_amount - ($voucher->retired_amount + $data['total_amount']);

            // Create retirement voucher
            $retirementVoucher = RetirementVoucher::create([
                'original_voucher_id' => $voucher->id,
                'schedule_id' => $voucher->schedule_id,
                'year_id' => $voucher->year_id,
                'mda_id' => $voucher->mda_id,
                'bank_activity_id' => $voucher->bank_activity_id,
                'retirement_number' => $retirementNumber,
                'status' => 'submitted',
                'retirement_type' => $remainingBalance > 0 ? 'partial' : 'full',
                'original_voucher_amount' => $voucher->total_amount,
                'retired_amount' => $data['total_amount'],
                'remaining_balance' => $remainingBalance,
                'comments' => $data['comment'] ?? null,
                'created_by' => $userId,
            ]);

            // Create retirement items
            foreach ($data['line_items'] as $lineItem) {
                RetirementItem::create([
                    'retirement_voucher_id' => $retirementVoucher->id,
                    'description' => $lineItem['description'],
                    'economic_code_id' => $lineItem['economic_code_id'] ?? null,
                    'economic_code_item_id' => $lineItem['code_item_id'] ?? null,
                    'quantity' => $lineItem['quantity'],
                    'unit_price' => $lineItem['unit_price'],
                    'sub_total' => $lineItem['sub_total'],
                    'comments' => $lineItem['comments'] ?? null,
                ]);
            }

            // Update original voucher
            $voucher->retired_amount += $data['total_amount'];
            $voucher->remaining_balance = $voucher->total_amount - $voucher->retired_amount;
            // $voucher->is_fully_retired = $voucher->remaining_balance <= 0;
            $voucher->is_fully_retired = 1;
            $voucher->retired_at = now();
            $voucher->retirement_voucher_id = $retirementVoucher->id;
            $voucher->save();

            // Create audit log
            $this->createLog(
                $retirementVoucher->id,
                $userId,
                'submitted',
                $data['comment'] ?? null,
                [
                    'retired_amount' => $data['total_amount'],
                    'remaining_balance' => $remainingBalance,
                    'line_items_count' => count($data['line_items']),
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'retirement_voucher' => $retirementVoucher,
                'original_voucher' => $voucher,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Retirement creation failed: '.$e->getMessage(), [
                'voucher_id' => $voucher->id,
                'user_id' => $userId,
                'error' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Approve a retirement voucher
     */
    public function approveRetirement(RetirementVoucher $retirementVoucher, int $userId, ?string $comment = null)
    {
        DB::beginTransaction();
        try {
            $oldStatus = $retirementVoucher->status;
            $retirementVoucher->status = 'approved';
            $retirementVoucher->approved_by = $userId;
            $retirementVoucher->approved_at = now();
            $retirementVoucher->save();

            // If it's a full retirement, update voucher status
            if ($retirementVoucher->retirement_type === 'full') {
                $voucher = $retirementVoucher->originalVoucher;
                $voucher->is_fully_retired = true;
                $voucher->save();
            }

            // Create audit log
            $this->createLog(
                $retirementVoucher->id,
                $userId,
                'approved',
                $comment,
                [
                    'old_status' => $oldStatus,
                    'new_status' => 'approved',
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'retirement_voucher' => $retirementVoucher,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Retirement approval failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Reject a retirement voucher
     */
    public function rejectRetirement(RetirementVoucher $retirementVoucher, int $userId, ?string $comment = null)
    {
        DB::beginTransaction();
        try {
            $oldStatus = $retirementVoucher->status;
            $retirementVoucher->status = 'rejected';
            $retirementVoucher->save();

            // Create audit log
            $this->createLog(
                $retirementVoucher->id,
                $userId,
                'rejected',
                $comment,
                [
                    'old_status' => $oldStatus,
                    'new_status' => 'rejected',
                ]
            );

            DB::commit();

            return [
                'success' => true,
                'retirement_voucher' => $retirementVoucher,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Retirement rejection failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Delete a retirement voucher
     */
    public function deleteRetirement(RetirementVoucher $retirementVoucher, int $userId, ?string $comment = null)
    {
        if (! $retirementVoucher->canBeDeleted()) {
            return [
                'success' => false,
                'error' => 'Retirement voucher cannot be deleted in its current status.',
            ];
        }

        DB::beginTransaction();
        try {
            // Revert original voucher amounts if already submitted
            if ($retirementVoucher->status === 'submitted') {
                $voucher = $retirementVoucher->originalVoucher;
                $voucher->retired_amount -= $retirementVoucher->retired_amount;
                $voucher->remaining_balance = $voucher->total_amount - $voucher->retired_amount;
                $voucher->is_fully_retired = $voucher->remaining_balance <= 0;
                $voucher->save();
            }

            // Create audit log before deletion
            $this->createLog(
                $retirementVoucher->id,
                $userId,
                'deleted',
                $comment,
                [
                    'retired_amount' => $retirementVoucher->retired_amount,
                    'retirement_number' => $retirementVoucher->retirement_number,
                ]
            );

            // Delete the retirement voucher
            $retirementVoucher->delete();

            DB::commit();

            return [
                'success' => true,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Retirement deletion failed: '.$e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get retirement statistics
     */
    public function getStatistics(?int $mdaId = null, ?int $yearId = null, ?string $startDate = null, ?string $endDate = null)
    {
        $query = RetirementVoucher::query();

        if ($mdaId) {
            $query->where('mda_id', $mdaId);
        }

        if ($yearId) {
            $query->where('year_id', $yearId);
        }

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->where('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return [
            'total_retirements' => $query->count(),
            'total_retired_amount' => $query->sum('retired_amount'),
            'pending_approval' => $query->clone()->where('status', 'submitted')->count(),
            'approved_retirements' => $query->clone()->where('status', 'approved')->count(),
            'rejected_retirements' => $query->clone()->where('status', 'rejected')->count(),
            'full_retirements' => $query->clone()->where('retirement_type', 'full')->count(),
            'partial_retirements' => $query->clone()->where('retirement_type', 'partial')->count(),
        ];
    }

    /**
     * Generate retirement number
     */
    private function generateRetirementNumber(Voucher $voucher): string
    {
        $year = now()->format('Y');
        $prefix = 'RET';

        // Count retirements for this voucher
        $count = RetirementVoucher::where('original_voucher_id', $voucher->id)->count() + 1;

        return sprintf(
            '%s/%s/%03d/%s',
            $prefix,
            $voucher->voucher_number,
            $count,
            $year
        );
    }

    /**
     * Create audit log
     */
    private function createLog(int $retirementVoucherId, int $userId, string $action, ?string $comment = null, ?array $metadata = null)
    {
        return RetirementLog::create([
            'retirement_voucher_id' => $retirementVoucherId,
            'user_id' => $userId,
            'action' => $action,
            'comment' => $comment,
            'ip_address' => request()->ip(),
            'metadata' => $metadata,
        ]);
    }
}
