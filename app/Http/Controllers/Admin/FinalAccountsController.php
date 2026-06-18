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

class FinalAccountsController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers pending Final Accounts review
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->where('status', 'audit_approved')
                ->where('is_final_accounts', 0)
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
                    'bank_activity' => $voucher->bankActivity ? [
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
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
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                        ];
                    })->toArray(),
                ];
            });
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('status', 'audit_approved')
                    ->where('is_final_accounts', 0)->count(),
                'processed_today' => Voucher::where('status', 'forwarded')
                    ->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    ->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['forwarded', 'closed'])->count(),
            ];
            
            return Inertia::render('admin/finalAccounts/index', [
                'vouchers' => [
                    'data' => $transformedVouchers,
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'from' => $vouchers->firstItem(),
                    'to' => $vouchers->lastItem(),
                ],
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Final Accounts Index Error: ' . $e->getMessage());
            return Inertia::render('admin/finalAccounts/index', [
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
                    'processed_today' => 0, 
                    'rejected_today' => 0, 
                    'total_processed' => 0
                ],
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
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals'])
                ->where('status', 'audit_approved')
                ->where('is_final_accounts', 0)
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
                    'bank_activity' => $voucher->bankActivity ? [
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
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
                'pending_count' => Voucher::where('status', 'audit_approved')
                    ->where('is_final_accounts', 0)->count(),
                'processed_today' => Voucher::where('status', 'forwarded')
                    ->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    ->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['forwarded', 'closed'])->count(),
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
            Log::error('Final Accounts Search Error: ' . $e->getMessage(), [
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
                    'processed_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                ],
            ]);
        }
    }

    /**
     * Show voucher details
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
            
            return Inertia::render('admin/finalAccounts/show', [
                'voucher' => $voucherData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Final Accounts Show Error: ' . $e->getMessage());
            return redirect()->route('final-accounts.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Approve voucher from Final Accounts
     * Forwards to next stage (EC for standard/prepayment, Inspectorate for salary)
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('FINAL ACCOUNTS APPROVAL - STARTED');
        Log::info('========================================');
        Log::info('Final Accounts Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'current_status' => $voucher->status,
            'is_final_accounts' => $voucher->is_final_accounts,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'timestamp' => now()->toDateTimeString(),
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate voucher status
            Log::info('CHECK 1: Validating voucher status...');
            if ($voucher->status !== 'audit_approved') {
                Log::warning('CHECK 1 FAILED: Voucher not audit approved', [
                    'current_status' => $voucher->status,
                    'expected_status' => 'audit_approved'
                ]);
                DB::rollBack();
                return redirect()->route('final-accounts.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Internal Audit first. Current status: {$voucher->status}");
            }
            Log::info('CHECK 1 PASSED: Voucher status is audit_approved ✅');

            // CHECK 2: Validate is_final_accounts
            Log::info('CHECK 2: Validating is_final_accounts...');
            if ($voucher->is_final_accounts) {
                Log::warning('CHECK 2 FAILED: Voucher already processed by FA', [
                    'is_final_accounts' => $voucher->is_final_accounts
                ]);
                DB::rollBack();
                return redirect()->route('final-accounts.index')
                    ->with('error', "Voucher {$voucher->voucher_number} has already been processed by Final Accounts.");
            }
            Log::info('CHECK 2 PASSED: is_final_accounts is 0 ✅');

            // Get the current maximum approval step
            Log::info('Getting current maximum approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $faStep = $maxStep + 1;
            $nextStep = $faStep + 1;
            Log::info('Approval steps calculated:', [
                'max_step' => $maxStep,
                'fa_step' => $faStep,
                'next_step' => $nextStep
            ]);

            // Determine next role based on voucher type
            $nextRole = ($voucher->voucher_type === 'salary') 
                ? VoucherApproval::ROLE_INSPECTORATE 
                : VoucherApproval::ROLE_EC;
            
            $nextRoleDisplay = ($voucher->voucher_type === 'salary') 
                ? 'Inspectorate' 
                : 'Expenditure Control (EC)';
            
            Log::info('Next role determined:', [
                'next_role' => $nextRole,
                'next_role_display' => $nextRoleDisplay,
                'voucher_type' => $voucher->voucher_type
            ]);

            // Update voucher - set is_final_accounts to true
            Log::info('Updating voucher - setting is_final_accounts to true...');
            $updateData = [
                'is_final_accounts' => 1,
                'final_approved_by' => auth()->id(),
                'final_approved_at' => now(),
            ];
            Log::info('Update data:', $updateData);

            $updated = $voucher->update($updateData);
            if (!$updated) {
                Log::error('Voucher update FAILED!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            Log::info('Voucher updated successfully ✅');

            // Create FA approval record
            Log::info('Creating FA approval record...');
            $faApproval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_FA,
                'approval_step' => $faStep,
                'approval_level' => $faStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Approved by Final Accounts'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            Log::info('FA approval record created successfully:', [
                'approval_id' => $faApproval->id
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
                'approval_id' => $forwardApproval->id
            ]);

            // Update voucher status to forwarded
            Log::info('Updating voucher status to forwarded...');
            $statusUpdate = $voucher->update([
                'status' => 'forwarded',
            ]);
            
            if (!$statusUpdate) {
                Log::error('Status update FAILED!');
                throw new \Exception('Failed to update voucher status');
            }
            Log::info('Voucher status updated to forwarded ✅');

            // Log activity
            $this->activityLogger->log(
                "Final Accounts approved and forwarded voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'forwarded_to' => $nextRoleDisplay,
                    'fa_step' => $faStep,
                    'next_step' => $nextStep,
                    'approved_by' => auth()->id(),
                    'approved_by_name' => auth()->user()?->name,
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('========================================');
            Log::info('FINAL ACCOUNTS APPROVAL - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Approval completed:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'forwarded_to' => $nextRoleDisplay
            ]);
            
            return redirect()->route('final-accounts.index')
                ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay} successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('FINAL ACCOUNTS APPROVAL - FAILED');
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
            
            return redirect()->route('final-accounts.index')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from Final Accounts (send back to DFA)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('FINAL ACCOUNTS REJECTION - STARTED');
        Log::info('========================================');
        Log::info('Final Accounts Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'current_status' => $voucher->status,
            'is_final_accounts' => $voucher->is_final_accounts,
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
                return redirect()->route('final-accounts.index')
                    ->with('error', 'Rejection reason is required.');
            }
            Log::info('CHECK 1 PASSED: Rejection reason provided ✅', [
                'reason_length' => strlen($reason)
            ]);

            // CHECK 2: Validate voucher status
            Log::info('CHECK 2: Validating voucher status...');
            if ($voucher->status !== 'audit_approved') {
                Log::warning('CHECK 2 FAILED: Voucher not audit approved', [
                    'current_status' => $voucher->status,
                    'expected_status' => 'audit_approved'
                ]);
                DB::rollBack();
                return redirect()->route('final-accounts.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Internal Audit first. Current status: {$voucher->status}");
            }
            Log::info('CHECK 2 PASSED: Voucher status is audit_approved ✅');

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
                'approval_role' => VoucherApproval::ROLE_FA,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);
            Log::info('Rejection record created successfully:', [
                'rejection_id' => $rejection->id
            ]);

            // Update voucher status
            Log::info('Updating voucher status to sent_back...');
            $updateData = [
                'status' => 'sent_back',
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ];
            Log::info('Update data:', $updateData);

            $updated = $voucher->update($updateData);
            
            if (!$updated) {
                Log::error('Voucher update FAILED!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            
            Log::info('Voucher updated successfully:', [
                'new_status' => 'sent_back',
                'rejected_by' => auth()->id(),
                'rejected_at' => now()->toDateTimeString()
            ]);

            // Log activity
            $this->activityLogger->log(
                "Final Accounts rejected voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'reason' => $reason,
                    'rejection_step' => $rejectionStep,
                    'rejected_by' => auth()->id(),
                    'rejected_by_name' => auth()->user()?->name,
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('========================================');
            Log::info('FINAL ACCOUNTS REJECTION - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Rejection completed:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason
            ]);
            
            return redirect()->route('final-accounts.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to DFA.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('FINAL ACCOUNTS REJECTION - FAILED');
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
            
            return redirect()->route('final-accounts.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Get statistics (API endpoint for AJAX calls)
     */
    public function stats()
    {
        try {
            $stats = [
                'pending_count' => Voucher::where('status', 'audit_approved')
                    ->where('is_final_accounts', 0)->count(),
                'processed_today' => Voucher::where('status', 'forwarded')
                    ->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    ->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['forwarded', 'closed'])->count(),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Final Accounts Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_count' => 0,
                'processed_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
            ]);
        }
    }
}