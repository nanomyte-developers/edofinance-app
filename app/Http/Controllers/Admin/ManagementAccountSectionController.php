<?php
// app/Http/Controllers/Admin/ManagementAccountSectionController.php

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

class ManagementAccountSectionController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers pending Management Account Section review
     * These are vouchers approved by Accountant General (Step 6)
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            
            // Get vouchers that are approved by AG and ready for MAS
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->where('status', 'ag_approved')
                ->where('ag_approved_by', 1)
                // ->whereDoesntHave('approvals', function ($q) {
                //     $q->where('approval_role', VoucherApproval::ROLE_MAS);
                // })
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
            $transformedVouchers = $vouchers->through(function ($voucher) {
                // Get approval records for display
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $iaApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_IA)->first();
                
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
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ia_approved_at' => $iaApproval?->approved_at?->toDateTimeString(),
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                        'title' => $voucher->bankActivity->title,
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
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('status', 'ag_approved')
                ->where('mas_approved_by', 0)->count(),
                    // ->whereDoesntHave('approvals', function ($q) {
                    //     $q->where('approval_role', VoucherApproval::ROLE_MAS);
                    // })->count(),
                'approved_today' => Voucher::where('status', 'closed')
                    ->whereDate('mas_approved_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'mas_rejected')
                    ->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['closed', 'mas_rejected'])->count(),
            ];
            
            return Inertia::render('admin/managementAccountSection/index', [
                'vouchers' => $transformedVouchers,
                'stats' => $stats,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Management Account Section Index Error: ' . $e->getMessage());
            return Inertia::render('admin/managementAccountSection/index', [
                'vouchers' => [],
                'stats' => ['pending_count' => 0, 'approved_today' => 0, 'rejected_today' => 0, 'total_processed' => 0],
            ]);
        }
    }

    /**
     * Show voucher details for Management Account Section
     */
    // public function show($id)
    // {
    //     try {
    //         $voucher = Voucher::with([
    //             'items.economyCode',
    //             'items.economyCodeItem',
    //             'items.programmeCode',
    //             'documents',
    //             'mda',
    //             'financialYear',
    //             'bankActivity',
    //             'creator',
    //             'approvals.user'
    //         ])->findOrFail($id);
            
    //         $voucherData = [
    //             'id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //             'narration' => $voucher->narration,
    //             'total_amount' => (float) $voucher->total_amount,
    //             'payee_name' => $voucher->payee_name,
    //             'status' => $voucher->status,
    //             'voucher_type' => $voucher->voucher_type,
    //             'mda' => $voucher->mda ? [
    //                 'id' => $voucher->mda->id,
    //                 'name' => $voucher->mda->name,
    //             ] : null,
    //             'items' => $voucher->items->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'description' => $item->description,
    //                     'quantity' => (float) $item->quantity,
    //                     'unit_price' => (float) $item->unit_price,
    //                     'sub_total' => (float) $item->sub_total,
    //                     'programme_code' => $item->programme_code,
    //                     'programme_name' => $item->programme_name,
    //                 ];
    //             }),
    //             'documents' => $voucher->documents->map(function ($doc) {
    //                 return [
    //                     'id' => $doc->id,
    //                     'file_name' => $doc->file_name,
    //                     'file_path' => $doc->file_path,
    //                     'document_type' => $doc->document_type,
    //                     'document_label' => $doc->document_label,
    //                 ];
    //             }),
    //             'approvals' => $voucher->approvals->map(function ($approval) {
    //                 return [
    //                     'id' => $approval->id,
    //                     'action' => $approval->action,
    //                     'comment' => $approval->comment,
    //                     'action_at' => $approval->action_at,
    //                     'approval_role' => $approval->approval_role,
    //                     'user' => $approval->user ? ['name' => $approval->user->name] : null,
    //                 ];
    //             }),
    //         ];
            
    //         return Inertia::render('admin/managementAccountSection/show', [
    //             'voucher' => $voucherData,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Management Account Section Show Error: ' . $e->getMessage());
    //         return redirect()->route('management-account-section.index')
    //             ->with('error', 'Voucher not found.');
    //     }
    // }
    /**
     * Show voucher details for Management Account Section
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
                // Add bank activity details
                'bank_activity' => $voucher->bankActivity ? [
                    'id' => $voucher->bankActivity->id,
                    'bank_name' => $voucher->bankActivity->bank_name,
                    'account_number' => $voucher->bankActivity->account_number,
                    'tag' => $voucher->bankActivity->tag,
                    'title' => $voucher->bankActivity->title,
                    'economic_code' => $voucher->bankActivity->economic_code,
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
            
            return Inertia::render('admin/managementAccountSection/show', [
                'voucher' => $voucherData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Management Account Section Show Error: ' . $e->getMessage());
            return redirect()->route('management-account-section.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Approve voucher from Management Account Section (Final Approval - Close Voucher)
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('Management Account Section Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        DB::beginTransaction();
        
        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                return redirect()->route('managementAccountSection.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Accountant General first. Current status: " . ($voucher->status ?? 'unknown'));
            }
            
            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $masStep = $maxStep + 1; // This will be 6
            
            // Create MAS approval record (Final Approval)
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_MAS,
                'approval_step' => $masStep,
                'approval_level' => $masStep,
                'action' => VoucherApproval::ACTION_CLOSED,
                'status' => VoucherApproval::STATUS_CLOSED,
                'comment' => $request->input('comment', 'Final approval by Management Account Section - Voucher Closed'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            
            // Update voucher status to Closed
            $voucher->update([
                'status' => 'closed',
                'mas_approved_by' => auth()->id(),
                'mas_approved_at' => now(),
                'closed_at' => now(),
            ]);
            
            // Log activity
            $this->activityLogger->log(
                "Management Account Section closed voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'final_step' => $masStep,
                    'approved_by' => auth()->id(),
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('Management Account Section Approval Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
            ]);
            
            return redirect()->route('management-account-section.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been closed successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Management Account Section Approval Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('management-account-section.index')
                ->with('error', 'Failed to close voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from Management Account Section (send back to DFA)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('Management Account Section Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        DB::beginTransaction();
        
        try {
            $reason = $request->input('reason');
            
            if (empty($reason)) {
                return redirect()->route('management-account-section.index')
                    ->with('error', 'Rejection reason is required.');
            }
            
            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                return redirect()->route('management-account-section.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Accountant General first. Current status: " . ($voucher->status ?? 'unknown'));
            }
            
            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;
            
            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_MAS,
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
                'status' => 'mas_rejected',
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);
            
            // Log activity
            $this->activityLogger->log(
                "Management Account Section rejected voucher {$voucher->voucher_number}",
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
            
            Log::info('Management Account Section Rejection Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason
            ]);
            
            return redirect()->route('management-account-section.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Management Account Section Rejection Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('management-account-section.index')
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
                ->where('status', 'ag_approved')
                ->whereDoesntHave('approvals', function ($q) {
                    $q->where('approval_role', VoucherApproval::ROLE_MAS);
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
            Log::error('Management Account Section Search Error: ' . $e->getMessage());
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
                'pending_count' => Voucher::where('status', 'ag_approved')
                    ->whereDoesntHave('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_MAS);
                    })->count(),
                'approved_today' => Voucher::where('status', 'closed')
                    ->whereDate('mas_approved_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'mas_rejected')
                    ->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['closed', 'mas_rejected'])->count(),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Management Account Section Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
            ]);
        }
    }
}