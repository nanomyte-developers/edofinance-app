<?php

namespace App\Services;

use App\Models\Voucher;
use App\Models\VoucherApproval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InternalAuditService
{
    /**
     * Approve a voucher in internal audit
     */
    public function approveVoucher(Voucher $voucher, array $data, int $userId)
    {
        return DB::transaction(function () use ($voucher, $data, $userId) {
            Log::info('Internal Audit Approval Process Started:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'user_id' => $userId,
                'data' => $data
            ]);

            // Check if all required documents are present
            $missingDocs = $this->checkRequiredDocuments($voucher);
            
            if (!empty($missingDocs)) {
                Log::warning('Missing required documents for approval:', [
                    'voucher_id' => $voucher->id,
                    'missing_docs' => $missingDocs
                ]);
                
                throw new \Exception('Missing required documents: ' . implode(', ', $missingDocs));
            }

            // Create audit approval record - Use correct ENUM values
            $approval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => $userId,
                'approval_role' => 'Internal Audit',
                'approval_step' => $this->getNextApprovalStep($voucher),
                'action' => 'Approved', // Use ENUM value 'Approved'
                'status' => 'approved',
                'comment' => $data['comments'] ?? 'Approved by Internal Audit',
                'action_at' => now(),
                'approval_level' => 2, // Internal Audit level
            ]);

            // Update voucher status
            $updateData = [
                'status' => 'audit_approved',
            ];

            // Only include current_stage if the column exists
            if ($this->columnExists('vouchers', 'current_stage')) {
                $updateData['current_stage'] = 'payment';
            }

            $voucher->update($updateData);

            Log::info('Voucher approved by Internal Audit:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'new_status' => 'audit_approved',
                'approval_id' => $approval->id
            ]);

            return [
                'voucher' => $voucher->fresh(),
                'approval' => $approval
            ];
        });
    }

    /**
     * Reject a voucher in internal audit - FIXED with correct ENUM values
     */
    public function rejectVoucher(Voucher $voucher, array $data, int $userId)
    {
        return DB::transaction(function () use ($voucher, $data, $userId) {
            Log::info('Internal Audit Rejection Process Started:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'user_id' => $userId,
                'data' => $data
            ]);

            // Validate required fields
            if (empty($data['reason'])) {
                throw new \Exception('Rejection reason is required.');
            }

            if (strlen($data['reason']) < 10) {
                throw new \Exception('Rejection reason must be at least 10 characters.');
            }

            // Create audit rejection record - Use correct ENUM values
            $approval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => $userId,
                'approval_role' => 'Internal Audit',
                'approval_step' => $this->getNextApprovalStep($voucher),
                'action' => 'Declined', // Use ENUM value 'Declined' instead of 'Rejected'
                'status' => 'rejected',
                'comment' => $data['reason'],
                'action_at' => now(),
                'approval_level' => 2, // Internal Audit level
            ]);

            // Return voucher to originator
            $updateData = [
                'status' => 'audit_rejected',
                'rejection_reason' => $data['reason']
            ];

            // Only include current_stage if the column exists
            if ($this->columnExists('vouchers', 'current_stage')) {
                $updateData['current_stage'] = 'originator';
            }

            $voucher->update($updateData);

            Log::info('Voucher rejected by Internal Audit:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'new_status' => 'audit_rejected',
                'reason' => $data['reason'],
                'approval_id' => $approval->id
            ]);

            return [
                'voucher' => $voucher->fresh(),
                'approval' => $approval
            ];
        });
    }

    /**
     * Get valid action values based on your ENUM column
     */
    public function getValidActions(): array
    {
        return [
            'Saved',
            'Approved', 
            'Declined',
            'Sent Back',
            'Forwarded',
            'Closed',
            'Decline and Close'
        ];
    }

    /**
     * Check if all required documents are present
     */
    public function checkRequiredDocuments(Voucher $voucher): array
    {
        $requiredDocs = $this->getRequiredDocuments();
        $attachedDocs = $voucher->documents->pluck('document_type')->toArray();
        
        $missing = [];
        
        foreach ($requiredDocs as $docType) {
            if (!in_array($docType, $attachedDocs)) {
                $missing[] = $this->getDocumentTypeLabel($docType);
            }
        }
        
        return $missing;
    }

    /**
     * Get list of required documents
     */
    public function getRequiredDocuments(): array
    {
        return [
            // 'invoice',
            // 'receipt', 
            // 'delivery_note',
            'approval_form',
        ];
    }

    /**
     * Get human-readable label for document type
     */
    public function getDocumentTypeLabel(string $type): string
    {
        $labels = [
            'approval_form' => 'Approval Form',
            // 'invoice' => 'Invoice',
            // 'receipt' => 'Receipt',
            // 'delivery_note' => 'Delivery Note',
        ];

        return $labels[$type] ?? $type;
    }

    /**
     * Get the next approval step number
     */
    private function getNextApprovalStep(Voucher $voucher): int
    {
        $currentStep = $voucher->approvals()->max('approval_step') ?? 0;
        return $currentStep + 1;
    }

    // Add this method to your InternalAuditService class
    /**
     * Check if user can approve/reject this voucher
     */
    public function canProcessVoucher(Voucher $voucher, int $userId): bool
    {
        // Check if voucher is in correct status for internal audit
        $validStatuses = ['submitted', 'Submitted']; // Handle both cases
        $validStages = ['internal_audit', 'Internal Audit']; // Handle both cases
        
        return in_array($voucher->status, $validStatuses) && 
            in_array($voucher->current_stage, $validStages) &&
            // Add any additional authorization checks here
            // Example: Check if user has internal audit role
            auth()->user()->hasRole('internal_audit');
    }

    /**
     * Get voucher statistics for internal audit dashboard
     */
    public function getDashboardStats(): array
    {
        return [
            'pending_count' => Voucher::whereIn('status', ['submitted', 'Submitted'])
                ->whereIn('current_stage', ['internal_audit', 'Internal Audit'])
                ->count(),
            'approved_today' => VoucherApproval::where('approval_role', 'Internal Audit')
                ->where('status', 'approved')
                ->whereDate('action_at', today())
                ->count(),
            'rejected_today' => VoucherApproval::where('approval_role', 'Internal Audit')
                ->where('status', 'rejected')
                ->whereDate('action_at', today())
                ->count(),
            'total_processed' => VoucherApproval::where('approval_role', 'Internal Audit')
                ->whereDate('action_at', today())
                ->count(),
        ];
    }

    /**
     * Get pending vouchers for internal audit
     */
    public function getPendingVouchers($perPage = 10)
    {
        // Use only status filter since current_stage column doesn't exist
        return Voucher::with(['mda', 'creator', 'documents'])
            ->whereIn('status', ['submitted', 'Submitted'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Check if a database column exists
     */
    private function columnExists(string $table, string $column): bool
    {
        try {
            $schema = DB::getSchemaBuilder();
            return $schema->hasColumn($table, $column);
        } catch (\Exception $e) {
            return false;
        }
    }
}