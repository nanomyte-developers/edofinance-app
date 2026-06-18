<?php
// app/Http/Controllers/Admin/AccountantGeneralController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankActivity;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AccountantGeneralController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers pending Accountant General review
     * These are vouchers approved by Expenditure Control (Step 5)
     */
    // public function index(Request $request)
    // {
    //     try {
    //         $perPage = $request->input('per_page', 15);
    //         $search = $request->input('search', '');
            
    //         // Get vouchers that are approved by EC and ready for AG
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
    //             ->where('status', 'ec_approved')
    //             ->where('ec_approved_by', 1)
    //             // ->whereHas('approvals', function ($q) {
    //             //     $q->where('approval_step', 6)
    //             //       ->where('action', VoucherApproval::ACTION_APPROVED);
    //             // })
    //             ->orderBy('created_at', 'desc');
            
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             foreach ($words as $word) {
    //                 $query->where(function ($q) use ($word) {
    //                     $q->where('voucher_number', 'like', "%{$word}%")
    //                       ->orWhere('narration', 'like', "%{$word}%")
    //                       ->orWhere('payee_name', 'like', "%{$word}%")
    //                       ->orWhereHas('mda', function ($mdaQuery) use ($word) {
    //                           $mdaQuery->where('name', 'like', "%{$word}%");
    //                       });
    //                 });
    //             }
    //         }
            
    //         $vouchers = $query->paginate($perPage);
            
    //         // Transform the data for the frontend
    //         $transformedVouchers = $vouchers->through(function ($voucher) {
    //             return [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => (float) $voucher->total_amount,
    //                 'payee_name' => $voucher->payee_name,
    //                 'status' => $voucher->status,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'code' => $voucher->mda->code,
    //                 ] : null,
    //                 'bank_activity' => $voucher->bankActivity ? [
    //                     'bank_name' => $voucher->bankActivity->bank_name,
    //                     'account_number' => $voucher->bankActivity->account_number,
    //                 ] : null,
    //                 'items' => $voucher->items->map(function ($item) {
    //                     return [
    //                         'id' => $item->id,
    //                         'description' => $item->description,
    //                         'quantity' => (float) $item->quantity,
    //                         'unit_price' => (float) $item->unit_price,
    //                         'sub_total' => (float) $item->sub_total,
    //                         'programme_code' => $item->programme_code,
    //                         'programme_name' => $item->programme_name,
    //                     ];
    //                 }),
    //             ];
    //         });
            
    //         // Get statistics
    //         $stats = [
    //             'pending_count' => Voucher::where('status', 'ec_approved')
    //             ->where('ag_approved_by', 0)->count(),
    //                 // ->whereHas('approvals', function ($q) {
    //                 //     $q->where('approval_step', 4)->where('action', VoucherApproval::ACTION_APPROVED);
    //                 // })->count(),
    //             'approved_today' => Voucher::where('status', 'ag_approved')
    //                 ->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')
    //                 ->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])->count(),
    //         ];
            
    //         return Inertia::render('admin/accountantGeneral/index', [
    //             'vouchers' => $transformedVouchers,
    //             'stats' => $stats,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Accountant General Index Error: ' . $e->getMessage());
    //         return Inertia::render('admin/accountantGeneral/index', [
    //             'vouchers' => [],
    //             'stats' => ['pending_count' => 0, 'approved_today' => 0, 'rejected_today' => 0, 'total_processed' => 0],
    //         ]);
    //     }
    // }

    /**
     * Display list of vouchers pending Accountant General review
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->where('status', 'ec_approved')
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
            
            $transformedVouchers = $vouchers->through(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'bank_activity_id' => $voucher->bank_activity_id,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                        'title' => $voucher->bankActivity->title,
                    ] : null,
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
                        ];
                    }),
                ];
            });
            
            $stats = [
                'pending_count' => Voucher::where('status', 'ec_approved')->count(),
                'approved_today' => Voucher::where('status', 'ag_approved')->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])->count(),
            ];
            
            return Inertia::render('admin/accountantGeneral/index', [
                'vouchers' => $transformedVouchers,
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Accountant General Index Error: ' . $e->getMessage());
            return Inertia::render('admin/accountantGeneral/index', [
                'vouchers' => [],
                'stats' => ['pending_count' => 0, 'approved_today' => 0, 'rejected_today' => 0, 'total_processed' => 0],
            ]);
        }
    }

    /**
     * Get bank activities for dropdown
     */
    public function getBankActivities(Request $request)
    {
        try {
            $filter = $request->input('filter', '');
            
            $query = BankActivity::query();
            
            if ($filter) {
                $query->where(function ($q) use ($filter) {
                    $q->where('bank_name', 'like', "%{$filter}%")
                      ->orWhere('account_number', 'like', "%{$filter}%")
                      ->orWhere('tag', 'like', "%{$filter}%")
                      ->orWhere('title', 'like', "%{$filter}%");
                });
            }
            
            $bankActivities = $query->orderBy('bank_name')->get();
            
            return response()->json([
                'data' => $bankActivities->map(function ($bank) {
                    return [
                        'id' => $bank->id,
                        'name' => $bank->bank_name,
                        'account_number' => $bank->account_number,
                        'tag' => $bank->tag,
                        'title' => $bank->title,
                        'label' => "{$bank->bank_name} - {$bank->account_number} ({$bank->tag})",
                        'value' => $bank->id,
                    ];
                }),
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching bank activities: ' . $e->getMessage());
            return response()->json(['data' => []]);
        }
    }

    /**
     * Approve voucher from Accountant General with bank selection
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('Accountant General Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'bank_activity_id' => $request->input('bank_activity_id'),
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();
        
        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                return redirect()->route('accountant-general.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }
            
            // Validate bank activity selection
            $bankActivityId = $request->input('bank_activity_id');
            if (!$bankActivityId) {
                return redirect()->route('accountant-general.index')
                    ->with('error', 'Please select a destination bank before approving the voucher.');
            }
            
            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $agStep = $maxStep + 1;
            $nextStep = $agStep + 1;
            
            $nextRole = VoucherApproval::ROLE_MAS;
            $nextRoleDisplay = 'Management Account Section (MAS)';
            
            // Update voucher with bank activity
            $voucher->update([
                'bank_activity_id' => $bankActivityId,
                'ag_approved_by' => auth()->id(),
                'ag_approved_at' => now(),
            ]);
            
            // Create AG approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_AG,
                'approval_step' => $agStep,
                'approval_level' => $agStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Approved by Accountant General'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            
            // Create forward to MAS record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => $nextRole,
                'approval_step' => $nextStep,
                'approval_level' => $nextStep,
                'action' => VoucherApproval::ACTION_FORWARDED,
                'status' => VoucherApproval::STATUS_FORWARDED,
                'comment' => "Forwarded to {$nextRoleDisplay} for final processing",
                'action_at' => now(),
            ]);
            
            // Update voucher status
            $voucher->update([
                'status' => 'ag_approved',
            ]);
            
            DB::commit();
            
            return redirect()->route('accountant-general.index')
                ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay}.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accountant General Approval Failed: ' . $e->getMessage());
            return redirect()->route('accountant-general.index')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }


    /**
     * Show voucher details for Accountant General
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
                    ];
                }),
                'documents' => $voucher->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'file_name' => $doc->file_name,
                        'file_path' => $doc->file_path,
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
                        'user' => $approval->user ? ['name' => $approval->user->name] : null,
                    ];
                }),
            ];
            
            return Inertia::render('admin/accountantGeneral/show', [
                'voucher' => $voucherData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Accountant General Show Error: ' . $e->getMessage());
            return redirect()->route('accountant-general.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Approve voucher from Accountant General
     * Forwards to Management Account Section (MAS) for final review
     */
    // public function approve(Voucher $voucher, Request $request)
    // {
    //     Log::info('Accountant General Approval Request:', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'user_id' => auth()->id(),
    //         'request_data' => $request->all()
    //     ]);

    //     DB::beginTransaction();
        
    //     try {
    //         // Check if voucher is in correct state
    //         if ($voucher->status !== 'ec_approved') {
    //             return redirect()->route('accountant-general.index')
    //                 ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
    //         }
            
    //         // Get the current maximum approval step
    //         $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            
    //         // AG should be at step 5 (after DFA step 1, IA step 2, FA step 3, EC step 4)
    //         $agStep = $maxStep + 1; // This will be 5
    //         $nextStep = $agStep + 1; // This will be 6 for MAS
            
    //         // Next role is Management Account Section
    //         $nextRole = VoucherApproval::ROLE_MAS;
    //         $nextRoleDisplay = 'Management Account Section (MAS)';
            
    //         // Create AG approval record at step 5
    //         VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => VoucherApproval::ROLE_AG,
    //             'approval_step' => $agStep,
    //             'approval_level' => $agStep,
    //             'action' => VoucherApproval::ACTION_APPROVED,
    //             'status' => VoucherApproval::STATUS_APPROVED,
    //             'comment' => $request->input('comment', 'Approved by Accountant General'),
    //             'action_at' => now(),
    //             'approved_at' => now(),
    //         ]);
            
    //         // Create forward to next stage record at step 6
    //         VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => $nextRole,
    //             'approval_step' => $nextStep,
    //             'approval_level' => $nextStep,
    //             'action' => VoucherApproval::ACTION_FORWARDED,
    //             'status' => VoucherApproval::STATUS_FORWARDED,
    //             'comment' => "Forwarded to {$nextRoleDisplay} for final processing",
    //             'action_at' => now(),
    //         ]);
            
    //         // Update voucher status
    //         $voucher->update([
    //             'status' => 'ag_approved',
    //             'ag_approved_by' => auth()->id(),
    //             'ag_approved_at' => now(),
    //         ]);
            
    //         // Log activity
    //         $this->activityLogger->log(
    //             "Accountant General approved and forwarded voucher {$voucher->voucher_number}",
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'forwarded_to' => $nextRoleDisplay,
    //                 'ag_step' => $agStep,
    //                 'next_step' => $nextStep,
    //                 'approved_by' => auth()->id(),
    //             ],
    //             'voucher'
    //         );
            
    //         DB::commit();
            
    //         Log::info('Accountant General Approval Successful:', [
    //             'voucher_id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'forwarded_to' => $nextRoleDisplay
    //         ]);
            
    //         return redirect()->route('accountant-general.index')
    //             ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay} successfully.");
                
    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('Accountant General Approval Failed:', [
    //             'voucher_id' => $voucher->id,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         return redirect()->route('accountant-general.index')
    //             ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
    //     }
    // }

    /**
     * Reject voucher from Accountant General (send back to DFA)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('Accountant General Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        DB::beginTransaction();
        
        try {
            $reason = $request->input('reason');
            
            if (empty($reason)) {
                return redirect()->route('accountant-general.index')
                    ->with('error', 'Rejection reason is required.');
            }
            
            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                return redirect()->route('accountant-general.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }
            
            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            
            // Create rejection record at a NEW step (maxStep + 1)
            $rejectionStep = $maxStep + 1; // This will be 5
            
            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_AG,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);
            
            // Update voucher status
            $voucher->update([
                'status' => 'sent_back',
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);
            
            // Log activity
            $this->activityLogger->log(
                "Accountant General rejected voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'reason' => $reason,
                    'rejection_step' => $rejectionStep,
                    'rejected_by' => auth()->id(),
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('Accountant General Rejection Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason
            ]);
            
            return redirect()->route('accountant-general.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to DFA.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accountant General Rejection Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('accountant-general.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Search for vouchers (API endpoint for AJAX calls)
     */
    public function search(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator'])
                ->where('status', 'ec_approved')
                ->whereHas('approvals', function ($q) {
                    $q->where('approval_step', 4)->where('action', VoucherApproval::ACTION_APPROVED);
                })
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
            
            return response()->json([
                'vouchers' => $vouchers,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Accountant General Search Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get statistics (API endpoint for AJAX calls)
     */
    public function stats()
    {
        try {
            $stats = [
                // Pending count: Vouchers approved by EC that haven't been processed by AG yet
                'pending_count' => Voucher::where('status', 'ec_approved')
                    ->whereDoesntHave('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_AG);
                    })
                    ->count(),
                
                // Approved today: Vouchers approved by AG today
                'approved_today' => Voucher::where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->count(),
                
                // Rejected today: Vouchers rejected by AG today (sent back to DFA)
                'rejected_today' => Voucher::where('status', 'sent_back')
                    ->whereDate('rejected_at', today())
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_AG)
                        ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                
                // Total processed: All vouchers that have been processed by AG
                'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])
                    ->count(),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Accountant General Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
            ]);
        }
    }
}