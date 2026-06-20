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

    // Define voucher types that go to AG
    const AG_VOUCHER_TYPES = ['capital', 'recurrent', 'gratuity', 'standard'];

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers for Accountant General review
     * Only Capital, Recurrent, and Gratuity vouchers go to AG
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

            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            if ($tab === 'all') {
                // Show all vouchers that EC sees (forwarded from FA) - only AG types
                $query->where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'pending') {
                // Pending AG review (EC approved) - only AG types
                $query->where('status', 'ec_approved')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'liability') {
                // Liability vouchers (approved by FA today) - only AG types
                $query->where('status', 'ec_approved')
                    ->whereDate('final_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'approved') {
                // AG approved today - only AG types
                $query->where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
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

            // Apply voucher type filter (if specific type selected)
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
                } elseif ($paymentStatus === 'awaiting_ag') {
                    $query->where('status', 'ec_approved')->whereNull('ag_approved_at');
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
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
                }

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    // 'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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

            // Get statistics - only for AG voucher types
            $stats = [
                'pending_ec_count' => Voucher::where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'approved_today' => Voucher::where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    // ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_AG)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'total_amount_approved' => (float) Voucher::where('status', 'ag_approved')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'liability_count' => Voucher::where('status', 'ec_approved')
                    ->whereDate('final_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
            ];

            return Inertia::render('admin/accountantGeneral/index', [
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
            Log::error('Accountant General Index Error: ' . $e->getMessage());
            return Inertia::render('admin/accountantGeneral/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'pending_ec_count' => 0,
                    'pending_ag_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_approved' => 0,
                    'liability_count' => 0,
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
            $paymentStatus = $request->input('payment_status', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            $tab = $request->input('tab', 'all');

            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals'])
                ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            if ($tab === 'all') {
                $query->where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'pending') {
                $query->where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'liability') {
                $query->where('status', 'ec_approved')
                    ->whereDate('final_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
            } elseif ($tab === 'approved') {
                $query->where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES);
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
                } elseif ($paymentStatus === 'awaiting_ag') {
                    $query->where('status', 'ec_approved')->whereNull('ag_approved_at');
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
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
                }

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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

            // Get statistics - only for AG voucher types
            $stats = [
                'pending_ec_count' => Voucher::where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'approved_today' => Voucher::where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    // ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_AG)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'total_amount_approved' => (float) Voucher::where('status', 'ag_approved')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'liability_count' => Voucher::where('status', 'ec_approved')
                    ->whereDate('final_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
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
            Log::error('Accountant General Search Error: ' . $e->getMessage(), [
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
                    'pending_ec_count' => 0,
                    'pending_ag_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_approved' => 0,
                    'liability_count' => 0,
                ],
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
     * Approve voucher from Accountant General - forwards to MAS
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('Accountant General Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        // Check if voucher type should go to AG
        if (!in_array($voucher->voucher_type, self::AG_VOUCHER_TYPES)) {
            return redirect()->route('accountant-general.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Accountant General. It should go to Inspectorate → TCO.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('accountant-general.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $agStep = $maxStep + 1;
            $nextStep = $agStep + 1;

            $nextRole = VoucherApproval::ROLE_MAS;
            $nextRoleDisplay = 'Management Account Section (MAS)';

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
                'ag_approved_by' => auth()->id(),
                'ag_approved_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Accountant General approved voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'forwarded_to' => $nextRoleDisplay,
                        'ag_step' => $agStep,
                        'approved_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Accountant General Approval Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'forwarded_to' => $nextRoleDisplay
            ]);

            return redirect()->route('accountant-general.index')
                ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Accountant General Approval Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('accountant-general.index')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from Accountant General (send back to EC)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('Accountant General Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        // Check if voucher type should go to AG
        if (!in_array($voucher->voucher_type, self::AG_VOUCHER_TYPES)) {
            return redirect()->route('accountant-general.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Accountant General.");
        }

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('accountant-general.index')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('accountant-general.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;

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
                // 'rejected_at' => now(),
            ]);

            // Update voucher status
            $voucher->update([
                'status' => 'sent_back',
                // 'rejection_reason' => $reason,
                // 'rejected_by' => auth()->id(),
                // 'rejected_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Accountant General rejected voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'reason' => $reason,
                        'rejection_step' => $rejectionStep,
                        'rejected_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Accountant General Rejection Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason
            ]);

            return redirect()->route('accountant-general.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to Expenditure Control.");

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

            // Check if voucher type should go to AG
            if (!in_array($voucher->voucher_type, self::AG_VOUCHER_TYPES)) {
                return redirect()->route('accountant-general.index')
                    ->with('error', "This voucher type '{$voucher->voucher_type}' does not go to Accountant General.");
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
     * Get statistics (API endpoint for AJAX calls)
     */
    public function stats()
    {
        try {
            $stats = [
                'pending_ec_count' => Voucher::where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'approved_today' => Voucher::where('status', 'ag_approved')
                    ->whereDate('ag_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')
                    // ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_AG)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['ag_approved', 'ag_rejected'])
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNull('ag_approved_at')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'total_amount_approved' => (float) Voucher::where('status', 'ag_approved')
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'liability_count' => Voucher::where('status', 'ec_approved')
                    ->whereDate('final_approved_at', today())
                    ->whereIn('voucher_type', self::AG_VOUCHER_TYPES)
                    ->count(),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Accountant General Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_ec_count' => 0,
                'pending_ag_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
                'total_amount_pending' => 0,
                'total_amount_approved' => 0,
                'liability_count' => 0,
            ]);
        }
    }
}