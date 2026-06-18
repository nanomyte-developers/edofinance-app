<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InternalAuditController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers pending Internal Audit review
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            
            $query = Voucher::with(['mda', 'documents', 'items', 'creator', 'approvals'])
                ->where('status', 'submitted')
                ->orderBy('created_at', 'desc');
            
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                          ->orWhere('narration', 'like', "%{$word}%")
                          ->orWhere('payee_name', 'like', "%{$word}%")
                          ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                              $mdaQuery->where('name', 'like', "%{$word}%");
                          });
                    });
                }
            }
            
            $vouchers = $query->paginate($perPage);
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                        'initials' => $voucher->mda->initials,
                    ] : null,
                    'documents' => $voucher->documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'file_name' => $doc->file_name,
                            'file_path' => $doc->file_path,
                            'document_type' => $doc->document_type,
                        ];
                    })->toArray(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                        ];
                    })->toArray(),
                ];
            });
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('status', 'submitted')->count(),
                'approved_today' => Voucher::whereDate('updated_at', today())
                    ->where('status', 'audit_approved')
                    ->count(),
                'rejected_today' => Voucher::whereDate('updated_at', today())
                    ->where('status', 'rejected')
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['audit_approved', 'rejected'])->count(),
            ];
            
            $requiredDocuments = config('voucher.required_documents', ['approval_form']);
            
            return Inertia::render('admin/internalAudit/index', [
                'vouchers' => [
                    'data' => $transformedVouchers,
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'from' => $vouchers->firstItem(),
                    'to' => $vouchers->lastItem(),
                ],
                'stats' => $stats,
                'requiredDocuments' => $requiredDocuments,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Internal Audit Index Error: ' . $e->getMessage());
            return Inertia::render('admin/internalAudit/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'pending_count' => 0, 
                    'approved_today' => 0, 
                    'rejected_today' => 0, 
                    'total_processed' => 0
                ],
                'requiredDocuments' => ['approval_form'],
            ]);
        }
    }

    /**
     * Search for vouchers (API endpoint for AJAX calls)
     */
    public function search(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            $search = $request->input('search', '');
            $voucherType = $request->input('voucher_type', '');
            $status = $request->input('status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            
            // Build the query
            $query = Voucher::with(['mda', 'documents', 'items', 'creator', 'approvals'])
                ->where('status', 'submitted')
                ->orderBy('created_at', 'desc');
            
            // Apply search filter
            if (!empty($search)) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                          ->orWhere('narration', 'like', "%{$word}%")
                          ->orWhere('payee_name', 'like', "%{$word}%")
                          ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                              $mdaQuery->where('name', 'like', "%{$word}%");
                          });
                    });
                }
            }
            
            // Apply voucher type filter
            if (!empty($voucherType)) {
                $query->where('voucher_type', $voucherType);
            }
            
            // Apply status filter
            if (!empty($status)) {
                $query->where('status', $status);
            }
            
            // Apply date range filter
            if (!empty($dateFrom)) {
                $query->whereDate('voucher_date', '>=', $dateFrom);
            }
            
            if (!empty($dateTo)) {
                $query->whereDate('voucher_date', '<=', $dateTo);
            }
            
            $vouchers = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->map(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                        'initials' => $voucher->mda->initials,
                    ] : null,
                    'documents' => $voucher->documents->map(function ($doc) {
                        return [
                            'id' => $doc->id,
                            'file_name' => $doc->file_name,
                            'file_path' => $doc->file_path,
                            'document_type' => $doc->document_type,
                        ];
                    })->toArray(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                        ];
                    })->toArray(),
                ];
            })->values()->toArray();
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('status', 'submitted')->count(),
                'approved_today' => Voucher::whereDate('updated_at', today())
                    ->where('status', 'audit_approved')
                    ->count(),
                'rejected_today' => Voucher::whereDate('updated_at', today())
                    ->where('status', 'rejected')
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['audit_approved', 'rejected'])->count(),
            ];
            
            return response()->json([
                'success' => true,
                'vouchers' => [
                    'data' => $transformedVouchers,
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                    'from' => $vouchers->firstItem(),
                    'to' => $vouchers->lastItem(),
                ],
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Internal Audit Search Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'last_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'pending_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                ],
            ]);
        }
    }

    /**
     * Show voucher details for Internal Audit
     */
    public function show($id)
    {
        try {
            $voucher = Voucher::with([
                'items.economyCode',
                'items.economyCodeItem',
                'items.programmeCode',
                'documents',
                'mda',
                'financialYear',
                'bankActivity',
                'creator',
                'approvals.user'
            ])->findOrFail($id);
            
            $voucherData = [
                'id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
                'narration' => $voucher->narration,
                'total_amount' => (float) $voucher->total_amount,
                'payee_name' => $voucher->payee_name,
                'status' => $voucher->status,
                'voucher_type' => $voucher->voucher_type,
                'mda' => $voucher->mda ? [
                    'id' => $voucher->mda->id,
                    'name' => $voucher->mda->name,
                    'code' => $voucher->mda->code,
                ] : null,
                'items' => $voucher->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'description' => $item->description,
                        'quantity' => (float) $item->quantity,
                        'unit_price' => (float) $item->unit_price,
                        'sub_total' => (float) $item->sub_total,
                        'programme_code' => $item->programme_code,
                        'programme_name' => $item->programme_name,
                        'budget_code' => $item->budget_code,
                    ];
                }),
                'documents' => $voucher->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path,
                        'file_size' => $doc->file_size,
                        'mime_type' => $doc->mime_type,
                        'document_type' => $doc->document_type,
                        'document_label' => $doc->document_label,
                    ];
                }),
                'approvals' => $voucher->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'action' => $approval->action,
                        'comment' => $approval->comment,
                        'action_at' => $approval->action_at,
                        'approval_role' => $approval->approval_role,
                        'user' => $approval->user ? [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                        ] : null,
                    ];
                }),
                'creator' => $voucher->creator ? [
                    'id' => $voucher->creator->id,
                    'name' => $voucher->creator->name,
                ] : null,
            ];
            
            return Inertia::render('admin/internalAudit/show', [
                'voucher' => $voucherData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Internal Audit Show Error: ' . $e->getMessage());
            return redirect()->route('internal-audits.index')
                ->with('error', 'Voucher not found.');
        }
    }

    // /**
    //  * Approve voucher from Internal Audit
    //  * Forwards to Final Accounts (Step 3 in workflow)
    //  */
    // public function approve(Voucher $voucher, Request $request)
    // {
    //     Log::info('Internal Audit Approval Request:', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'user_id' => auth()->id(),
    //     ]);

    //     DB::beginTransaction();
        
    //     try {
    //         // Check if voucher is in correct state
    //         if ($voucher->status !== 'Submitted') {
    //             return redirect()->route('internal-audits.index')
    //                 ->with('error', "Voucher {$voucher->voucher_number} must be submitted first.");
    //         }
            
    //         // Get the current maximum approval step
    //         $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
    //         $iaStep = $maxStep + 1;
    //         $nextStep = $iaStep + 1;
            
    //         // Next role is Final Accounts (FA)
    //         $nextRole = VoucherApproval::ROLE_FA;
    //         $nextRoleDisplay = 'Final Accounts';
            
    //         // Create Internal Audit approval record
    //         VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => VoucherApproval::ROLE_IA,
    //             'approval_step' => $iaStep,
    //             'approval_level' => $iaStep,
    //             'action' => VoucherApproval::ACTION_APPROVED,
    //             'status' => VoucherApproval::STATUS_APPROVED,
    //             'comment' => $request->input('comment', 'Approved by Internal Audit'),
    //             'action_at' => now(),
    //             'approved_at' => now(),
    //         ]);
            
    //         // Create forward to next stage record
    //         VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => $nextRole,
    //             'approval_step' => $nextStep,
    //             'approval_level' => $nextStep,
    //             'action' => VoucherApproval::ACTION_FORWARDED,
    //             'status' => VoucherApproval::STATUS_FORWARDED,
    //             'comment' => "Forwarded to {$nextRoleDisplay} for further processing",
    //             'action_at' => now(),
    //         ]);
            
    //         // Update voucher status
    //         $voucher->update([
    //             'status' => 'audit_approved',
    //             'is_final_accounts' => 0,
    //         ]);
            
    //         DB::commit();
            
    //         return redirect()->route('internal-audits.index')
    //             ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay}.");
                
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Internal Audit Approval Failed: ' . $e->getMessage());
    //         return redirect()->route('internal-audits.index')
    //             ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
    //     }
    // }

    // /**
    //  * Reject voucher from Internal Audit (send back to DFA)
    //  */
    // public function reject(Voucher $voucher, Request $request)
    // {
    //     Log::info('Internal Audit Rejection Request:', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'user_id' => auth()->id(),
    //         'reason' => $request->input('reason')
    //     ]);

    //     DB::beginTransaction();
        
    //     try {
    //         $reason = $request->input('reason');
            
    //         if (empty($reason)) {
    //             return redirect()->route('internal-audits.index')
    //                 ->with('error', 'Rejection reason is required.');
    //         }
            
    //         // Check if voucher is in correct state
    //         if ($voucher->status !== 'submitted') {
    //             return redirect()->route('internal-audits.index')
    //                 ->with('error', "Voucher {$voucher->voucher_number} must be submitted first.");
    //         }
            
    //         // Get the current maximum approval step
    //         $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
    //         $rejectionStep = $maxStep + 1;
            
    //         // Create rejection record
    //         VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => VoucherApproval::ROLE_IA,
    //             'approval_step' => $rejectionStep,
    //             'approval_level' => $rejectionStep,
    //             'action' => VoucherApproval::ACTION_DECLINED,
    //             'status' => VoucherApproval::STATUS_REJECTED,
    //             'comment' => $reason,
    //             'action_at' => now(),
    //             'rejected_at' => now(),
    //         ]);
            
    //         // Update voucher status
    //         $voucher->update([
    //             'status' => 'rejected',
    //             'rejection_reason' => $reason,
    //             'rejected_by' => auth()->id(),
    //             'rejected_at' => now(),
    //         ]);
            
    //         DB::commit();
            
    //         return redirect()->route('internal-audits.index')
    //             ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned.");
                
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Internal Audit Rejection Failed: ' . $e->getMessage());
    //         return redirect()->route('internal-audits.index')
    //             ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
    //     }
    // }

    /**
 * Approve voucher from Internal Audit
 * Forwards to Final Accounts (Step 3 in workflow)
 */
public function approve(Voucher $voucher, Request $request)
{
    Log::info('========================================');
    Log::info('INTERNAL AUDIT APPROVAL - STARTED');
    Log::info('========================================');
    Log::info('Internal Audit Approval Request:', [
        'voucher_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'current_status' => $voucher->status,
        'user_id' => auth()->id(),
        'user_name' => auth()->user()?->name,
        'request_data' => $request->all(),
        'timestamp' => now()->toDateTimeString(),
    ]);

    DB::beginTransaction();
    
    try {
        // CHECK 1: Validate voucher status
        Log::info('CHECK 1: Validating voucher status...');
        Log::info('Current status check:', [
            'current_status' => $voucher->status,
            'expected_status' => 'submitted',
            'voucher_number' => $voucher->voucher_number
        ]);
        
        if ($voucher->status !== 'Submitted') {
            Log::warning('CHECK 1 FAILED: Voucher not in submitted state', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'current_status' => $voucher->status,
                'expected_status' => 'submitted'
            ]);
            DB::rollBack();
            Log::info('Redirecting to index with error: Voucher must be submitted first');
            return redirect()->route('internal-audits.index')
                ->with('error', "Voucher {$voucher->voucher_number} must be submitted first. Current status: {$voucher->status}");
        }
        Log::info('CHECK 1 PASSED: Voucher status is submitted ✅');

        // Get the current maximum approval step
        Log::info('Getting current maximum approval step...');
        $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
        $iaStep = $maxStep + 1;
        $nextStep = $iaStep + 1;
        Log::info('Approval steps calculated:', [
            'max_step' => $maxStep,
            'ia_step' => $iaStep,
            'next_step' => $nextStep
        ]);

        // Next role is Final Accounts (FA)
        $nextRole = VoucherApproval::ROLE_FA;
        $nextRoleDisplay = 'Final Accounts';
        Log::info('Next role determined:', [
            'next_role' => $nextRole,
            'next_role_display' => $nextRoleDisplay
        ]);

        // Create Internal Audit approval record
        Log::info('Creating Internal Audit approval record...');
        $iaApproval = VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => auth()->id(),
            'approval_role' => VoucherApproval::ROLE_IA,
            'approval_step' => $iaStep,
            'approval_level' => $iaStep,
            'action' => VoucherApproval::ACTION_APPROVED,
            'status' => VoucherApproval::STATUS_APPROVED,
            'comment' => $request->input('comment', 'Approved by Internal Audit'),
            'action_at' => now(),
            'approved_at' => now(),
        ]);
        Log::info('IA approval record created successfully:', [
            'approval_id' => $iaApproval->id,
            'voucher_id' => $voucher->id
        ]);

        // Create forward to next stage record
        Log::info('Creating forward to next stage record...');
        $forwardApproval = VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => auth()->id(),
            'approval_role' => $nextRole,
            'approval_step' => $nextStep,
            'approval_level' => $nextStep,
            'action' => VoucherApproval::ACTION_FORWARDED,
            'status' => VoucherApproval::STATUS_FORWARDED,
            'comment' => "Forwarded to {$nextRoleDisplay} for further processing",
            'action_at' => now(),
        ]);
        Log::info('Forward record created successfully:', [
            'approval_id' => $forwardApproval->id,
            'voucher_id' => $voucher->id
        ]);

        // Update voucher status
        Log::info('Updating voucher status...');
        $updateData = [
            'status' => 'audit_approved',
            'is_final_accounts' => 0,
        ];
        Log::info('Update data:', $updateData);

        $updated = $voucher->update($updateData);
        
        if (!$updated) {
            Log::error('Voucher update FAILED!', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'update_data' => $updateData
            ]);
            throw new \Exception('Failed to update voucher record');
        }
        
        Log::info('Voucher updated successfully:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'new_status' => 'audit_approved',
            'is_final_accounts' => 0
        ]);

        DB::commit();
        
        Log::info('========================================');
        Log::info('INTERNAL AUDIT APPROVAL - COMPLETED SUCCESSFULLY');
        Log::info('========================================');
        Log::info('Approval completed successfully:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'forwarded_to' => $nextRoleDisplay,
            'approved_by' => auth()->user()?->name,
            'approved_at' => now()->toDateTimeString()
        ]);
        
        return redirect()->route('internal-audits.index')
            ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay}.");
            
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('========================================');
        Log::error('INTERNAL AUDIT APPROVAL - FAILED');
        Log::error('========================================');
        Log::error('Approval Exception:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return redirect()->route('internal-audits.index')
            ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
    }
}

/**
 * Reject voucher from Internal Audit (send back to DFA)
 */
public function reject(Voucher $voucher, Request $request)
{
    Log::info('========================================');
    Log::info('INTERNAL AUDIT REJECTION - STARTED');
    Log::info('========================================');
    Log::info('Internal Audit Rejection Request:', [
        'voucher_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'current_status' => $voucher->status,
        'user_id' => auth()->id(),
        'user_name' => auth()->user()?->name,
        'reason' => $request->input('reason'),
        'timestamp' => now()->toDateTimeString(),
    ]);

    DB::beginTransaction();
    
    try {
        // CHECK 1: Validate reason
        Log::info('CHECK 1: Validating rejection reason...');
        $reason = $request->input('reason');
        
        if (empty($reason)) {
            Log::warning('CHECK 1 FAILED: Rejection reason is empty');
            DB::rollBack();
            Log::info('Redirecting to index with error: Rejection reason required');
            return redirect()->route('internal-audits.index')
                ->with('error', 'Rejection reason is required.');
        }
        Log::info('CHECK 1 PASSED: Rejection reason provided ✅', [
            'reason_length' => strlen($reason)
        ]);

        // CHECK 2: Validate voucher status
        Log::info('CHECK 2: Validating voucher status...');
        Log::info('Current status check:', [
            'current_status' => $voucher->status,
            'expected_status' => 'submitted',
            'voucher_number' => $voucher->voucher_number
        ]);
        
        if ($voucher->status !== 'submitted') {
            Log::warning('CHECK 2 FAILED: Voucher not in submitted state', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'current_status' => $voucher->status,
                'expected_status' => 'submitted'
            ]);
            DB::rollBack();
            Log::info('Redirecting to index with error: Voucher must be submitted first');
            return redirect()->route('internal-audits.index')
                ->with('error', "Voucher {$voucher->voucher_number} must be submitted first. Current status: {$voucher->status}");
        }
        Log::info('CHECK 2 PASSED: Voucher status is submitted ✅');

        // Get the current maximum approval step
        Log::info('Getting current maximum approval step...');
        $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
        $rejectionStep = $maxStep + 1;
        Log::info('Rejection step calculated:', [
            'max_step' => $maxStep,
            'rejection_step' => $rejectionStep
        ]);

        // Create rejection record
        Log::info('Creating rejection record...');
        $rejection = VoucherApproval::create([
            'voucher_id' => $voucher->id,
            'user_id' => auth()->id(),
            'approval_role' => VoucherApproval::ROLE_IA,
            'approval_step' => $rejectionStep,
            'approval_level' => $rejectionStep,
            'action' => VoucherApproval::ACTION_DECLINED,
            'status' => VoucherApproval::STATUS_REJECTED,
            'comment' => $reason,
            'action_at' => now(),
            'rejected_at' => now(),
        ]);
        Log::info('Rejection record created successfully:', [
            'rejection_id' => $rejection->id,
            'voucher_id' => $voucher->id
        ]);

        // Update voucher status
        Log::info('Updating voucher status...');
        $updateData = [
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'rejected_by' => auth()->id(),
            'rejected_at' => now(),
        ];
        Log::info('Update data:', $updateData);

        $updated = $voucher->update($updateData);
        
        if (!$updated) {
            Log::error('Voucher update FAILED!', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'update_data' => $updateData
            ]);
            throw new \Exception('Failed to update voucher record');
        }
        
        Log::info('Voucher updated successfully:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'new_status' => 'rejected',
            'rejected_by' => auth()->id(),
            'rejected_at' => now()->toDateTimeString()
        ]);

        DB::commit();
        
        Log::info('========================================');
        Log::info('INTERNAL AUDIT REJECTION - COMPLETED SUCCESSFULLY');
        Log::info('========================================');
        Log::info('Rejection completed successfully:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'reason' => $reason,
            'rejected_by' => auth()->user()?->name,
            'rejected_at' => now()->toDateTimeString()
        ]);
        
        return redirect()->route('internal-audits.index')
            ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned.");
            
    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('========================================');
        Log::error('INTERNAL AUDIT REJECTION - FAILED');
        Log::error('========================================');
        Log::error('Rejection Exception:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'error_message' => $e->getMessage(),
            'error_code' => $e->getCode(),
            'error_file' => $e->getFile(),
            'error_line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);
        
        return redirect()->route('internal-audits.index')
            ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
    }
}

    /**
     * Check documents for a voucher
     */
    public function checkDocuments(Voucher $voucher)
    {
        try {
            $requiredDocuments = config('voucher.required_documents', ['approval_form']);
            $attachedDocuments = $voucher->documents->pluck('document_type')->toArray();
            
            $missingDocuments = array_diff($requiredDocuments, $attachedDocuments);
            
            return response()->json([
                'has_all_documents' => empty($missingDocuments),
                'missing_documents' => array_values($missingDocuments),
                'total_required' => count($requiredDocuments),
                'total_attached' => count($attachedDocuments),
            ]);
        } catch (\Exception $e) {
            Log::error('Check Documents Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get required documents configuration
     */
    public function getRequiredDocuments()
    {
        try {
            return response()->json([
                'required_documents' => config('voucher.required_documents', ['approval_form']),
            ]);
        } catch (\Exception $e) {
            Log::error('Get Required Documents Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}