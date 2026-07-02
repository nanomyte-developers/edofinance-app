<?php
// app/Http/Controllers/Admin/TreasuryCashOfficeController.php

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

class TreasuryCashOfficeController extends Controller
{
    protected $activityLogger;

    // Voucher types that go through TCO (Salary and Pension)
    const TCO_VOUCHER_TYPES = ['salary', 'pension'];

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers for Treasury Cash Office review
     * Only Salary and Pension vouchers go to TCO (after Inspectorate)
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
                ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            if ($tab === 'all') {
                // Show all vouchers approved by Inspectorate
                $query->where('status', 'inspectorate_approved');
            } elseif ($tab === 'pending') {
                // Pending TCO review (Inspectorate approved, not yet paid)
                $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
            } elseif ($tab === 'approved') {
                // TCO approved today (paid)
                $query->where('status', 'closed')
                      ->where('tco_approved_by', '>', 0)
                      ->whereDate('tco_approved_at', today());
            } elseif ($tab === 'rejected') {
                // TCO rejected
                $query->where('status', 'tco_rejected');
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
                    $query->where('status', 'closed')->whereNotNull('tco_approved_at');
                } elseif ($paymentStatus === 'awaiting_tco') {
                    $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
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
                $inspectorateApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();

                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->tco_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'inspectorate_approved') {
                    $paymentStatus = 'awaiting_tco';
                }

                // =============================================
                // ADD APPROVALS TO THE TRANSFORMED DATA
                // =============================================
                $approvals = $voucher->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'action' => $approval->action,
                        'comment' => $approval->comment,
                        'action_at' => $approval->action_at?->toDateTimeString(),
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'approval_role' => $approval->approval_role,
                        'status' => $approval->status,
                        'user' => $approval->user ? [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                        ] : null,
                    ];
                });

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'i_approved_at' => $inspectorateApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
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
                    'approvals' => $approvals,
                ];
            });

            // Get statistics
            $stats = [
                'pending_inspectorate_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->count(),
                'pending_tco_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->count(),
                'approved_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)
                    ->whereDate('tco_approved_at', today())
                    ->count(),
                'rejected_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'tco_rejected')
                    ->whereDate('rejected_at', today())
                    ->count(),
                'total_processed' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'tco_rejected'])
                    ->count(),
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->sum('total_amount'),
                'total_amount_paid' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)
                    ->sum('total_amount'),
            ];

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

            // Get users for assignment
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'TCO Staff', 'admin', 'TCO Admin']);
            })->get(['id', 'name', 'email']);

            return Inertia::render('admin/treasuryCashOffice/index', [
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
            Log::error('TCO Index Error: ' . $e->getMessage());
            return Inertia::render('admin/treasuryCashOffice/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'pending_inspectorate_count' => 0,
                    'pending_tco_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_paid' => 0,
                ],
                'users' => [],
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
                ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering - FIXED
            if ($tab === 'all') {
                $query->where('status', 'inspectorate_approved');
            } elseif ($tab === 'pending') {
                $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
            } elseif ($tab === 'approved') {
                // FIXED: Added tco_approved_by > 0 check
                $query->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)
                    ->whereDate('tco_approved_at', today());
            } elseif ($tab === 'rejected') {
                $query->where('status', 'tco_rejected');
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
                    $query->where('status', 'closed')->whereNotNull('tco_approved_at');
                } elseif ($paymentStatus === 'awaiting_tco') {
                    $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
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
                $inspectorateApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();

                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->tco_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'inspectorate_approved') {
                    $paymentStatus = 'awaiting_tco';
                }

                // =============================================
                // ADD APPROVALS TO THE TRANSFORMED DATA
                // =============================================
                $approvals = $voucher->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'action' => $approval->action,
                        'comment' => $approval->comment,
                        'action_at' => $approval->action_at?->toDateTimeString(),
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'approval_role' => $approval->approval_role,
                        'status' => $approval->status,
                        'user' => $approval->user ? [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                        ] : null,
                    ];
                });

                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'i_approved_at' => $inspectorateApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
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
                    'approvals' => $approvals,
                ];
            })->values()->toArray();

            // Get statistics
            $stats = [
                'pending_inspectorate_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->count(),
                'pending_tco_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->count(),
                'approved_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)  // FIXED: Added this
                    ->whereDate('tco_approved_at', today())
                    ->count(),
                'rejected_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'tco_rejected')
                    ->whereDate('rejected_at', today())
                    ->count(),
                'total_processed' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'tco_rejected'])
                    ->count(),
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->sum('total_amount'),
                'total_amount_paid' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)  // FIXED: Added this
                    ->sum('total_amount'),
            ];

            // Get users for assignment
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'TCO Staff', 'admin', 'TCO Admin']);
            })->get(['id', 'name', 'email']);

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
                'users' => $users,
            ]);

        } catch (\Exception $e) {
            Log::error('TCO Search Error: ' . $e->getMessage(), [
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
                    'pending_inspectorate_count' => 0,
                    'pending_tco_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_paid' => 0,
                ],
                'users' => [],
            ]);
        }
    }

    /**
     * Assign bank account to voucher
     */
    public function assignBank(Voucher $voucher, Request $request)
    {
        Log::info('TCO Assign Bank Request:', [
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
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', 'Please select a bank account.');
            }

            // Check if bank exists
            $bank = BankActivity::find($bankActivityId);
            if (!$bank) {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
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

            Log::info('TCO Assign Bank Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'bank_activity_id' => $bankActivityId,
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('success', "Bank account assigned to voucher {$voucher->voucher_number} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TCO Assign Bank Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('error', 'Failed to assign bank: ' . $e->getMessage());
        }
    }

    /**
     * Assign voucher to a staff member
     */
    public function assign(Voucher $voucher, Request $request)
    {
        Log::info('TCO Assign Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'assign_to_user_id' => $request->input('user_id'),
        ]);

        // Check if voucher type should go to TCO
        if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
            return redirect()->route('treasury-cash-office.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to TCO.");
        }

        DB::beginTransaction();

        try {
            $userId = $request->input('user_id');

            if (empty($userId)) {
                Log::warning('CHECK FAILED: User ID is required');
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', 'Please select a staff member to assign this voucher.');
            }

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                Log::warning('CHECK FAILED: User not found', ['user_id' => $userId]);
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', 'Selected staff member not found.');
            }

            // Check if the column exists before updating
            $hasAssignedToColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_to_user_id');
            $hasAssignedAtColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_at');
            $hasAssignedByColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_by');

            // Prepare update data
            $updateData = [];

            if ($hasAssignedToColumn) {
                $updateData['assigned_to_user_id'] = $userId;
            }

            if ($hasAssignedAtColumn) {
                $updateData['assigned_at'] = now();
            }

            if ($hasAssignedByColumn) {
                $updateData['assigned_by'] = auth()->id();
            }

            // If no columns exist, log warning and still process
            if (empty($updateData)) {
                Log::warning('No assignment columns found in vouchers table. Please run migration.');
                if ($this->activityLogger) {
                    $this->activityLogger->log(
                        "Voucher {$voucher->voucher_number} assigned to {$user->name} (columns not available)",
                        [
                            'voucher_id' => $voucher->id,
                            'voucher_number' => $voucher->voucher_number,
                            'voucher_type' => $voucher->voucher_type,
                            'assigned_to' => $userId,
                            'assigned_to_name' => $user->name,
                            'assigned_by' => auth()->id(),
                            'assigned_by_name' => auth()->user()?->name,
                        ],
                        'voucher'
                    );
                }

                DB::commit();

                return redirect()->route('treasury-cash-office.index')
                    ->with('warning', "Voucher {$voucher->voucher_number} assignment logged but database columns are missing. Please run migration.");
            }

            // Update voucher with assigned user
            $updated = $voucher->update($updateData);

            if (!$updated) {
                Log::error('Voucher update failed', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }

            Log::info('Voucher updated successfully', [
                'voucher_id' => $voucher->id,
                'update_data' => $updateData
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Voucher {$voucher->voucher_number} assigned to {$user->name}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'assigned_to' => $userId,
                        'assigned_to_name' => $user->name,
                        'assigned_by' => auth()->id(),
                        'assigned_by_name' => auth()->user()?->name,
                        'assigned_at' => now()->toDateTimeString(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('TCO Assign - Completed Successfully');

            return redirect()->route('treasury-cash-office.index')
                ->with('success', "Voucher {$voucher->voucher_number} assigned to {$user->name} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('TCO Assign - Failed', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('error', 'Failed to assign voucher: ' . $e->getMessage());
        }
    }

    /**
     * Approve voucher from TCO - mark as paid (Final Stage)
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('TCO Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to TCO
        if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
            return redirect()->route('treasury-cash-office.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to TCO.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'inspectorate_approved') {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Inspectorate first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $tcoStep = $maxStep + 1;

            // Create TCO approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_TCO,
                'approval_step' => $tcoStep,
                'approval_level' => $tcoStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Payment processed by TCO'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Update voucher status to closed (paid)
            $voucher->update([
                'status' => 'closed',
                'tco_approved_by' => auth()->id(),
                'tco_approved_at' => now(),
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "TCO approved voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'tco_step' => $tcoStep,
                        'approved_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('TCO Approval Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been approved and marked as paid.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TCO Approval Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from TCO (send back to EC)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('TCO Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        // Check if voucher type should go to TCO
        if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
            return redirect()->route('treasury-cash-office.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to TCO.");
        }

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'inspectorate_approved') {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Inspectorate first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;

            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_TCO,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);

            // Update voucher status - FIXED: Return to EC (not Inspectorate)
            $voucher->update([
                'status' => 'ec_approved',  // Changed from 'tco_rejected' to 'ec_approved'
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "TCO rejected voucher {$voucher->voucher_number} and returned to EC",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'reason' => $reason,
                        'rejection_step' => $rejectionStep,
                        'rejected_by' => auth()->id(),
                        'returned_to' => 'Expenditure Control (EC)',
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('TCO Rejection Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason,
                'voucher_returned_to' => 'EC'
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to Expenditure Control (EC).");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TCO Rejection Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('treasury-cash-office.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Show voucher details for TCO
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

            // Check if voucher type should go through TCO
            if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
                return redirect()->route('treasury-cash-office.index')
                    ->with('error', "This voucher type '{$voucher->voucher_type}' does not go through TCO.");
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

            return Inertia::render('admin/treasuryCashOffice/show', [
                'voucher' => $voucherData,
            ]);

        } catch (\Exception $e) {
            Log::error('TCO Show Error: ' . $e->getMessage());
            return redirect()->route('treasury-cash-office.index')
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
                'pending_inspectorate_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->count(),
                'pending_tco_count' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->count(),
                'approved_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->where('tco_approved_by', '>', 0)
                    ->whereDate('tco_approved_at', today())
                    ->count(),
                'rejected_today' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'tco_rejected')
                    ->whereDate('rejected_at', today())
                    ->count(),
                'total_processed' => Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->whereIn('status', ['closed', 'tco_rejected'])
                    ->count(),
                'total_amount_pending' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->sum('total_amount'),
                'total_amount_paid' => (float) Voucher::whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->where('status', 'closed')
                    ->sum('total_amount'),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('TCO Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_inspectorate_count' => 0,
                'pending_tco_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
                'total_amount_pending' => 0,
                'total_amount_paid' => 0,
            ]);
        }
    }

    /**
     * Display assigned vouchers for TCO staff
     * Shows only vouchers assigned to the current user
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
            
            // Build query - only vouchers assigned to current user and TCO types
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals'])
                ->where('assigned_to_user_id', $userId)
                ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
            } elseif ($tab === 'approved') {
                $query->where('status', 'closed')->whereNotNull('tco_approved_at');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'tco_rejected');
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
                    $query->where('status', 'closed')->whereNotNull('tco_approved_at');
                } elseif ($paymentStatus === 'awaiting_tco') {
                    $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
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
                // Get approval records
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $inspectorateApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->tco_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'inspectorate_approved') {
                    $paymentStatus = 'awaiting_tco';
                }

                // =============================================
                // ADD APPROVALS TO THE TRANSFORMED DATA
                // =============================================
                $approvals = $voucher->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'action' => $approval->action,
                        'comment' => $approval->comment,
                        'action_at' => $approval->action_at?->toDateTimeString(),
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'approval_role' => $approval->approval_role,
                        'status' => $approval->status,
                        'user' => $approval->user ? [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                        ] : null,
                    ];
                });
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'i_approved_at' => $inspectorateApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
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
                    'approvals' => $approvals,
                ];
            });
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'closed')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'tco_rejected')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => 0, // TCO doesn't forward
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];
            
            return Inertia::render('admin/treasuryCashOffice/assigned', [
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
            Log::error('TCO Assigned Index Error: ' . $e->getMessage());
            return Inertia::render('admin/treasuryCashOffice/assigned', [
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
                    'approved_count' => 0,
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
                ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
            } elseif ($tab === 'approved') {
                $query->where('status', 'closed')->whereNotNull('tco_approved_at');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'tco_rejected');
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
                    $query->where('status', 'closed')->whereNotNull('tco_approved_at');
                } elseif ($paymentStatus === 'awaiting_tco') {
                    $query->where('status', 'inspectorate_approved')->whereNull('tco_approved_at');
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
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $inspectorateApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->tco_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'inspectorate_approved') {
                    $paymentStatus = 'awaiting_tco';
                }

                // =============================================
                // ADD APPROVALS TO THE TRANSFORMED DATA
                // =============================================
                $approvals = $voucher->approvals->map(function ($approval) {
                    return [
                        'id' => $approval->id,
                        'action' => $approval->action,
                        'comment' => $approval->comment,
                        'action_at' => $approval->action_at?->toDateTimeString(),
                        'created_at' => $approval->created_at?->toDateTimeString(),
                        'approval_role' => $approval->approval_role,
                        'status' => $approval->status,
                        'user' => $approval->user ? [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                        ] : null,
                    ];
                });
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'i_approved_at' => $inspectorateApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
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
                    'approvals' => $approvals,
                ];
            })->values()->toArray();
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereNull('tco_approved_at')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'closed')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'tco_rejected')
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => 0,
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::TCO_VOUCHER_TYPES)
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
            Log::error('TCO Assigned Search Error: ' . $e->getMessage(), [
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
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'forwarded_count' => 0,
                    'total_amount' => 0,
                ],
            ]);
        }
    }

    /**
     * Approve assigned voucher from TCO - mark as paid
     */
    public function approveAssigned(Voucher $voucher, Request $request)
    {
        Log::info('TCO Approve Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to TCO
        if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
            return redirect()->route('treasury-cash-office.assigned')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to TCO.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'inspectorate_approved') {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Inspectorate first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $tcoStep = $maxStep + 1;

            // Create TCO approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_TCO,
                'approval_step' => $tcoStep,
                'approval_level' => $tcoStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Payment processed by TCO'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Update voucher status to closed (paid)
            $voucher->update([
                'status' => 'closed',
                'tco_approved_by' => auth()->id(),
                'tco_approved_at' => now(),
                'closed_at' => now(),
                'closed_by' => auth()->id(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "TCO approved assigned voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'tco_step' => $tcoStep,
                        'approved_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('TCO Approve Assigned Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
            ]);

            return redirect()->route('treasury-cash-office.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} has been approved and marked as paid.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TCO Approve Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('treasury-cash-office.assigned')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject assigned voucher from TCO (send back to EC)
     */
    public function rejectAssigned(Voucher $voucher, Request $request)
    {
        Log::info('TCO Reject Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        // Check if voucher type should go to TCO
        if (!in_array($voucher->voucher_type, self::TCO_VOUCHER_TYPES)) {
            return redirect()->route('treasury-cash-office.assigned')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to TCO.");
        }

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.assigned')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'inspectorate_approved') {
                DB::rollBack();
                return redirect()->route('treasury-cash-office.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Inspectorate first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;

            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_TCO,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);

            // Update voucher status - FIXED: Return to EC (not Inspectorate)
            $voucher->update([
                'status' => 'ec_approved',  // Changed from 'tco_rejected' to 'ec_approved'
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "TCO rejected assigned voucher {$voucher->voucher_number} and returned to EC",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'reason' => $reason,
                        'rejection_step' => $rejectionStep,
                        'rejected_by' => auth()->id(),
                        'returned_to' => 'Expenditure Control (EC)',
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('TCO Reject Assigned Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason,
                'voucher_returned_to' => 'EC'
            ]);

            return redirect()->route('treasury-cash-office.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to Expenditure Control (EC).");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TCO Reject Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('treasury-cash-office.assigned')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }
}