<?php
// app/Http/Controllers/Admin/InspectorateController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class InspectorateController extends Controller
{
    protected $activityLogger;

    // Define voucher types that go to Inspectorate (Salary and Pension)
    const INSPECTORATE_VOUCHER_TYPES = ['salary', 'pension'];

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers for Inspectorate review
     * Only Salary and Pension vouchers go to Inspectorate
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
                ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            if ($tab === 'all') {
                // Show all vouchers from EC (EC approved) - only Salary and Pension
                $query->where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->whereNotNull('final_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
            } elseif ($tab === 'pending') {
                // Pending Inspectorate review (EC approved, not yet inspectorate approved)
                $query->where('status', 'ec_approved')
                    ->where('ec_approved_by', '>', 0)
                    ->where('forwarded_to_inspectorate_by', '>', 0)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
            } elseif ($tab === 'approved') {
                // Inspectorate approved today
                $query->where('status', 'inspectorate_approved')
                    ->whereDate('i_approved_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
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

            // =============================================
            // FIXED STATISTICS - Only for Inspectorate voucher types
            // =============================================
            $stats = [
                // All EC approved vouchers (Salary and Pension) - "Vouchers from EC" tab
                'pending_ec_count' => Voucher::where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->whereNotNull('final_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                
                // EC approved but not yet inspectorate approved - "Pending Inspectorate Review" tab
                'pending_inspectorate_count' => Voucher::where('status', 'ec_approved')
                    ->whereNotNull('ec_approved_at')
                    ->where('ec_approved_by', '>', 0)
                    ->where('forwarded_to_inspectorate_by', '>', 0)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                
                // Inspectorate approved today - "Approved" tab
                'approved_today' => Voucher::where('status', 'inspectorate_approved')
                    ->whereDate('i_approved_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                
                // Rejected by Inspectorate today
                'rejected_today' => Voucher::where('status', 'ec_review')
                    ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                
                // Total processed by Inspectorate
                'total_processed' => Voucher::whereIn('status', ['inspectorate_approved', 'ec_review'])
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                
                // Total amount pending Inspectorate review (EC approved, not yet inspectorate approved)
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNotNull('i_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
                
                // Total amount approved by Inspectorate
                'total_amount_approved' => (float) Voucher::where('status', 'inspectorate_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];

            // Get users for assignment - FIX THIS SECTION
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'Inspectorate Staff', 'admin', 'Inspectorate Admin']);
            })->get(['id', 'name', 'email']);

            return Inertia::render('admin/inspectorate/index', [
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
            ]);

        } catch (\Exception $e) {
            Log::error('Inspectorate Index Error: ' . $e->getMessage());
            return Inertia::render('admin/inspectorate/index', [
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
                    'pending_inspectorate_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_approved' => 0,
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
                ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');

            // Tab filtering
            if ($tab === 'all') {
                $query->where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->whereNotNull('final_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
            } elseif ($tab === 'pending') {
                $query->where('status', 'ec_approved')
                    ->where('ec_approved_by', '>', 0)
                    ->whereNotNull('ec_approved_at')
                    ->where('forwarded_to_inspectorate_by', '>', 0)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
            } elseif ($tab === 'approved') {
                $query->where('status', 'inspectorate_approved')
                    ->whereDate('i_approved_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES);
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

            // =============================================
            // FIXED STATISTICS FOR SEARCH
            // =============================================
            $stats = [
                'pending_ec_count' => Voucher::where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->whereNotNull('final_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'pending_inspectorate_count' => Voucher::where('status', 'ec_approved')
                    ->whereNotNull('ec_approved_at')
                    ->where('ec_approved_by', '>', 0)
                    ->where('forwarded_to_inspectorate_by', '>', 0)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'approved_today' => Voucher::where('status', 'inspectorate_approved')
                    ->whereDate('i_approved_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'rejected_today' => Voucher::where('status', 'ec_review')
                    // ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['inspectorate_approved', 'ec_review'])
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNotNull('ec_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'total_amount_approved' => (float) Voucher::where('status', 'inspectorate_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];

            // Get users for assignment - ADD THIS
            $users = User::whereHas('roles', function ($query) {
                $query->whereIn('name', ['staff', 'Inspectorate Staff', 'admin', 'Inspectorate Admin']);
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
            Log::error('Inspectorate Search Error: ' . $e->getMessage(), [
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
                    'pending_inspectorate_count' => 0,
                    'approved_today' => 0,
                    'rejected_today' => 0,
                    'total_processed' => 0,
                    'total_amount_pending' => 0,
                    'total_amount_approved' => 0,
                ],
            ]);
        }
    }

    /**
     * Assign voucher to a staff member
     */
    public function assign(Voucher $voucher, Request $request)
    {
        Log::info('Inspectorate Assign Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'assign_to_user_id' => $request->input('user_id'),
        ]);

        // Check if voucher type should go to Inspectorate
        if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
            return redirect()->route('inspectorate.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
        }

        DB::beginTransaction();

        try {
            $userId = $request->input('user_id');

            if (empty($userId)) {
                Log::warning('CHECK FAILED: User ID is required');
                DB::rollBack();
                return redirect()->route('inspectorate.index')
                    ->with('error', 'Please select a staff member to assign this voucher.');
            }

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                Log::warning('CHECK FAILED: User not found', ['user_id' => $userId]);
                DB::rollBack();
                return redirect()->route('inspectorate.index')
                    ->with('error', 'Selected staff member not found.');
            }

            // Check if the column exists before updating
            $hasAssignedToColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_to_user_id');
            $hasAssignedAtColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_at');
            $hasAssignedByColumn = \Illuminate\Support\Facades\Schema::hasColumn('vouchers', 'assigned_by');

            Log::info('Column existence check:', [
                'assigned_to_user_id' => $hasAssignedToColumn,
                'assigned_at' => $hasAssignedAtColumn,
                'assigned_by' => $hasAssignedByColumn,
            ]);

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
                // Still log the assignment in activity log
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

                return redirect()->route('inspectorate.index')
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

            Log::info('========================================');
            Log::info('INSPECTORATE ASSIGN - COMPLETED SUCCESSFULLY');
            Log::info('========================================');

            return redirect()->route('inspectorate.index')
                ->with('success', "Voucher {$voucher->voucher_number} assigned to {$user->name} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('========================================');
            Log::error('INSPECTORATE ASSIGN - FAILED');
            Log::error('========================================');
            Log::error('Assign Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()->route('inspectorate.index')
                ->with('error', 'Failed to assign voucher: ' . $e->getMessage());
        }
    }

    /**
     * Approve voucher from Inspectorate - forwards to TCO
     */
    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('Inspectorate Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to Inspectorate
        if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
            return redirect()->route('inspectorate.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('inspectorate.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $inspectorateStep = $maxStep + 1;
            $nextStep = $inspectorateStep + 1;

            $nextRole = VoucherApproval::ROLE_TCO;
            $nextRoleDisplay = 'Treasury Cash Office (TCO)';

            // Create Inspectorate approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_INSPECTORATE,
                'approval_step' => $inspectorateStep,
                'approval_level' => $inspectorateStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Approved by Inspectorate'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Create forward to TCO record
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
                'status' => 'inspectorate_approved',
                'i_approved_by' => auth()->id(),
                'i_approved_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Inspectorate approved voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'forwarded_to' => $nextRoleDisplay,
                        'inspectorate_step' => $inspectorateStep,
                        'approved_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Inspectorate Approval Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'forwarded_to' => $nextRoleDisplay
            ]);

            return redirect()->route('inspectorate.index')
                ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inspectorate Approval Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('inspectorate.index')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject voucher from Inspectorate (send back to EC)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('Inspectorate Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        // Check if voucher type should go to Inspectorate
        if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
            return redirect()->route('inspectorate.index')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
        }

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('inspectorate.index')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('inspectorate.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;

            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_INSPECTORATE,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);

            // Update voucher status - RETURN TO EC FOR REVIEW
            $voucher->update([
                'status' => 'ec_review', // Changed from 'ec_review' to 'ec_review'
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                // Clear approval fields so it can go through the flow again
                'i_approved_by' => null,
                'i_approved_at' => null,
                'ec_approved_by' => null,
                'ec_approved_at' => null,
                'forwarded_to_inspectorate_by' => null,
                'forwarded_to_inspectorate_at' => null,
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Inspectorate rejected voucher {$voucher->voucher_number} and returned to EC for review",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'reason' => $reason,
                        'rejection_step' => $rejectionStep,
                        'rejected_by' => auth()->id(),
                        'returned_to' => 'EC',
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Inspectorate Rejection Successful - Returned to EC:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'new_status' => 'ec_review',
                'reason' => $reason
            ]);

            return redirect()->route('inspectorate.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to Expenditure Control for review.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inspectorate Rejection Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('inspectorate.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Show voucher details for Inspectorate
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

            // Check if voucher type should go to Inspectorate
            if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
                return redirect()->route('inspectorate.index')
                    ->with('error', "This voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
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

            return Inertia::render('admin/inspectorate/show', [
                'voucher' => $voucherData,
            ]);

        } catch (\Exception $e) {
            Log::error('Inspectorate Show Error: ' . $e->getMessage());
            return redirect()->route('inspectorate.index')
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
                'pending_ec_count' => Voucher::where('status', 'ec_approved')
                    ->whereNotNull('ec_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'pending_inspectorate_count' => Voucher::where('status', 'ec_approved')
                    ->whereNotNull('ec_approved_at')
                    ->where('ec_approved_by', '>', 0)
                    ->where('forwarded_to_inspectorate_by', '>', 0)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'approved_today' => Voucher::where('status', 'inspectorate_approved')
                    ->whereDate('i_approved_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'rejected_today' => Voucher::where('status', 'ec_review')
                    ->whereDate('rejected_at', today())
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->whereHas('approvals', function ($q) {
                        $q->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)
                            ->where('action', VoucherApproval::ACTION_DECLINED);
                    })
                    ->count(),
                'total_processed' => Voucher::whereIn('status', ['inspectorate_approved', 'ec_review'])
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'total_amount_pending' => (float) Voucher::where('status', 'ec_approved')
                    ->whereNull('i_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
                'total_amount_approved' => (float) Voucher::where('status', 'inspectorate_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];

            return response()->json($stats);

        } catch (\Exception $e) {
            Log::error('Inspectorate Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_ec_count' => 0,
                'pending_inspectorate_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
                'total_amount_pending' => 0,
                'total_amount_approved' => 0,
            ]);
        }
    }

    /**
     * Display assigned vouchers for Inspectorate staff
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
            
            // Build query - only vouchers assigned to current user and Inspectorate types
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'assignedTo'])
                ->where('assigned_to_user_id', $userId)
                ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'ec_approved');
            } elseif ($tab === 'approved') {
                $query->where('status', 'inspectorate_approved');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'ec_review');
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
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_review')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereNotNull('i_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->sum('total_amount'),
            ];
            
            // Return with proper pagination structure
            return Inertia::render('admin/inspectorate/assigned', [
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
            Log::error('Inspectorate Assigned Index Error: ' . $e->getMessage());
            return Inertia::render('admin/inspectorate/assigned', [
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
                ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'ec_approved');
            } elseif ($tab === 'approved') {
                $query->where('status', 'inspectorate_approved');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'ec_review');
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
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_review')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'forwarded_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'inspectorate_approved')
                    ->whereNotNull('i_approved_at')
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
                    ->count(),
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->whereIn('voucher_type', self::INSPECTORATE_VOUCHER_TYPES)
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
            Log::error('Inspectorate Assigned Search Error: ' . $e->getMessage(), [
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
     * Approve assigned voucher from Inspectorate - forwards to TCO
     */
    public function approveAssigned(Voucher $voucher, Request $request)
    {
        Log::info('Inspectorate Approve Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
        ]);

        // Check if voucher type should go to Inspectorate
        if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
            return redirect()->route('inspectorate.assigned')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
        }

        DB::beginTransaction();

        try {
            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('inspectorate.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $inspectorateStep = $maxStep + 1;
            $nextStep = $inspectorateStep + 1;

            $nextRole = VoucherApproval::ROLE_TCO;
            $nextRoleDisplay = 'Treasury Cash Office (TCO)';

            // Create Inspectorate approval record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_INSPECTORATE,
                'approval_step' => $inspectorateStep,
                'approval_level' => $inspectorateStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Approved by Inspectorate'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);

            // Create forward to TCO record
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
                'status' => 'inspectorate_approved',
                'i_approved_by' => auth()->id(),
                'i_approved_at' => now(),
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Inspectorate approved assigned voucher {$voucher->voucher_number}",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'forwarded_to' => $nextRoleDisplay,
                        'inspectorate_step' => $inspectorateStep,
                        'approved_by' => auth()->id(),
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Inspectorate Approve Assigned Successful:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_type' => $voucher->voucher_type,
                'forwarded_to' => $nextRoleDisplay
            ]);

            return redirect()->route('inspectorate.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay} successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inspectorate Approve Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('inspectorate.assigned')
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject assigned voucher from Inspectorate - send back to EC
     */
    public function rejectAssigned(Voucher $voucher, Request $request)
    {
        Log::info('Inspectorate Reject Assigned Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'user_id' => auth()->id(),
            'reason' => $request->input('reason')
        ]);

        // Check if voucher type should go to Inspectorate
        if (!in_array($voucher->voucher_type, self::INSPECTORATE_VOUCHER_TYPES)) {
            return redirect()->route('inspectorate.assigned')
                ->with('error', "Voucher type '{$voucher->voucher_type}' does not go to Inspectorate.");
        }

        DB::beginTransaction();

        try {
            $reason = $request->input('reason');

            if (empty($reason)) {
                DB::rollBack();
                return redirect()->route('inspectorate.assigned')
                    ->with('error', 'Rejection reason is required.');
            }

            // Check if voucher is in correct state
            if ($voucher->status !== 'ec_approved') {
                DB::rollBack();
                return redirect()->route('inspectorate.assigned')
                    ->with('error', "Voucher {$voucher->voucher_number} must be approved by Expenditure Control first. Current status: " . ($voucher->status ?? 'unknown'));
            }

            // Get the current maximum approval step
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;

            // Create rejection record
            VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_INSPECTORATE,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);

            // Update voucher status - RETURN TO EC FOR REVIEW
            $voucher->update([
                'status' => 'ec_review', // Changed from 'ec_review' to 'ec_review'
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
                // Clear approval fields so it can go through the flow again
                'i_approved_by' => null,
                'i_approved_at' => null,
                'ec_approved_by' => null,
                'ec_approved_at' => null,
                'forwarded_to_inspectorate_by' => null,
                'forwarded_to_inspectorate_at' => null,
            ]);

            // Log activity
            if ($this->activityLogger) {
                $this->activityLogger->log(
                    "Inspectorate rejected assigned voucher {$voucher->voucher_number} and returned to EC for review",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => $voucher->voucher_type,
                        'reason' => $reason,
                        'rejection_step' => $rejectionStep,
                        'rejected_by' => auth()->id(),
                        'returned_to' => 'EC',
                    ],
                    'voucher'
                );
            }

            DB::commit();

            Log::info('Inspectorate Reject Assigned Successful - Returned to EC:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'new_status' => 'ec_review',
                'reason' => $reason
            ]);

            return redirect()->route('inspectorate.assigned')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to Expenditure Control for review.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Inspectorate Reject Assigned Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->route('inspectorate.assigned')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }
}