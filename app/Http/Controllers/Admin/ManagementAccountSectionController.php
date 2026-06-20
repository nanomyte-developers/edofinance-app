<?php
// app/Http/Controllers/Admin/ManagementAccountSectionController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankActivity;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class ManagementAccountSectionController extends Controller
{
    protected $activityLogger;

    // Voucher types that go through AG → MAS flow
    const MAS_VOUCHER_TYPES = ['capital', 'recurrent', 'gratuity', 'standard'];

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers for Management Account Section review
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $voucherType = $request->input('voucher_type', '');
            $status = $request->input('status', '');
            $paymentStatus = $request->input('payment_status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $tab = $request->input('tab', 'all');

            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'assignedTo'])
                ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // // Tab filtering
            // if ($tab === 'all') {
            //     // Show all vouchers approved by AG (status ag_approved) AND those already closed
            //     $query->whereIn('status', ['ag_approved', 'closed']);
            // } elseif ($tab === 'pending') {
            //     // Pending MAS review (AG approved, not yet closed)
            //     $query->where('status', 'ag_approved')->whereNotNull('ag_approved_at');
            // } elseif ($tab === 'liability') {
            //     // Liability vouchers (approved by FA today)
            //     $query->whereIn('status', ['ag_approved', 'closed'])
            //         ->whereDate('ag_approved_at', today());
            // } elseif ($tab === 'closed') {
            //     // Closed vouchers
            //     $query->where('status', 'closed')->whereNotNull('mas_approved_at');
            // }

            // =============================================
            // FIXED TAB FILTERING - Replace your existing tab filtering block
            // =============================================
            // Tab filtering
            if ($tab === 'all') {
                // Show all vouchers that have reached MAS (AG approved or closed)
                $query->whereIn('status', ['ag_approved', 'closed']);
            } elseif ($tab === 'pending') {
                // Pending MAS review (AG approved, not yet closed)
                // This shows vouchers approved by AG but not yet closed by MAS
                $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
            } elseif ($tab === 'liability') {
                // Liability vouchers - approved by AG today (ag_approved_at = today)
                // These should also appear in the pending tab if not closed
                $query->whereIn('status', ['ag_approved', 'closed'])
                    ->whereDate('ag_approved_at', today());
            } elseif ($tab === 'closed') {
                // Closed vouchers
                $query->where('status', 'closed')->whereNotNull('mas_approved_at');
            }

            // Apply search filter
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

            // Apply voucher type filter
            if ($voucherType) {
                $query->where('voucher_type', $voucherType);
            }

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            // Apply payment status filter
            if ($paymentStatus) {
                if ($paymentStatus === 'paid') {
                    $query->where('status', 'closed')->whereNotNull('mas_approved_at');
                } elseif ($paymentStatus === 'awaiting_mas') {
                    $query->where('status', 'ag_approved')->whereNotNull('ag_approved_at');
                }
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('voucher_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('voucher_date', '<=', $dateTo);
            }

            $vouchers = $query->paginate($perPage);

            $transformedVouchers = $vouchers->through(function ($voucher) {
                // Get approval records
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();

                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                }

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'payment_status' => $paymentStatus,
                    'bank_activity_id' => $voucher->bank_activity_id,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                        'title' => $voucher->bankActivity->title,
                    ] : null,
                    'assigned_to' => $voucher->assignedTo ? [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                        'email' => $voucher->assignedTo->email,
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

            // =============================================
            // FIXED STATISTICS
            // =============================================
            // $stats = [
            //     // All vouchers approved by AG (including those already closed)
            //     'pending_ag_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         ->count(),
                
            //     // Vouchers pending MAS review (AG approved but not closed)
            //     'pending_mas_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         // ->whereNull('mas_approved_at')
            //         ->count(),
                
            //     // Vouchers closed today
            //     'closed_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'closed')
            //         ->whereDate('mas_approved_at', today())
            //         ->count(),
                
            //     // Vouchers rejected by MAS today
            //     'rejected_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'mas_rejected')
            //         // ->whereDate('rejected_at', today())
            //         ->count(),
                
            //     // Total processed (closed + rejected by MAS)
            //     'total_processed' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->whereIn('status', ['closed', 'mas_rejected'])
            //         ->count(),
                
            //     // Total amount pending MAS review
            //     'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         // ->whereNull('mas_approved_at')
            //         ->sum('total_amount'),
                
            //     // Total amount closed
            //     'total_amount_closed' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'closed')
            //         ->sum('total_amount'),
                
            //     // Liability count - vouchers approved by FA today (liability as at current day)
            //     // These are vouchers where final_approved_at is today, regardless of current status
            //     'liability_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->whereDate('ag_approved_at', today())
            //         ->count(),
            // ];

            // =============================================
            // FIXED STATISTICS - Replace your stats array
            // =============================================
            $stats = [
                // All vouchers approved by AG (including those already closed)
                'pending_ag_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->count(),
                
                // Vouchers pending MAS review (AG approved but not closed)
                'pending_mas_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->count(),
                
                // Vouchers closed today
                'closed_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->whereDate('mas_approved_at', today())
                    ->count(),
                
                // Vouchers rejected by MAS today
                'rejected_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'mas_rejected')
                    // ->whereDate('rejected_at', today())
                    ->count(),
                
                // Total processed (closed + rejected by MAS)
                'total_processed' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'mas_rejected'])
                    ->count(),
                
                // Total amount pending MAS review
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->sum('total_amount'),
                
                // Total amount closed
                'total_amount_closed' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->sum('total_amount'),
                
                // Liability count - vouchers approved by AG today (ag_approved_at = today)
                // These are vouchers that became liabilities when AG approved them today
                'liability_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereDate('ag_approved_at', today())
                    ->count(),
            ];

            // Get users for assignment
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'MAS Staff', 'admin']);
            })->get(['id', 'name', 'email']);

            // Get bank activities
            $bankActivities = BankActivity::orderBy('bank_name')->get()->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'bank_name' => $bank->bank_name,
                    'account_number' => $bank->account_number,
                    'tag' => $bank->tag,
                    'title' => $bank->title,
                    'label' => "{$bank->bank_name} - {$bank->account_number} ({$bank->tag})",
                ];
            });

            return Inertia::render('admin/managementAccountSection/index', [
                'vouchers' => [
                    'data' => $transformedVouchers,
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'from' => $vouchers->firstItem(),
                    'to' => $vouchers->lastItem(),
                ],
                'stats' => $stats,
                'users' => $users,
                'bankActivities' => $bankActivities,
            ]);

        } catch (\Exception $e) {
            Log::error('MAS Index Error: ' . $e->getMessage());
            return Inertia::render('admin/managementAccountSection/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'pending_ag_count' => 0,
                    'pending_mas_count' => 0,
                    'closed_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_closed' => 0,
                    'liability_count' => 0,
                ],
                'users' => [],
                'bankActivities' => [],
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
            $paymentStatus = $request->input('payment_status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $tab = $request->input('tab', 'all');

            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals', 'assignedTo'])
                ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            // if ($tab === 'all') {
            //     $query->whereIn('status', ['ag_approved', 'closed']);
            // } elseif ($tab === 'pending') {
            //     $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
            // } elseif ($tab === 'liability') {
            //     $query->whereIn('status', ['ag_approved', 'closed'])
            //         ->whereDate('final_approved_at', today());
            // } elseif ($tab === 'closed') {
            //     $query->where('status', 'closed')->whereNotNull('mas_approved_at');
            // }

            // =============================================
            // FIXED SEARCH TAB FILTERING - Replace your search tab filtering
            // =============================================
            // Tab filtering
            if ($tab === 'all') {
                $query->whereIn('status', ['ag_approved', 'closed']);
            } elseif ($tab === 'pending') {
                $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
            } elseif ($tab === 'liability') {
                $query->whereIn('status', ['ag_approved', 'closed'])
                    ->whereDate('ag_approved_at', today());
            } elseif ($tab === 'closed') {
                $query->where('status', 'closed')->whereNotNull('mas_approved_at');
            }

            // Apply search filter
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

            // Apply voucher type filter
            if ($voucherType) {
                $query->where('voucher_type', $voucherType);
            }

            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }

            // Apply payment status filter
            if ($paymentStatus) {
                if ($paymentStatus === 'paid') {
                    $query->where('status', 'closed')->whereNotNull('mas_approved_at');
                } elseif ($paymentStatus === 'awaiting_mas') {
                    $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
                }
            }

            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('voucher_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('voucher_date', '<=', $dateTo);
            }

            $vouchers = $query->paginate($perPage, ['*'], 'page', $page);

            $transformedVouchers = $vouchers->map(function ($voucher) {
                // Get approval records
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();

                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                }

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'payment_status' => $paymentStatus,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                        'title' => $voucher->bankActivity->title,
                    ] : null,
                    'assigned_to' => $voucher->assignedTo ? [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
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
                        ];
                    }),
                ];
            })->values()->toArray();

            // =============================================
            // FIXED STATISTICS FOR SEARCH
            // =============================================
            // $stats = [
            //     'pending_ag_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         ->count(),
            //     'pending_mas_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         ->whereNull('mas_approved_at')
            //         ->count(),
            //     'closed_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'closed')
            //         ->whereDate('mas_approved_at', today())
            //         ->count(),
            //     'rejected_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'mas_rejected')
            //         // ->whereDate('rejected_at', today())
            //         ->count(),
            //     'total_processed' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->whereIn('status', ['closed', 'mas_rejected'])
            //         ->count(),
            //     'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'ag_approved')
            //         ->whereNull('mas_approved_at')
            //         ->sum('total_amount'),
            //     'total_amount_closed' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->where('status', 'closed')
            //         ->sum('total_amount'),
            //     'liability_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
            //         ->whereDate('final_approved_at', today())
            //         ->count(),
            // ];

            // =============================================
            // FIXED SEARCH STATISTICS - Replace your search stats
            // =============================================
            $stats = [
                'pending_ag_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->count(),
                'pending_mas_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->count(),
                'closed_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->whereDate('mas_approved_at', today())
                    ->count(),
                'rejected_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'mas_rejected')
                    // ->whereDate('rejected_at', today())
                    ->count(),
                'total_processed' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'mas_rejected'])
                    ->count(),
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->sum('total_amount'),
                'total_amount_closed' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->sum('total_amount'),
                'liability_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereDate('ag_approved_at', today())
                    ->count(),
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
            Log::error('MAS Search Error: ' . $e->getMessage(), [
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
                    'pending_ag_count' => 0,
                    'pending_mas_count' => 0,
                    'closed_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_closed' => 0,
                    'liability_count' => 0,
                ],
            ]);
        }
    }

    /**
     * Close voucher from Management Account Section (Final Stage)
     */
    public function close(Voucher $voucher, Request $request)
    {
        Log::info('MAS Close Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to MAS
        if (!in_array($voucher->voucher_type, self::MAS_VOUCHER_TYPES)) {
            return redirect()->route('management-account-section.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go through MAS.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by AG first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Check if bank is assigned
            if (!$voucher->bank_activity_id) {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must have a bank account assigned before closing.");
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $masStep = $maxStep + 1;

            // Create MAS approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_MAS,
                'approval_step' => $masStep,
                'approval_level' => $masStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Voucher closed by Management Account Section'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Update voucher status to closed
            $voucher->update([
                'status' => 'closed',
                'mas_approved_by' => auth()->id(),
                'mas_approved_at' => now(),
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Management Account Section closed voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'mas_step' => $masStep,
                        'closed_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('MAS Close Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
            ]);

            return redirect()->route('management-account-section.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been closed successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MAS Close Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('management-account-section.index')
                ->with('error', 'Failed to close voucher: ' . $e->getMessage());
        }
    }

    /**
     * Assign bank account to voucher
     */
    // public function assignBank(Voucher $voucher, Request $request)
    // {
    //     Log::info('MAS Assign Bank Request:', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'bank_activity_id' => $request->input('bank_activity_id'),
    //         'user_id' => auth()->id(),
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         $bankActivityId = $request->input('bank_activity_id');

    //         if (!$bankActivityId) {
    //             DB::rollBack();
    //             return redirect()->route('management-account-section.index')
    //                 ->with('error', 'Please select a bank account.');
    //         }

    //         // Check if bank exists
    //         $bank = BankActivity::find($bankActivityId);
    //         if (!$bank) {
    //             DB::rollBack();
    //             return redirect()->route('management-account-section.index')
    //                 ->with('error', 'Selected bank account not found.');
    //         }

    //         // Update voucher with bank
    //         $voucher->update([
    //             'bank_activity_id' => $bankActivityId,
    //             // 'bank_assigned_by' => auth()->id(),
    //             // 'bank_assigned_at' => now(),
    //         ]);

    //         // Log activity
    //         if ($this->activityLogger) {
    //             $this->activityLogger->log(
    //                 "Bank account assigned to voucher {$voucher->voucher_number}",
    //                 [
    //                     'voucher_id' => $voucher->id,
    //                     'voucher_number' => $voucher->voucher_number,
    //                     'bank_name' => $bank->bank_name,
    //                     'bank_account' => $bank->account_number,
    //                     'assigned_by' => auth()->id(),
    //                 ],
    //                 'voucher'
    //             );
    //         }

    //         DB::commit();

    //         Log::info('MAS Assign Bank Successful:', [
    //             'voucher_id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'bank_activity_id' => $bankActivityId,
    //         ]);

    //         return redirect()->route('management-account-section.index')
    //             ->with('success', "Bank account assigned to voucher {$voucher->voucher_number} successfully.");

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('MAS Assign Bank Failed:', [
    //             'voucher_id' => $voucher->id,
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);

    //         return redirect()->route('management-account-section.index')
    //             ->with('error', 'Failed to assign bank: ' . $e->getMessage());
    //     }
    // }

    /**
 * Assign bank account to voucher
 */
public function assignBank(Voucher $voucher, Request $request)
{
    Log::info('MAS Assign Bank Request:', [
        'voucher_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'bank_activity_id' => $request->input('bank_activity_id'),
        'user_id' => auth()->id(),
    ]);

    DB::beginTransaction();

    try {
        $bankActivityId = $request->input('bank_activity_id');

        // If it's an array/object, extract the ID
        if (is_array($bankActivityId) && isset($bankActivityId['id'])) {
            $bankActivityId = $bankActivityId['id'];
        } elseif (is_object($bankActivityId) && property_exists($bankActivityId, 'id')) {
            $bankActivityId = $bankActivityId->id;
        }

        if (!$bankActivityId) {
            DB::rollBack();
            return redirect()->route('management-account-section.index')
                ->with('error', 'Please select a bank account.');
        }

        // Check if bank exists
        $bank = BankActivity::find($bankActivityId);
        if (!$bank) {
            DB::rollBack();
            return redirect()->route('management-account-section.index')
                ->with('error', 'Selected bank account not found.');
        }

        // Update voucher with bank
        $voucher->update([
            'bank_activity_id' => $bankActivityId,
            // 'bank_assigned_by' => auth()->id(),
            // 'bank_assigned_at' => now(),
        ]);

        // Log activity
        if ($this->activityLogger) {
            $this->activityLogger->log(
                "Bank account assigned to voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'bank_name' => $bank->bank_name,
                    'bank_account' => $bank->account_number,
                    'assigned_by' => auth()->id(),
                ],
                'voucher'
            );
        }

        DB::commit();

        Log::info('MAS Assign Bank Successful:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'bank_activity_id' => $bankActivityId,
        ]);

        return redirect()->route('management-account-section.index')
            ->with('success', "Bank account assigned to voucher {$voucher->voucher_number} successfully.");

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('MAS Assign Bank Failed:', [
            'voucher_id' => $voucher->id,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);

        return redirect()->route('management-account-section.index')
            ->with('error', 'Failed to assign bank: ' . $e->getMessage());
    }
}

    /**
     * Assign voucher to staff member
     */
    public function assign(Voucher $voucher, Request $request)
    {
        Log::info('MAS Assign to Staff Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => $request->input('user_id'),
        ]);

        DB::beginTransaction();

        try {
            $userId = $request->input('user_id');

            if (!$userId) {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', 'Please select a staff member.');
            }

            $user = User::find($userId);
            if (!$user) {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', 'Selected staff member not found.');
            }

            $voucher->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Voucher {$voucher->voucher_number} assigned to {$user->name}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'assigned_to' => $userId,
                        'assigned_to_name' => $user->name,
                        'assigned_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('MAS Assign to Staff Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'assigned_to' => $userId,
            ]);

            return redirect()->route('management-account-section.index')
                ->with('success', "Voucher {$voucher->voucher_number} assigned to {$user->name} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MAS Assign to Staff Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('management-account-section.index')
                ->with('error', 'Failed to assign voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from Management Account Section
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('MAS Reject Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'reason' => $request->input('reason'),
        ]);

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                DB::rollBack();
                return redirect()->route('management-account-section.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be AG approved first.");
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
                // 'rejected_at' => now(),
            ]);

            // Update voucher status
            $voucher->update([
                'status' => 'mas_rejected',
                // 'rejection_reason' => $reason,
                // 'rejected_by' => auth()->id(),
                // 'rejected_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Management Account Section rejected voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'reason' => $reason,
                        'rejected_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('MAS Reject Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
            ]);

            return redirect()->route('management-account-section.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MAS Reject Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('management-account-section.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
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
                'approvals.user',
                'assignedTo'
            ])->findOrFail($id);

            // Check if voucher type should go through MAS
            if (!in_array($voucher->voucher_type, self::MAS_VOUCHER_TYPES)) {
                return redirect()->route('management-account-section.index')
                    ->with('error', "This voucher type '{$voucher->voucher_type}' does not go through MAS.");
            }

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
                'bank_activity' => $voucher->bankActivity ? [
                    'id' => $voucher->bankActivity->id,
                    'bank_name' => $voucher->bankActivity->bank_name,
                    'account_number' => $voucher->bankActivity->account_number,
                    'tag' => $voucher->bankActivity->tag,
                    'title' => $voucher->bankActivity->title,
                ] : null,
                'assigned_to' => $voucher->assignedTo ? [
                    'id' => $voucher->assignedTo->id,
                    'name' => $voucher->assignedTo->name,
                    'email' => $voucher->assignedTo->email,
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
                'creator' => $voucher->creator ? [
                    'id' => $voucher->creator->id,
                    'name' => $voucher->creator->name,
                ] : null,
            ];

            return Inertia::render('admin/managementAccountSection/show', [
                'voucher' => $voucherData,
            ]);

        } catch (\Exception $e) {
            Log::error('MAS Show Error: ' . $e->getMessage());
            return redirect()->route('management-account-section.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Get statistics (API endpoint)
     */
    public function stats()
    {
        try {
            $stats = [
                'pending_ag_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->count(),
                'pending_mas_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->count(),
                'closed_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->whereDate('mas_approved_at', today())
                    ->count(),
                'rejected_today' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'mas_rejected')
                    ->whereDate('rejected_at', today())
                    ->count(),
                'total_processed' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'mas_rejected'])
                    ->count(),
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'ag_approved')
                    ->whereNull('mas_approved_at')
                    ->sum('total_amount'),
                'total_amount_closed' => (float) Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->sum('total_amount'),
                'liability_count' => Voucher::whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->whereDate('ag_approved_at', today())
                    ->count(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('MAS Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_ag_count' => 0,
                'pending_mas_count' => 0,
                'closed_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
                'total_amount_pending' => 0,
                'total_amount_closed' => 0,
                'liability_count' => 0,
            ]);
        }
    }


    /**
     * Display assigned vouchers for MAS staff (non-admin users)
     * Shows only vouchers assigned to the current user in MAS
     */
    public function assignedIndex(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $voucherType = $request->input('voucher_type', '');
            $status = $request->input('status', '');
            $paymentStatus = $request->input('payment_status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $tab = $request->input('tab', 'all');
            
            $userId = auth()->id();
            
            // Build query - only vouchers assigned to current user
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->where('assigned_to_user_id', $userId)
                ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'ag_approved');
            } elseif ($tab === 'closed') {
                $query->where('status', 'closed');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'mas_rejected');
            }
            
            // Apply search filter
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
            
            // Apply voucher type filter
            if ($voucherType) {
                $query->where('voucher_type', $voucherType);
            }
            
            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }
            
            // Apply payment status filter
            if ($paymentStatus) {
                if ($paymentStatus === 'paid') {
                    $query->where('status', 'closed')->whereNotNull('mas_approved_at');
                } elseif ($paymentStatus === 'awaiting_mas') {
                    $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
                }
            }
            
            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('voucher_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('voucher_date', '<=', $dateTo);
            }
            
            $vouchers = $query->paginate($perPage);
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->through(function ($voucher) {
                // Get approval records for display
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                }
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
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
                    'assigned_to' => $voucher->assignedTo ? [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
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
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ag_approved')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'closed_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'closed')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'mas_rejected')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => 0, // MAS doesn't forward, they close or reject
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];
            
            return Inertia::render('admin/managementAccountSection/assigned', [
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
            Log::error('MAS Assigned Index Error: ' . $e->getMessage());
            return Inertia::render('admin/managementAccountSection/assigned', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'total_assigned' => 0,
                    'pending_review' => 0,
                    'closed_count' => 0,
                    'rejected_count' => 0,
                    'forwarded_count' => 0,
                    'total_amount' => 0,
                ],
            ]);
        }
    }

    /**
     * Search assigned vouchers (API endpoint for AJAX calls)
     */
    public function searchAssigned(Request $request)
    {
        try {
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            $search = $request->input('search', '');
            $voucherType = $request->input('voucher_type', '');
            $status = $request->input('status', '');
            $paymentStatus = $request->input('payment_status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $tab = $request->input('tab', 'all');
            
            $userId = auth()->id();
            
            // Build query - only vouchers assigned to current user
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals'])
                ->where('assigned_to_user_id', $userId)
                ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'ag_approved');
            } elseif ($tab === 'closed') {
                $query->where('status', 'closed');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'mas_rejected');
            }
            
            // Apply search filter
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
            
            // Apply voucher type filter
            if ($voucherType) {
                $query->where('voucher_type', $voucherType);
            }
            
            // Apply status filter
            if ($status) {
                $query->where('status', $status);
            }
            
            // Apply payment status filter
            if ($paymentStatus) {
                if ($paymentStatus === 'paid') {
                    $query->where('status', 'closed')->whereNotNull('mas_approved_at');
                } elseif ($paymentStatus === 'awaiting_mas') {
                    $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
                }
            }
            
            // Apply date range filter
            if ($dateFrom) {
                $query->whereDate('voucher_date', '>=', $dateFrom);
            }
            if ($dateTo) {
                $query->whereDate('voucher_date', '<=', $dateTo);
            }
            
            $vouchers = $query->paginate($perPage, ['*'], 'page', $page);
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->map(function ($voucher) {
                // Get approval records
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                }
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
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
                    ] : null,
                    'assigned_to' => $voucher->assignedTo ? [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                    ] : null,
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                        ];
                    }),
                ];
            })->values()->toArray();
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ag_approved')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'closed_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'closed')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'mas_rejected')
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => 0,
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::MAS_VOUCHER_TYPES)
                    ->sum('total_amount'),
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
            Log::error('MAS Assigned Search Error: ' . $e->getMessage(), [
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
                    'total_assigned' => 0,
                    'pending_review' => 0,
                    'closed_count' => 0,
                    'rejected_count' => 0,
                    'forwarded_count' => 0,
                    'total_amount' => 0,
                ],
            ]);
        }
    }

    /**
     * Close assigned voucher from Management Account Section (Final Stage)
     */
    public function closeAssigned(Voucher $voucher, Request $request)
    {
        Log::info('MAS Close Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to MAS
        if (!in_array($voucher->voucher_type, self::MAS_VOUCHER_TYPES)) {
            return redirect()->route('management-account-section.assigned')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go through MAS.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                DB::rollBack();
                return redirect()->route('management-account-section.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by AG first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Check if bank is assigned
            if (!$voucher->bank_activity_id) {
                DB::rollBack();
                return redirect()->route('management-account-section.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must have a bank account assigned before closing.");
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $masStep = $maxStep + 1;

            // Create MAS approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_MAS,
                'approval_step' => $masStep,
                'approval_level' => $masStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Voucher closed by Management Account Section'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Update voucher status to closed
            $voucher->update([
                'status' => 'closed',
                'mas_approved_by' => auth()->id(),
                'mas_approved_at' => now(),
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Management Account Section closed assigned voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'mas_step' => $masStep,
                        'closed_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('MAS Close Assigned Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
            ]);

            return redirect()->route('management-account-section.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} has been closed successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MAS Close Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('management-account-section.assigned')
                ->with('error', 'Failed to close voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject assigned voucher from Management Account Section
     */
    public function rejectAssigned(Voucher $voucher, Request $request)
    {
        Log::info('MAS Reject Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'reason' => $request->input('reason'),
        ]);

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('management-account-section.assigned')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'ag_approved') {
                DB::rollBack();
                return redirect()->route('management-account-section.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be AG approved first.");
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
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Management Account Section rejected assigned voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'reason' => $reason,
                        'rejected_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('MAS Reject Assigned Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
            ]);

            return redirect()->route('management-account-section.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('MAS Reject Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('management-account-section.assigned')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }
}