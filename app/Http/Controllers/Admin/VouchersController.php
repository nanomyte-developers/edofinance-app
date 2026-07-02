<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\NumberToWords;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoucherStoreUpdateRequest;
use App\Models\AdministrativeCode;
use App\Models\BankActivity;
use App\Models\EconomyCode;
use App\Models\EconomyCodeItem;
use App\Models\FinancialYear;
use App\Models\Mda;
use App\Models\ProgrammeCode;
use App\Models\Schedule;
use App\Models\ScheduleItem;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Services\ActivityLogger;
use App\Services\BudgetService;
use App\Services\VoucherService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class VouchersController extends Controller
{
    protected VoucherService $voucherService;
    protected $activityLogger;
    protected BudgetService $budgetService;

    public function __construct(VoucherService $voucherService, ActivityLogger $activityLogger, BudgetService $budgetService)
    {
        $this->voucherService = $voucherService;
        $this->activityLogger = $activityLogger;
        $this->budgetService = $budgetService;
    }

  /**
     * Check if user is DFA subordinate
     */
    private function isDFASubordinate(): bool
    {
        $user = Auth::user();
        return $user->hasPermissionTo('dfa.subordinate') && !$user->hasPermissionTo('dfa.main');
    }

    /**
     * Check if user is DFA main (can submit for approval)
     */
    private function isDFAMain(): bool
    {
        $user = Auth::user();
        return $user->hasRole('Director of Finance') || $user->hasPermissionTo('dfa.main');
    }

    /**
     * Get user's DFA permissions for the view
     */
    private function getDFAPermissions(): array
    {
        $user = Auth::user();
        
        return [
            'can_submit_for_approval' => $user->hasRole('Director of Finance') || $user->hasPermissionTo('dfa.main'),
            'can_save_as_draft' => $user->hasPermissionTo('dfa.subordinate') || $user->hasRole('Director of Finance') || $user->hasPermissionTo('dfa.main'),
            'is_subordinate' => $user->hasPermissionTo('dfa.subordinate') && !$user->hasPermissionTo('dfa.main'),
            'is_dfa_main' => $user->hasRole('Director of Finance') || $user->hasPermissionTo('dfa.main'),
            'can_view' => $user->hasPermissionTo('dfa.view') || $user->hasRole('Director of Finance'),
        ];
    }

    /**
     * Get the MDAs assigned to the current user as array
     */
    private function getUserAssignedMdas(): array
    {
        $user = Auth::user();
        
        // If user has admin role or specific permission, return empty array (no filter - show all)
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_vouchers')) {
            return []; // Empty array means no filter - show all
        }
        
        // Get the MDAs assigned to this user
        $mdas = $user->mdas()
            ->select('mdas.id', 'mdas.name', 'mdas.code')
            ->get()
            ->map(function($mda) {
                return [
                    'id' => $mda->id,
                    'name' => $mda->name,
                    'code' => $mda->code ?? '',
                ];
            })
            ->toArray();
        
        return $mdas;
    }

    /**
     * Get the MDA IDs assigned to the current user
     */
    private function getUserAssignedMdaIds(): array
    {
        $mdas = $this->getUserAssignedMdas();
        return array_column($mdas, 'id');
    }

    /**
     * Check if user has any MDAs assigned
     */
    private function hasAssignedMdas(): bool
    {
        $user = Auth::user();
        
        // Admin users don't need MDA assignments
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_vouchers')) {
            return true; // Admin can see all
        }
        
        // Check if user has any MDAs assigned
        return $user->mdas()->exists();
    }

    /**
     * Check if user is admin
     */
    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user->hasRole('admin') || $user->hasPermissionTo('view_all_vouchers');
    }

    /**
     * Apply MDA filter to the query if needed
     */
    private function applyMdaFilter($query)
    {
        $user = Auth::user();
        
        // If user is admin, no filter needed
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_vouchers')) {
            return $query;
        }
        
        $mdaIds = $this->getUserAssignedMdaIds();
        
        // If user has no MDAs assigned, return query that will show no records
        if (empty($mdaIds)) {
            return $query->whereRaw('1 = 0'); // Force empty result
        }
        
        // Filter by assigned MDAs
        return $query->whereIn('mda_id', $mdaIds);
    }

    /**
     * Display a listing of vouchers (Main index)
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
            
            // Check if the assigned_to_user_id column exists
            $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
            // ✅ Get user's assigned MDAs for display
            $assignedMdas = $this->getUserAssignedMdas();
            $isAdmin = $this->isAdmin();
            $hasMdas = $this->hasAssignedMdas();
            
            // Build the query with conditional eager loading
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'documents']);
            
            // Only load assignedTo relationship if the column exists and relationship is defined
            if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
                $query->with('assignedTo');
            }
            
            // ✅ Apply MDA filter based on user's assigned MDAs
            $query = $this->applyMdaFilter($query);
            
            // Base query - order by created_at desc
            $query->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'submitted');
            } elseif ($tab === 'approved') {
                $query->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved']);
            } elseif ($tab === 'rejected') {
                $query->whereIn('status', ['rejected', 'sent_back']);
            } elseif ($tab === 'forwarded') {
                $query->where('status', 'forwarded');
            } elseif ($tab === 'liability') {
                $query->whereDate('final_approved_at', today());
            } elseif ($tab === 'draft') {
                $query->whereIn('status', ['draft', 'saved']);
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
                } elseif ($paymentStatus === 'awaiting_ec') {
                    $query->where('status', 'fa_approved')->whereNull('ec_approved_at');
                } elseif ($paymentStatus === 'awaiting_audit') {
                    $query->where('status', 'submitted')->whereNull('final_approved_at');
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
            $transformedVouchers = $vouchers->through(function ($voucher) use ($hasAssignment) {
                // Get approval records for display
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $iaApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_IA)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
                } elseif ($voucher->status === 'fa_approved') {
                    $paymentStatus = 'awaiting_ec';
                } elseif ($voucher->status === 'submitted') {
                    $paymentStatus = 'awaiting_audit';
                }
                
                $data = [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ia_approved_at' => $iaApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
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
                    'approvals' => $voucher->approvals->map(function ($approval) {
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
                    }),
                    'documents' => $voucher->documents->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'file_name' => $document->file_name,
                            'file_path' => $document->file_path,
                            'mime_type' => $document->mime_type,
                            'size' => $document->size,
                        ];
                    }),
                ];
                
                // Only add assigned_to if the column exists and relationship is loaded
                if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
                    $data['assigned_to'] = [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                        'email' => $voucher->assignedTo->email ?? null,
                    ];
                } else {
                    $data['assigned_to'] = null;
                }
                
                return $data;
            });
            
            // ✅ Get statistics - filtered by user's MDAs
            $statsQuery = Voucher::query();
            $statsQuery = $this->applyMdaFilter($statsQuery);
            
            $stats = [
                'total_vouchers' => $statsQuery->count(),
                'pending_count' => (clone $statsQuery)->where('status', 'submitted')->count(),
                'approved_count' => (clone $statsQuery)->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved'])->count(),
                'rejected_count' => (clone $statsQuery)->whereIn('status', ['rejected', 'sent_back'])->count(),
                'forwarded_count' => (clone $statsQuery)->where('status', 'forwarded')->count(),
                'draft_count' => (clone $statsQuery)->whereIn('status', ['draft', 'saved'])->count(),
                'paid_count' => (clone $statsQuery)->where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => (clone $statsQuery)->where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => (clone $statsQuery)->where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'pending_ec_count' => (clone $statsQuery)->where('status', 'fa_approved')->whereNull('ec_approved_at')->count(),
                'pending_audit_count' => (clone $statsQuery)->where('status', 'submitted')->whereNull('final_approved_at')->count(),
                'total_amount' => (float) (clone $statsQuery)->sum('total_amount'),
                'total_amount_paid' => (float) (clone $statsQuery)->where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) (clone $statsQuery)->whereIn('status', ['submitted', 'fa_approved', 'ec_approved', 'ag_approved'])->sum('total_amount'),
                'liability_count' => (clone $statsQuery)->whereDate('final_approved_at', today())->count(),
            ];
            
            // Get users for assignment if needed
            $users = $this->getUsersForAssignment();
            
            return Inertia::render('admin/vouchers/index', [
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
                'userMdas' => $assignedMdas,
                'isAdmin' => $isAdmin,
                'hasMdas' => $hasMdas, // ✅ Pass this to Vue
            ]);
            
        } catch (\Exception $e) {
            Log::error('Voucher Index Error: ' . $e->getMessage());
            return Inertia::render('admin/vouchers/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'from' => 0,
                    'to' => 0,
                ],
                'stats' => [
                    'total_vouchers' => 0,
                    'pending_count' => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'forwarded_count' => 0,
                    'draft_count' => 0,
                    'paid_count' => 0,
                    'pending_mas_count' => 0,
                    'pending_ag_count' => 0,
                    'pending_ec_count' => 0,
                    'pending_audit_count' => 0,
                    'total_amount' => 0,
                    'total_amount_paid' => 0,
                    'total_amount_pending' => 0,
                    'liability_count' => 0,
                ],
                'users' => [],
                'userMdas' => [],
                'isAdmin' => false,
                'hasMdas' => false,
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
            
            // Check if the assigned_to_user_id column exists
            $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
            // Build query with conditional eager loading
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals', 'documents']);
            
            // Only load assignedTo relationship if the column exists and relationship is defined
            if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
                $query->with('assignedTo');
            }
            
            // ✅ Apply MDA filter based on user's assigned MDAs
            $query = $this->applyMdaFilter($query);
            
            // Base query - order by created_at desc
            $query->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'submitted');
            } elseif ($tab === 'approved') {
                $query->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved']);
            } elseif ($tab === 'rejected') {
                $query->whereIn('status', ['rejected', 'sent_back']);
            } elseif ($tab === 'forwarded') {
                $query->where('status', 'forwarded');
            } elseif ($tab === 'liability') {
                $query->whereDate('final_approved_at', today());
            } elseif ($tab === 'draft') {
                $query->whereIn('status', ['draft', 'saved']);
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
                } elseif ($paymentStatus === 'awaiting_ec') {
                    $query->where('status', 'fa_approved')->whereNull('ec_approved_at');
                } elseif ($paymentStatus === 'awaiting_audit') {
                    $query->where('status', 'submitted')->whereNull('final_approved_at');
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
            $transformedVouchers = $vouchers->map(function ($voucher) use ($hasAssignment) {
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
                } elseif ($voucher->status === 'fa_approved') {
                    $paymentStatus = 'awaiting_ec';
                } elseif ($voucher->status === 'submitted') {
                    $paymentStatus = 'awaiting_audit';
                }
                
                $data = [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
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
                        ];
                    }),
                    'approvals' => $voucher->approvals->map(function ($approval) {
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
                    }),
                    'documents' => $voucher->documents->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'file_name' => $document->file_name,
                            'file_path' => $document->file_path,
                            'mime_type' => $document->mime_type,
                            'size' => $document->size,
                        ];
                    }),
                ];
                
                // Only add assigned_to if the column exists and relationship is loaded
                if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
                    $data['assigned_to'] = [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                        'email' => $voucher->assignedTo->email ?? null,
                    ];
                } else {
                    $data['assigned_to'] = null;
                }
                
                return $data;
            })->values()->toArray();
            
            // ✅ Get statistics - filtered by user's MDAs
            $statsQuery = Voucher::query();
            $statsQuery = $this->applyMdaFilter($statsQuery);
            
            $stats = [
                'total_vouchers' => $statsQuery->count(),
                'pending_count' => (clone $statsQuery)->where('status', 'submitted')->count(),
                'approved_count' => (clone $statsQuery)->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved'])->count(),
                'rejected_count' => (clone $statsQuery)->whereIn('status', ['rejected', 'sent_back'])->count(),
                'forwarded_count' => (clone $statsQuery)->where('status', 'forwarded')->count(),
                'draft_count' => (clone $statsQuery)->whereIn('status', ['draft', 'saved'])->count(),
                'paid_count' => (clone $statsQuery)->where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => (clone $statsQuery)->where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => (clone $statsQuery)->where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'pending_ec_count' => (clone $statsQuery)->where('status', 'fa_approved')->whereNull('ec_approved_at')->count(),
                'pending_audit_count' => (clone $statsQuery)->where('status', 'submitted')->whereNull('final_approved_at')->count(),
                'total_amount' => (float) (clone $statsQuery)->sum('total_amount'),
                'total_amount_paid' => (float) (clone $statsQuery)->where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) (clone $statsQuery)->whereIn('status', ['submitted', 'fa_approved', 'ec_approved', 'ag_approved'])->sum('total_amount'),
                'liability_count' => (clone $statsQuery)->whereDate('final_approved_at', today())->count(),
            ];
            
            // Get users for assignment
            $users = $this->getUsersForAssignment();
            
            return response()->json([
                'status' => 'success',
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
                'isAdmin' => $this->isAdmin(),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Voucher Search Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
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
                    'total_vouchers' => 0,
                    'pending_count' => 0,
                    'approved_count' => 0,
                    'rejected_count' => 0,
                    'forwarded_count' => 0,
                    'draft_count' => 0,
                    'paid_count' => 0,
                    'pending_mas_count' => 0,
                    'pending_ag_count' => 0,
                    'pending_ec_count' => 0,
                    'pending_audit_count' => 0,
                    'total_amount' => 0,
                    'total_amount_paid' => 0,
                    'total_amount_pending' => 0,
                    'liability_count' => 0,
                ],
                'users' => [],
                'isAdmin' => false,
            ]);
        }
    }

    /**
     * Get users for assignment (using Spatie roles)
     */
    private function getUsersForAssignment()
    {
        try {
            $roles = ['admin', 'Expenditure Controller'];
            $existingRoles = \Spatie\Permission\Models\Role::whereIn('name', $roles)->pluck('name')->toArray();
            
            if (empty($existingRoles)) {
                Log::warning('No roles found for assignment');
                return [];
            }
            
            $users = \App\Models\User::role($existingRoles)
                ->select('id', 'name', 'email')
                ->get()
                ->map(function ($user) {
                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ];
                })
                ->toArray();
            
            return $users;
        } catch (\Exception $e) {
            \Log::error('Error fetching users for assignment: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get the MDAs assigned to the current user
     */
    // private function getUserAssignedMdaIds()
    // {
    //     $user = Auth::user();
        
    //     // If user has admin role or specific permission, return null (no filter)
    //     if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_vouchers')) {
    //         return null; // null means no MDA filter - show all
    //     }
        
    //     // Get the MDA IDs assigned to this user
    //     $mdaIds = $user->mdas()->pluck('mda_id')->toArray();
        
    //     // If user has no MDAs assigned, return empty array (will show no vouchers)
    //     if (empty($mdaIds)) {
    //         return [];
    //     }
        
    //     return $mdaIds;
    // }

    /**
     * Apply MDA filter to the query if needed
     */
    // private function applyMdaFilter($query)
    // {
    //     $mdaIds = $this->getUserAssignedMdaIds();
        
    //     // If null, user has admin permissions - no filter needed
    //     if ($mdaIds === null) {
    //         return $query;
    //     }
        
    //     // If empty array, user has no MDAs assigned - return query that will show nothing
    //     if (empty($mdaIds)) {
    //         return $query->whereRaw('1 = 0'); // Force empty result
    //     }
        
    //     // Filter by assigned MDAs
    //     return $query->whereIn('mda_id', $mdaIds);
    // }

    /**
     * Display a listing of vouchers (Main index)
     */
    // public function index(Request $request)
    // {
    //     try {
    //         $perPage = $request->input('per_page', 15);
    //         $search = $request->input('search', '');
    //         $voucherType = $request->input('voucher_type', '');
    //         $status = $request->input('status', '');
    //         $paymentStatus = $request->input('payment_status', '');
    //         $dateFrom = $request->input('date_from', '');
    //         $dateTo = $request->input('date_to', '');
    //         $tab = $request->input('tab', 'all');
            
    //         // Check if the assigned_to_user_id column exists
    //         $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
    //         // Build the query with conditional eager loading
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'documents']);
            
    //         // Only load assignedTo relationship if the column exists and relationship is defined
    //         if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
    //             $query->with('assignedTo');
    //         }
            
    //         // Base query - exclude soft deleted records
    //         $query->orderBy('created_at', 'desc');
            
    //         // Apply tab filter
    //         if ($tab === 'pending') {
    //             $query->where('status', 'submitted');
    //         } elseif ($tab === 'approved') {
    //             $query->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved']);
    //         } elseif ($tab === 'rejected') {
    //             $query->whereIn('status', ['rejected', 'sent_back']);
    //         } elseif ($tab === 'forwarded') {
    //             $query->where('status', 'forwarded');
    //         } elseif ($tab === 'liability') {
    //             $query->whereDate('final_approved_at', today());
    //         } elseif ($tab === 'draft') {
    //             $query->whereIn('status', ['draft', 'saved']);
    //         }
            
    //         // Apply search filter
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
            
    //         // Apply voucher type filter
    //         if ($voucherType) {
    //             $query->where('voucher_type', $voucherType);
    //         }
            
    //         // Apply status filter
    //         if ($status) {
    //             $query->where('status', $status);
    //         }
            
    //         // Apply payment status filter
    //         if ($paymentStatus) {
    //             if ($paymentStatus === 'paid') {
    //                 $query->where('status', 'closed')->whereNotNull('mas_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_mas') {
    //                 $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_ag') {
    //                 $query->where('status', 'ec_approved')->whereNull('ag_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_ec') {
    //                 $query->where('status', 'fa_approved')->whereNull('ec_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_audit') {
    //                 $query->where('status', 'submitted')->whereNull('final_approved_at');
    //             }
    //         }
            
    //         // Apply date range filter
    //         if ($dateFrom) {
    //             $query->whereDate('voucher_date', '>=', $dateFrom);
    //         }
    //         if ($dateTo) {
    //             $query->whereDate('voucher_date', '<=', $dateTo);
    //         }
            
    //         $vouchers = $query->paginate($perPage);
            
    //         // Transform the data for the frontend
    //         $transformedVouchers = $vouchers->through(function ($voucher) use ($hasAssignment) {
    //             // Get approval records for display
    //             $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
    //             $iaApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_IA)->first();
    //             $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
    //             $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
    //             $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
    //             // Determine payment status
    //             $paymentStatus = 'unknown';
    //             if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
    //                 $paymentStatus = 'paid';
    //             } elseif ($voucher->status === 'ag_approved') {
    //                 $paymentStatus = 'awaiting_mas';
    //             } elseif ($voucher->status === 'ec_approved') {
    //                 $paymentStatus = 'awaiting_ag';
    //             } elseif ($voucher->status === 'fa_approved') {
    //                 $paymentStatus = 'awaiting_ec';
    //             } elseif ($voucher->status === 'submitted') {
    //                 $paymentStatus = 'awaiting_audit';
    //             }
                
    //             $data = [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
    //                 'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => (float) $voucher->total_amount,
    //                 'payee_name' => $voucher->payee_name,
    //                 'status' => $voucher->status,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'payment_status' => $paymentStatus,
    //                 'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
    //                 'ia_approved_at' => $iaApproval?->approved_at?->toDateTimeString(),
    //                 'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
    //                 'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
    //                 'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
    //                 'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'code' => $voucher->mda->code,
    //                 ] : null,
    //                 'bank_activity' => $voucher->bankActivity ? [
    //                     'id' => $voucher->bankActivity->id,
    //                     'bank_name' => $voucher->bankActivity->bank_name,
    //                     'account_number' => $voucher->bankActivity->account_number,
    //                     'tag' => $voucher->bankActivity->tag,
    //                     'title' => $voucher->bankActivity->title,
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
    //                 'approvals' => $voucher->approvals->map(function ($approval) {
    //                     return [
    //                         'id' => $approval->id,
    //                         'action' => $approval->action,
    //                         'comment' => $approval->comment,
    //                         'action_at' => $approval->action_at?->toDateTimeString(),
    //                         'created_at' => $approval->created_at?->toDateTimeString(),
    //                         'approval_role' => $approval->approval_role,
    //                         'status' => $approval->status,
    //                         'user' => $approval->user ? [
    //                             'id' => $approval->user->id,
    //                             'name' => $approval->user->name,
    //                         ] : null,
    //                     ];
    //                 }),
    //                 'documents' => $voucher->documents->map(function ($document) {
    //                     return [
    //                         'id' => $document->id,
    //                         'file_name' => $document->file_name,
    //                         'file_path' => $document->file_path,
    //                         'mime_type' => $document->mime_type,
    //                         'size' => $document->size,
    //                     ];
    //                 }),
    //             ];
                
    //             // Only add assigned_to if the column exists and relationship is loaded
    //             if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
    //                 $data['assigned_to'] = [
    //                     'id' => $voucher->assignedTo->id,
    //                     'name' => $voucher->assignedTo->name,
    //                     'email' => $voucher->assignedTo->email ?? null,
    //                 ];
    //             } else {
    //                 $data['assigned_to'] = null;
    //             }
                
    //             return $data;
    //         });
            
    //         // Get statistics
    //         $stats = [
    //             'total_vouchers' => Voucher::count(),
    //             'pending_count' => Voucher::where('status', 'submitted')->count(),
    //             'approved_count' => Voucher::whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved'])->count(),
    //             'rejected_count' => Voucher::whereIn('status', ['rejected', 'sent_back'])->count(),
    //             'forwarded_count' => Voucher::where('status', 'forwarded')->count(),
    //             'draft_count' => Voucher::whereIn('status', ['draft', 'saved'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'pending_ec_count' => Voucher::where('status', 'fa_approved')->whereNull('ec_approved_at')->count(),
    //             'pending_audit_count' => Voucher::where('status', 'submitted')->whereNull('final_approved_at')->count(),
    //             'total_amount' => (float) Voucher::sum('total_amount'),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['submitted', 'fa_approved', 'ec_approved', 'ag_approved'])->sum('total_amount'),
    //             'liability_count' => Voucher::whereDate('final_approved_at', today())->count(),
    //         ];
            
    //         // Get users for assignment if needed
    //         $users = $this->getUsersForAssignment();
            
    //         return Inertia::render('admin/vouchers/index', [
    //             'vouchers' => [
    //                 'data' => $transformedVouchers,
    //                 'total' => $vouchers->total(),
    //                 'per_page' => $vouchers->perPage(),
    //                 'current_page' => $vouchers->currentPage(),
    //                 'from' => $vouchers->firstItem(),
    //                 'to' => $vouchers->lastItem(),
    //             ],
    //             'stats' => $stats,
    //             'users' => $users,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Voucher Index Error: ' . $e->getMessage());
    //         return Inertia::render('admin/vouchers/index', [
    //             'vouchers' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'per_page' => 15,
    //                 'current_page' => 1,
    //                 'from' => 0,
    //                 'to' => 0,
    //             ],
    //             'stats' => [
    //                 'total_vouchers' => 0,
    //                 'pending_count' => 0,
    //                 'approved_count' => 0,
    //                 'rejected_count' => 0,
    //                 'forwarded_count' => 0,
    //                 'draft_count' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'pending_ec_count' => 0,
    //                 'pending_audit_count' => 0,
    //                 'total_amount' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //                 'liability_count' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }
    
    /**
     * Search for vouchers (API endpoint for AJAX calls)
     * This matches the route /vouchers/search
     */
    // public function search(Request $request)
    // {
    //     try {
    //         $perPage = (int) $request->input('per_page', 15);
    //         $page = (int) $request->input('page', 1);
    //         $search = $request->input('search', '');
    //         $voucherType = $request->input('voucher_type', '');
    //         $status = $request->input('status', '');
    //         $paymentStatus = $request->input('payment_status', '');
    //         $dateFrom = $request->input('date_from', '');
    //         $dateTo = $request->input('date_to', '');
    //         $tab = $request->input('tab', 'all');
            
    //         // Check if the assigned_to_user_id column exists
    //         $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
    //         // Build query with conditional eager loading
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals', 'documents']);
            
    //         // Only load assignedTo relationship if the column exists and relationship is defined
    //         if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
    //             $query->with('assignedTo');
    //         }
            
    //         // Base query - exclude soft deleted records
    //         $query->orderBy('created_at', 'desc');
            
    //         // Apply tab filter
    //         if ($tab === 'pending') {
    //             $query->where('status', 'submitted');
    //         } elseif ($tab === 'approved') {
    //             $query->whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved']);
    //         } elseif ($tab === 'rejected') {
    //             $query->whereIn('status', ['rejected', 'sent_back']);
    //         } elseif ($tab === 'forwarded') {
    //             $query->where('status', 'forwarded');
    //         } elseif ($tab === 'liability') {
    //             $query->whereDate('final_approved_at', today());
    //         } elseif ($tab === 'draft') {
    //             $query->whereIn('status', ['draft', 'saved']);
    //         }
            
    //         // Apply search filter
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
            
    //         // Apply voucher type filter
    //         if ($voucherType) {
    //             $query->where('voucher_type', $voucherType);
    //         }
            
    //         // Apply status filter
    //         if ($status) {
    //             $query->where('status', $status);
    //         }
            
    //         // Apply payment status filter
    //         if ($paymentStatus) {
    //             if ($paymentStatus === 'paid') {
    //                 $query->where('status', 'closed')->whereNotNull('mas_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_mas') {
    //                 $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_ag') {
    //                 $query->where('status', 'ec_approved')->whereNull('ag_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_ec') {
    //                 $query->where('status', 'fa_approved')->whereNull('ec_approved_at');
    //             } elseif ($paymentStatus === 'awaiting_audit') {
    //                 $query->where('status', 'submitted')->whereNull('final_approved_at');
    //             }
    //         }
            
    //         // Apply date range filter
    //         if ($dateFrom) {
    //             $query->whereDate('voucher_date', '>=', $dateFrom);
    //         }
    //         if ($dateTo) {
    //             $query->whereDate('voucher_date', '<=', $dateTo);
    //         }
            
    //         $vouchers = $query->paginate($perPage, ['*'], 'page', $page);
            
    //         // Transform the data for the frontend
    //         $transformedVouchers = $vouchers->map(function ($voucher) use ($hasAssignment) {
    //             // Determine payment status
    //             $paymentStatus = 'unknown';
    //             if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
    //                 $paymentStatus = 'paid';
    //             } elseif ($voucher->status === 'ag_approved') {
    //                 $paymentStatus = 'awaiting_mas';
    //             } elseif ($voucher->status === 'ec_approved') {
    //                 $paymentStatus = 'awaiting_ag';
    //             } elseif ($voucher->status === 'fa_approved') {
    //                 $paymentStatus = 'awaiting_ec';
    //             } elseif ($voucher->status === 'submitted') {
    //                 $paymentStatus = 'awaiting_audit';
    //             }
                
    //             $data = [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
    //                 'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => (float) $voucher->total_amount,
    //                 'payee_name' => $voucher->payee_name,
    //                 'status' => $voucher->status,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'payment_status' => $paymentStatus,
    //                 'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'code' => $voucher->mda->code,
    //                 ] : null,
    //                 'bank_activity' => $voucher->bankActivity ? [
    //                     'id' => $voucher->bankActivity->id,
    //                     'bank_name' => $voucher->bankActivity->bank_name,
    //                     'account_number' => $voucher->bankActivity->account_number,
    //                     'tag' => $voucher->bankActivity->tag,
    //                     'title' => $voucher->bankActivity->title,
    //                 ] : null,
    //                 'items' => $voucher->items->map(function ($item) {
    //                     return [
    //                         'id' => $item->id,
    //                         'description' => $item->description,
    //                         'quantity' => (float) $item->quantity,
    //                         'unit_price' => (float) $item->unit_price,
    //                         'sub_total' => (float) $item->sub_total,
    //                     ];
    //                 }),
    //                 'approvals' => $voucher->approvals->map(function ($approval) {
    //                     return [
    //                         'id' => $approval->id,
    //                         'action' => $approval->action,
    //                         'comment' => $approval->comment,
    //                         'action_at' => $approval->action_at?->toDateTimeString(),
    //                         'created_at' => $approval->created_at?->toDateTimeString(),
    //                         'approval_role' => $approval->approval_role,
    //                         'status' => $approval->status,
    //                         'user' => $approval->user ? [
    //                             'id' => $approval->user->id,
    //                             'name' => $approval->user->name,
    //                         ] : null,
    //                     ];
    //                 }),
    //                 'documents' => $voucher->documents->map(function ($document) {
    //                     return [
    //                         'id' => $document->id,
    //                         'file_name' => $document->file_name,
    //                         'file_path' => $document->file_path,
    //                         'mime_type' => $document->mime_type,
    //                         'size' => $document->size,
    //                     ];
    //                 }),
    //             ];
                
    //             // Only add assigned_to if the column exists and relationship is loaded
    //             if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
    //                 $data['assigned_to'] = [
    //                     'id' => $voucher->assignedTo->id,
    //                     'name' => $voucher->assignedTo->name,
    //                     'email' => $voucher->assignedTo->email ?? null,
    //                 ];
    //             } else {
    //                 $data['assigned_to'] = null;
    //             }
                
    //             return $data;
    //         })->values()->toArray();
            
    //         // Get statistics - Same pattern as Expenditure Control
    //         $stats = [
    //             'total_vouchers' => Voucher::count(),
    //             'pending_count' => Voucher::where('status', 'submitted')->count(),
    //             'approved_count' => Voucher::whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved'])->count(),
    //             'rejected_count' => Voucher::whereIn('status', ['rejected', 'sent_back'])->count(),
    //             'forwarded_count' => Voucher::where('status', 'forwarded')->count(),
    //             'draft_count' => Voucher::whereIn('status', ['draft', 'saved'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'pending_ec_count' => Voucher::where('status', 'fa_approved')->whereNull('ec_approved_at')->count(),
    //             'pending_audit_count' => Voucher::where('status', 'submitted')->whereNull('final_approved_at')->count(),
    //             'total_amount' => (float) Voucher::sum('total_amount'),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['submitted', 'fa_approved', 'ec_approved', 'ag_approved'])->sum('total_amount'),
    //             'liability_count' => Voucher::whereDate('final_approved_at', today())->count(),
    //         ];
            
    //         // Get users for assignment
    //         $users = $this->getUsersForAssignment();
            
    //         return response()->json([
    //             'status' => 'success',
    //             'vouchers' => [
    //                 'data' => $transformedVouchers,
    //                 'total' => $vouchers->total(),
    //                 'per_page' => $vouchers->perPage(),
    //                 'current_page' => $vouchers->currentPage(),
    //                 'last_page' => $vouchers->lastPage(),
    //                 'from' => $vouchers->firstItem(),
    //                 'to' => $vouchers->lastItem(),
    //             ],
    //             'stats' => $stats,
    //             'users' => $users,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Voucher Search Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => $e->getMessage(),
    //             'vouchers' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'per_page' => 15,
    //                 'current_page' => 1,
    //                 'last_page' => 1,
    //                 'from' => 0,
    //                 'to' => 0,
    //             ],
    //             'stats' => [
    //                 'total_vouchers' => 0,
    //                 'pending_count' => 0,
    //                 'approved_count' => 0,
    //                 'rejected_count' => 0,
    //                 'forwarded_count' => 0,
    //                 'draft_count' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'pending_ec_count' => 0,
    //                 'pending_audit_count' => 0,
    //                 'total_amount' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //                 'liability_count' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

    /**
     * Get data for the index page (same as search but for Inertia)
     */
    public function getData(Request $request)
    {
        return $this->search($request);
    }

    /**
     * Get statistics for the dashboard
     */
    public function stats(Request $request)
    {
        try {
            $stats = [
                'total_vouchers' => Voucher::count(),
                'pending_count' => Voucher::where('status', 'submitted')->count(),
                'approved_count' => Voucher::whereIn('status', ['fa_approved', 'ec_approved', 'ag_approved', 'mas_approved'])->count(),
                'rejected_count' => Voucher::whereIn('status', ['rejected', 'sent_back'])->count(),
                'forwarded_count' => Voucher::where('status', 'forwarded')->count(),
                'draft_count' => Voucher::whereIn('status', ['draft', 'saved'])->count(),
                'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'pending_ec_count' => Voucher::where('status', 'fa_approved')->whereNull('ec_approved_at')->count(),
                'pending_audit_count' => Voucher::where('status', 'submitted')->whereNull('final_approved_at')->count(),
                'total_amount' => (float) Voucher::sum('total_amount'),
                'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) Voucher::whereIn('status', ['submitted', 'fa_approved', 'ec_approved', 'ag_approved'])->sum('total_amount'),
                'liability_count' => Voucher::whereDate('final_approved_at', today())->count(),
            ];

            return response()->json([
                'status' => 'success',
                'stats' => $stats,
            ]);
        } catch (\Exception $e) {
            Log::error('Voucher Stats Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get users for assignment (using Spatie roles)
     */
    // private function getUsersForAssignment()
    // {
    //     try {
    //         // Get users with specific roles that can be assigned
    //         $users = \App\Models\User::role(['admin', 'Expenditure Controller', 'Internal Auditor', 'Director of Finance'])
    //             ->select('id', 'name', 'email')
    //             ->get()
    //             ->map(function ($user) {
    //                 return [
    //                     'id' => $user->id,
    //                     'name' => $user->name,
    //                     'email' => $user->email,
    //                 ];
    //             })
    //             ->toArray();
            
    //         return $users;
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching users for assignment: ' . $e->getMessage());
    //         return [];
    //     }
    // }

    /**
     * Show the form for creating a new resource.
     */
    // public function create(Request $request)
    // {
    //     // Get DFA permissions
    //     $dfaPermissions = $this->getDFAPermissions();
        
    //     // If user is subordinate and tries to access create, still allow but restrict buttons
    //     if ($this->isDFASubordinate()) {
    //         // Allow access but will show only draft button
    //         Log::info('DFA Subordinate accessing create form', ['user_id' => Auth::id()]);
    //     }

    //     $schedule = null;

    //     if ($request->has('schedule_id')) {
    //         $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
    //             ->find($request->schedule_id);
    //     }

    //     $scheduleId = $request->get('schedule_id');
    //     $itemId = $request->get('item_id');
    //     $voucherType = $request->get('type', 'standard');
        
    //     $schedule = null;
    //     $lineItem = null;
    //     $voucherData = [];
        
    //     if ($scheduleId) {
    //         $schedule = Schedule::with(['items', 'mda', 'financialYear'])->find($scheduleId);
            
    //         if ($itemId) {
    //             // ✅ Single line item - create voucher for this specific item
    //             $lineItem = $schedule->items->find($itemId);
    //             if ($lineItem) {
    //                 $voucherData = [
    //                     'payee_name' => $lineItem->payee_name,
    //                     'amount' => $lineItem->amount,
    //                     'description' => $lineItem->description ?? "Payment to {$lineItem->payee_name}",
    //                     'economy_code_id' => $lineItem->economy_code_id,
    //                     'economy_code_item_id' => $lineItem->economy_code_item_id,
    //                     'item_id' => $lineItem->id,
    //                 ];
    //             }
    //         } else {
    //             // ✅ Multiple items - full schedule voucher
    //             $voucherData = [
    //                 'payee_name' => $schedule->items->first()?->payee_name ?? '',
    //                 'amount' => $schedule->total_amount,
    //                 'description' => $schedule->narration ?? 'Payment from schedule',
    //                 'items' => $schedule->items->toArray(),
    //             ];
    //         }
    //     }

    //     $economyCodes = EconomyCode::select('id', 'code', 'name')
    //         ->where('status', 'active')
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($code) {
    //             return [
    //                 'value' => $code->id,
    //                 'label' => $code->code . ' - ' . $code->name,
    //                 'code' => $code->code,
    //                 'name' => $code->name,
    //                 'type' => $code->type ?? 'operational',
    //             ];
    //         })->toArray();

    //     $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
    //         ->select('id', 'economy_code_id', 'code', 'name')
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'value' => $item->id,
    //                 'label' => $item->code . ' - ' . $item->name,
    //                 'economy_code_id' => $item->economy_code_id,
    //             ];
    //         })->toArray();

    //     $financialYearId = $schedule?->year_id ?? FinancialYear::where('is_active', true)->first()?->id;
        
    //     $programmeCodes = ProgrammeCode::with('economicCode')
    //         ->active()
    //         ->projects()
    //         ->when($financialYearId, function ($query) use ($financialYearId) {
    //             $query->where('financial_year_id', $financialYearId);
    //         })
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($programme) {
    //             return [
    //                 'id' => $programme->id,
    //                 'code' => $programme->code,
    //                 'name' => $programme->name,
    //                 'description' => $programme->project_description ?: $programme->name,
    //                 'budget_code' => $programme->budget_code,
    //                 'remaining_budget' => (float) $programme->remaining_budget,
    //                 'approved_budget' => (float) $programme->approved_budget,
    //                 'economic_code_id' => $programme->economic_code_id,
    //                 'economic_code' => $programme->economicCode ? [
    //                     'id' => $programme->economicCode->id,
    //                     'code' => $programme->economicCode->code,
    //                     'name' => $programme->economicCode->name,
    //                 ] : null,
    //                 'mda_name' => $programme->mda_name,
    //                 'sector' => $programme->sector,
    //                 'label' => "{$programme->code} - {$programme->name} (₦" . number_format($programme->remaining_budget, 2) . ")",
    //                 'value' => $programme->id,
    //             ];
    //         });

    //     $this->activityLogger->log(
    //         "Accessed voucher creation form",
    //         [
    //             'schedule_id' => $request->get('schedule_id'),
    //             'voucher_type' => $request->get('type', 'standard'),
    //             'user_id' => Auth::id(),
    //             'is_subordinate' => $this->isDFASubordinate(),
    //         ],
    //         'voucher'
    //     );

    //     return Inertia::render('admin/vouchers/create', [
    //         'voucherType' => $request->get('type', 'standard'),
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'year_id' => $schedule->year_id,
    //             'mda_id' => $schedule->mda_id,
    //             'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
    //             'budget_code' => $schedule->budgetCode?->code,
    //             'total_amount' => $schedule->total_amount,
    //             'amount_posted' => $schedule->vouchers->sum('total_amount'),
    //             'voucher_count' => $schedule->vouchers->count(),
    //             'narration' => $schedule->narration,
    //             'payee_name' => $schedule->payee_name,
    //         ] : null,
    //         'mdas' => Mda::all()->map(fn($mda) => [
    //             'value' => $mda->id,
    //             'label' => $mda->name
    //         ]),
    //         'financialYears' => FinancialYear::all()->map(fn($year) => [
    //             'value' => $year->id,
    //             'label' => $year->name
    //         ]),
    //         'economyCodes' => $economyCodes,
    //         'economyCodeItems' => $economyCodeItems,
    //         'programmeCodes' => $programmeCodes,
    //         'today' => now()->format('Y-m-d'),
    //         'voucherNumber' => $this->generateVoucherNumber(),
    //         'dfaPermissions' => $dfaPermissions, // Pass to view
    //     ]);
    // }

    /**
     * Generate a unique voucher number
     */
    private function generateVoucherNumber()
    {
        $year = date('y');
        $month = date('m');
        
        $lastVoucher = Voucher::whereYear('created_at', date('Y'))
            ->orderBy('id', 'desc')
            ->first();
        
        if ($lastVoucher && preg_match('/VCH-' . $year . $month . '-(\d+)/', $lastVoucher->voucher_number, $matches)) {
            $sequence = (int) $matches[1] + 1;
        } else {
            $sequence = 1;
        }
        
        return 'VCH-' . $year . $month . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Store a newly created resource in storage.
     */
    // public function store(VoucherStoreUpdateRequest $request): RedirectResponse
    // {
    //     $data = $request->validated();
    //     $files = $request->file('documents') ?? [];
    //     $documentTypes = $request->input('document_types', []);

    //     // Check if user is subordinate
    //     $isSubordinate = $this->isDFASubordinate();
        
    //     // If subordinate, force status to Draft
    //     if ($isSubordinate && isset($data['status']) && $data['status'] === 'Submitted') {
    //         $data['status'] = 'Draft';
    //         Log::info('DFA Subordinate forced status to Draft', [
    //             'user_id' => Auth::id(),
    //             'original_status' => $request->input('status'),
    //         ]);
    //     }

    //     // If user is DFA main, allow submission
    //     if ($this->isDFAMain() && isset($data['status'])) {
    //         // Allow submission
    //         Log::info('DFA Main creating voucher with status: ' . $data['status'], [
    //             'user_id' => Auth::id(),
    //         ]);
    //     }

    //     Log::info('Voucher Store Request Data:', [
    //         'data_keys' => array_keys($data),
    //         'files_count' => count($files),
    //         'document_types_count' => count($documentTypes),
    //         'user_id' => Auth::id(),
    //         'is_subordinate' => $isSubordinate,
    //         'status' => $data['status'] ?? 'Draft',
    //     ]);

    //     try {
    //         $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);
            
    //         // If submitted for approval (only DFA main can do this)
    //         if ($data['status'] === 'Submitted' && $this->isDFAMain()) {
    //             $budgetValidation = $this->budgetService->validateVoucherBudget($voucher);
                
    //             if (!$budgetValidation['is_valid']) {
    //                 // Budget validation failed - delete the voucher
    //                 $voucher->delete();
    //                 $errorMessage = implode('; ', $budgetValidation['errors']);
    //                 return back()
    //                     ->withInput()
    //                     ->with('error', 'Budget validation failed: ' . $errorMessage);
    //             }
                
    //             // Log the submission
    //             $this->activityLogger->log(
    //                 "DFA Main submitted voucher for approval",
    //                 [
    //                     'voucher_id' => $voucher->id,
    //                     'voucher_number' => $voucher->voucher_number,
    //                     'user_id' => Auth::id(),
    //                 ],
    //                 'voucher'
    //             );
    //         }

    //         $this->activityLogger->log(
    //             "Created voucher {$voucher->voucher_number}",
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'total_amount' => $voucher->total_amount,
    //                 'mda_id' => $voucher->mda_id,
    //                 'status' => $voucher->status,
    //                 'document_count' => $voucher->documents->count(),
    //                 'schedule_id' => $voucher->schedule_id,
    //                 'user_id' => Auth::id(),
    //                 'is_subordinate' => $isSubordinate,
    //             ],
    //             'voucher'
    //         );

    //         $this->activityLogger->logAction('created', $voucher, [
    //             'amount' => $voucher->total_amount,
    //             'payee_name' => $voucher->payee_name,
    //             'narration' => $voucher->narration
    //         ]);

    //         $successMessage = $voucher->status === 'Submitted' 
    //             ? 'Voucher ' . $voucher->voucher_number . ' created and submitted for approval successfully.'
    //             : 'Voucher ' . $voucher->voucher_number . ' saved as draft successfully.';

    //         return redirect()
    //             ->route('vouchers.index')
    //             ->with('success', $successMessage);
                
    //     } catch (\Exception $e) {
    //         \Log::error('Voucher Creation Failed: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString(),
    //             'data' => $request->except(['documents', 'document_types']),
    //             'user_id' => Auth::id(),
    //         ]);

    //         $this->activityLogger->log(
    //             "Failed to create voucher",
    //             [
    //                 'error' => $e->getMessage(),
    //                 'data_keys' => array_keys($data),
    //                 'attempted_by' => Auth::id()
    //             ],
    //             'voucher'
    //         );

    //         return back()
    //             ->withInput()
    //             ->with('error', 'Failed to create voucher: ' . $e->getMessage());
    //     }
    // }

    /**
     * Show the form for creating a new resource.
     */
    // public function create(Request $request)
    // {
    //     // Get DFA permissions
    //     $dfaPermissions = $this->getDFAPermissions();
        
    //     // If user is subordinate and tries to access create, still allow but restrict buttons
    //     if ($this->isDFASubordinate()) {
    //         Log::info('DFA Subordinate accessing create form', ['user_id' => Auth::id()]);
    //     }

    //     $schedule = null;
    //     $lineItem = null;
    //     $voucherData = [];

    //     // ✅ Get schedule_id from request
    //     if ($request->has('schedule_id')) {
    //         $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
    //             ->find($request->schedule_id);
            
    //         // ✅ Get specific line item if provided
    //         if ($request->has('item_id')) {
    //             $lineItem = ScheduleItem::with(['economyCode', 'economyCodeItem'])
    //                 ->find($request->item_id);
                
    //             if ($lineItem && $schedule) {
    //                 // ✅ Pre-fill voucher data from line item
    //                 $voucherData = [
    //                     'payee_name' => $lineItem->payee_name,
    //                     'amount' => $lineItem->amount,
    //                     'description' => $lineItem->description ?? "Payment to {$lineItem->payee_name}",
    //                     'economy_code_id' => $lineItem->economy_code_id,
    //                     'economy_code_item_id' => $lineItem->economy_code_item_id,
    //                     'schedule_item_id' => $lineItem->id,
    //                     'schedule_id' => $schedule->id,
    //                 ];
                    
    //                 Log::info('Creating voucher from line item:', [
    //                     'schedule_item_id' => $lineItem->id,
    //                     'payee' => $lineItem->payee_name,
    //                     'amount' => $lineItem->amount,
    //                 ]);
    //             }
    //         } elseif ($schedule) {
    //             // ✅ Full schedule - check if it has multiple items
    //             $itemsCount = $schedule->items->count();
                
    //             if ($itemsCount === 1) {
    //                 // ✅ One-to-one: single voucher for the whole schedule
    //                 $firstItem = $schedule->items->first();
    //                 $voucherData = [
    //                     'payee_name' => $firstItem->payee_name,
    //                     'amount' => $schedule->total_amount,
    //                     'description' => $schedule->narration ?? "Payment from schedule {$schedule->schedule_number}",
    //                     'economy_code_id' => $firstItem->economy_code_id,
    //                     'economy_code_item_id' => $firstItem->economy_code_item_id,
    //                     'schedule_id' => $schedule->id,
    //                 ];
    //             } else {
    //                 // ✅ One-to-many: multiple vouchers, one per line item
    //                 // Store schedule but let user choose which item
    //                 $voucherData = [
    //                     'schedule_id' => $schedule->id,
    //                     'has_multiple_items' => true,
    //                     'items_count' => $itemsCount,
    //                 ];
    //             }
    //         }
    //     }

    //     $economyCodes = EconomyCode::select('id', 'code', 'name')
    //         ->where('status', 'active')
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($code) {
    //             return [
    //                 'value' => $code->id,
    //                 'label' => $code->code . ' - ' . $code->name,
    //                 'code' => $code->code,
    //                 'name' => $code->name,
    //                 'type' => $code->type ?? 'operational',
    //             ];
    //         })->toArray();

    //     $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
    //         ->select('id', 'economy_code_id', 'code', 'name')
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($item) {
    //             return [
    //                 'value' => $item->id,
    //                 'label' => $item->code . ' - ' . $item->name,
    //                 'economy_code_id' => $item->economy_code_id,
    //             ];
    //         })->toArray();

    //     $financialYearId = $schedule?->year_id ?? FinancialYear::where('is_active', true)->first()?->id;
        
    //     $programmeCodes = ProgrammeCode::with('economicCode')
    //         ->active()
    //         ->projects()
    //         ->when($financialYearId, function ($query) use ($financialYearId) {
    //             $query->where('financial_year_id', $financialYearId);
    //         })
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($programme) {
    //             return [
    //                 'id' => $programme->id,
    //                 'code' => $programme->code,
    //                 'name' => $programme->name,
    //                 'description' => $programme->project_description ?: $programme->name,
    //                 'budget_code' => $programme->budget_code,
    //                 'remaining_budget' => (float) $programme->remaining_budget,
    //                 'approved_budget' => (float) $programme->approved_budget,
    //                 'economic_code_id' => $programme->economic_code_id,
    //                 'economic_code' => $programme->economicCode ? [
    //                     'id' => $programme->economicCode->id,
    //                     'code' => $programme->economicCode->code,
    //                     'name' => $programme->economicCode->name,
    //                 ] : null,
    //                 'mda_name' => $programme->mda_name,
    //                 'sector' => $programme->sector,
    //                 'label' => "{$programme->code} - {$programme->name} (₦" . number_format($programme->remaining_budget, 2) . ")",
    //                 'value' => $programme->id,
    //             ];
    //         });

    //     $this->activityLogger->log(
    //         "Accessed voucher creation form",
    //         [
    //             'schedule_id' => $request->get('schedule_id'),
    //             'item_id' => $request->get('item_id'),
    //             'voucher_type' => $request->get('type', 'standard'),
    //             'user_id' => Auth::id(),
    //             'is_subordinate' => $this->isDFASubordinate(),
    //         ],
    //         'voucher'
    //     );

    //     return Inertia::render('admin/vouchers/create', [
    //         'voucherType' => $request->get('type', 'standard'),
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'year_id' => $schedule->year_id,
    //             'mda_id' => $schedule->mda_id,
    //             'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
    //             'budget_code' => $schedule->budgetCode?->code,
    //             'total_amount' => $schedule->total_amount,
    //             'amount_posted' => $schedule->vouchers->sum('total_amount'),
    //             'voucher_count' => $schedule->vouchers->count(),
    //             'narration' => $schedule->narration,
    //             'payee_name' => $schedule->payee_name,
    //             'items' => $schedule->items->map(function($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'payee_name' => $item->payee_name,
    //                     'amount' => $item->amount,
    //                     'has_voucher' => !is_null($item->voucher_id),
    //                     'voucher_id' => $item->voucher_id,
    //                 ];
    //             }),
    //         ] : null,
    //         'lineItem' => $lineItem ? [
    //             'id' => $lineItem->id,
    //             'payee_name' => $lineItem->payee_name,
    //             'amount' => $lineItem->amount,
    //             'description' => $lineItem->description,
    //             'economy_code_id' => $lineItem->economy_code_id,
    //             'economy_code_item_id' => $lineItem->economy_code_item_id,
    //             'serial_number' => $lineItem->serial_number,
    //         ] : null,
    //         'voucherData' => $voucherData,
    //         'mdas' => Mda::all()->map(fn($mda) => [
    //             'value' => $mda->id,
    //             'label' => $mda->name
    //         ]),
    //         'financialYears' => FinancialYear::all()->map(fn($year) => [
    //             'value' => $year->id,
    //             'label' => $year->name
    //         ]),
    //         'economyCodes' => $economyCodes,
    //         'economyCodeItems' => $economyCodeItems,
    //         'programmeCodes' => $programmeCodes,
    //         'today' => now()->format('Y-m-d'),
    //         'voucherNumber' => $this->generateVoucherNumber(),
    //         'dfaPermissions' => $dfaPermissions,
    //     ]);
    // }
    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Get DFA permissions
        $dfaPermissions = $this->getDFAPermissions();
        
        if ($this->isDFASubordinate()) {
            Log::info('DFA Subordinate accessing create form', ['user_id' => Auth::id()]);
        }

        $schedule = null;
        $lineItem = null;
        $voucherData = [];
        $totalAmount = 0;
        $payeeName = '';
        $voucherItems = [];
        $isLineItemVoucher = false;

        // ✅ Get schedule_id from request
        if ($request->has('schedule_id')) {
            $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
                ->find($request->schedule_id);
            
            // ✅ Get specific line item if provided
            if ($request->has('item_id')) {
                $lineItem = ScheduleItem::with(['economyCode', 'economyCodeItem'])
                    ->find($request->item_id);
                
                if ($lineItem && $schedule) {
                    $isLineItemVoucher = true;
                    
                    // ✅ Pre-fill voucher data from line item
                    // The total amount should be the line item amount, not the schedule total
                    $totalAmount = $lineItem->amount;
                    $payeeName = $lineItem->payee_name;
                    
                    // Create a single line item from the schedule item
                    $voucherItems = [
                        [
                            'description' => $lineItem->description ?? "Payment to {$lineItem->payee_name}",
                            'economy_code_id' => $lineItem->economy_code_id,
                            'economy_code_item_id' => $lineItem->economy_code_item_id,
                            'quantity' => 1,
                            'unit_price' => $lineItem->amount,
                            'sub_total' => $lineItem->amount,
                        ]
                    ];
                    
                    $voucherData = [
                        'payee_name' => $lineItem->payee_name,
                        'total_amount' => $lineItem->amount,
                        'description' => $lineItem->description ?? "Payment to {$lineItem->payee_name}",
                        'economy_code_id' => $lineItem->economy_code_id,
                        'economy_code_item_id' => $lineItem->economy_code_item_id,
                        'schedule_item_id' => $lineItem->id,
                        'schedule_id' => $schedule->id,
                        'items' => $voucherItems,
                        'is_line_item_voucher' => true,
                    ];
                    
                    Log::info('Creating voucher from line item:', [
                        'schedule_item_id' => $lineItem->id,
                        'payee' => $lineItem->payee_name,
                        'amount' => $lineItem->amount,
                    ]);
                }
            } elseif ($schedule) {
                // ✅ Full schedule - check if it has multiple items
                $itemsCount = $schedule->items->count();
                $firstItem = $schedule->items->first();
                
                if ($itemsCount === 1) {
                    // ✅ One-to-one: single voucher for the whole schedule
                    $totalAmount = $schedule->total_amount;
                    $payeeName = $firstItem->payee_name;
                    
                    $voucherItems = $schedule->items->map(function($item) {
                        return [
                            'description' => $item->description ?? "Payment to {$item->payee_name}",
                            'economy_code_id' => $item->economy_code_id,
                            'economy_code_item_id' => $item->economy_code_item_id,
                            'quantity' => 1,
                            'unit_price' => $item->amount,
                            'sub_total' => $item->amount,
                        ];
                    })->toArray();
                    
                    $voucherData = [
                        'payee_name' => $firstItem->payee_name,
                        'total_amount' => $schedule->total_amount,
                        'description' => $schedule->narration ?? "Payment from schedule {$schedule->schedule_number}",
                        'economy_code_id' => $firstItem->economy_code_id,
                        'economy_code_item_id' => $firstItem->economy_code_item_id,
                        'schedule_id' => $schedule->id,
                        'items' => $voucherItems,
                        'is_line_item_voucher' => false,
                    ];
                } else {
                    // ✅ One-to-many: multiple vouchers, one per line item
                    // Store schedule but let user choose which item
                    $voucherData = [
                        'schedule_id' => $schedule->id,
                        'has_multiple_items' => true,
                        'items_count' => $itemsCount,
                        'is_line_item_voucher' => false,
                    ];
                }
            }
        }

        $economyCodes = EconomyCode::select('id', 'code', 'name')
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(function ($code) {
                return [
                    'value' => $code->id,
                    'label' => $code->code . ' - ' . $code->name,
                    'code' => $code->code,
                    'name' => $code->name,
                    'type' => $code->type ?? 'operational',
                ];
            })->toArray();

        $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
            ->select('id', 'economy_code_id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                    'economy_code_id' => $item->economy_code_id,
                ];
            })->toArray();

        $financialYearId = $schedule?->year_id ?? FinancialYear::where('is_active', true)->first()?->id;
        
        $programmeCodes = ProgrammeCode::with('economicCode')
            ->active()
            ->projects()
            ->when($financialYearId, function ($query) use ($financialYearId) {
                $query->where('financial_year_id', $financialYearId);
            })
            ->orderBy('code')
            ->get()
            ->map(function ($programme) {
                return [
                    'id' => $programme->id,
                    'code' => $programme->code,
                    'name' => $programme->name,
                    'description' => $programme->project_description ?: $programme->name,
                    'budget_code' => $programme->budget_code,
                    'remaining_budget' => (float) $programme->remaining_budget,
                    'approved_budget' => (float) $programme->approved_budget,
                    'economic_code_id' => $programme->economic_code_id,
                    'economic_code' => $programme->economicCode ? [
                        'id' => $programme->economicCode->id,
                        'code' => $programme->economicCode->code,
                        'name' => $programme->economicCode->name,
                    ] : null,
                    'mda_name' => $programme->mda_name,
                    'sector' => $programme->sector,
                    'label' => "{$programme->code} - {$programme->name} (₦" . number_format($programme->remaining_budget, 2) . ")",
                    'value' => $programme->id,
                ];
            });

        $this->activityLogger->log(
            "Accessed voucher creation form",
            [
                'schedule_id' => $request->get('schedule_id'),
                'item_id' => $request->get('item_id'),
                'voucher_type' => $request->get('type', 'standard'),
                'user_id' => Auth::id(),
                'is_subordinate' => $this->isDFASubordinate(),
            ],
            'voucher'
        );

        return Inertia::render('admin/vouchers/create', [
            'voucherType' => $request->get('type', 'standard'),
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'year_id' => $schedule->year_id,
                'mda_id' => $schedule->mda_id,
                'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
                'budget_code' => $schedule->budgetCode?->code,
                'total_amount' => $schedule->total_amount,
                'amount_posted' => $schedule->vouchers->sum('total_amount'),
                'voucher_count' => $schedule->vouchers->count(),
                'narration' => $schedule->narration,
                'payee_name' => $schedule->payee_name,
                'items' => $schedule->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'payee_name' => $item->payee_name,
                        'amount' => $item->amount,
                        'has_voucher' => !is_null($item->voucher_id),
                        'voucher_id' => $item->voucher_id,
                    ];
                }),
            ] : null,
            'lineItem' => $lineItem ? [
                'id' => $lineItem->id,
                'payee_name' => $lineItem->payee_name,
                'amount' => $lineItem->amount,
                'description' => $lineItem->description,
                'economy_code_id' => $lineItem->economy_code_id,
                'economy_code_item_id' => $lineItem->economy_code_item_id,
                'serial_number' => $lineItem->serial_number,
            ] : null,
            'voucherData' => $voucherData,
            'isLineItemVoucher' => $isLineItemVoucher,
            'mdas' => Mda::all()->map(fn($mda) => [
                'value' => $mda->id,
                'label' => $mda->name
            ]),
            'financialYears' => FinancialYear::all()->map(fn($year) => [
                'value' => $year->id,
                'label' => $year->name
            ]),
            'economyCodes' => $economyCodes,
            'economyCodeItems' => $economyCodeItems,
            'programmeCodes' => $programmeCodes,
            'today' => now()->format('Y-m-d'),
            'voucherNumber' => $this->generateVoucherNumber(),
            'dfaPermissions' => $dfaPermissions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(VoucherStoreUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('documents') ?? [];
        $documentTypes = $request->input('document_types', []);

        // ✅ Add schedule_item_id if provided
        if ($request->has('schedule_item_id') && !empty($request->schedule_item_id)) {
            $data['schedule_item_id'] = $request->schedule_item_id;
        }

        // Check if user is subordinate
        $isSubordinate = $this->isDFASubordinate();
        
        // If subordinate, force status to Draft
        if ($isSubordinate && isset($data['status']) && $data['status'] === 'Submitted') {
            $data['status'] = 'Draft';
            Log::info('DFA Subordinate forced status to Draft', [
                'user_id' => Auth::id(),
                'original_status' => $request->input('status'),
            ]);
        }

        // If user is DFA main, allow submission
        if ($this->isDFAMain() && isset($data['status'])) {
            Log::info('DFA Main creating voucher with status: ' . $data['status'], [
                'user_id' => Auth::id(),
            ]);
        }

        Log::info('Voucher Store Request Data:', [
            'data_keys' => array_keys($data),
            'schedule_id' => $data['schedule_id'] ?? null,
            'schedule_item_id' => $data['schedule_item_id'] ?? null,
            'files_count' => count($files),
            'document_types_count' => count($documentTypes),
            'user_id' => Auth::id(),
            'is_subordinate' => $isSubordinate,
            'status' => $data['status'] ?? 'Draft',
        ]);

        try {
            $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);
            
            // ✅ If schedule_item_id was provided, update the schedule item
            if (isset($data['schedule_item_id']) && !empty($data['schedule_item_id'])) {
                // This is now handled in the VoucherService
                Log::info('Voucher linked to schedule item:', [
                    'voucher_id' => $voucher->id,
                    'schedule_item_id' => $data['schedule_item_id'],
                ]);
            }
            
            // If submitted for approval (only DFA main can do this)
            if ($data['status'] === 'Submitted' && $this->isDFAMain()) {
                $budgetValidation = $this->budgetService->validateVoucherBudget($voucher);
                
                if (!$budgetValidation['is_valid']) {
                    // Budget validation failed - delete the voucher
                    $voucher->delete();
                    $errorMessage = implode('; ', $budgetValidation['errors']);
                    return back()
                        ->withInput()
                        ->with('error', 'Budget validation failed: ' . $errorMessage);
                }
                
                // Log the submission
                $this->activityLogger->log(
                    "DFA Main submitted voucher for approval",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'schedule_item_id' => $voucher->schedule_item_id,
                        'user_id' => Auth::id(),
                    ],
                    'voucher'
                );
            }

            $this->activityLogger->log(
                "Created voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'total_amount' => $voucher->total_amount,
                    'mda_id' => $voucher->mda_id,
                    'status' => $voucher->status,
                    'schedule_id' => $voucher->schedule_id,
                    'schedule_item_id' => $voucher->schedule_item_id,
                    'document_count' => $voucher->documents->count(),
                    'user_id' => Auth::id(),
                    'is_subordinate' => $isSubordinate,
                ],
                'voucher'
            );

            $this->activityLogger->logAction('created', $voucher, [
                'amount' => $voucher->total_amount,
                'payee_name' => $voucher->payee_name,
                'narration' => $voucher->narration,
                'schedule_item_id' => $voucher->schedule_item_id,
            ]);

            $successMessage = $voucher->status === 'Submitted' 
                ? 'Voucher ' . $voucher->voucher_number . ' created and submitted for approval successfully.'
                : 'Voucher ' . $voucher->voucher_number . ' saved as draft successfully.';

            return redirect()
                ->route('vouchers.index')
                ->with('success', $successMessage);
                
        } catch (\Exception $e) {
            \Log::error('Voucher Creation Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['documents', 'document_types']),
                'user_id' => Auth::id(),
            ]);

            $this->activityLogger->log(
                "Failed to create voucher",
                [
                    'error' => $e->getMessage(),
                    'data_keys' => array_keys($data),
                    'attempted_by' => Auth::id()
                ],
                'voucher'
            );

            return back()
                ->withInput()
                ->with('error', 'Failed to create voucher: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Voucher $voucher)
    {
        $voucher->load([
            'items.economyCode',
            'items.economyCodeItem',
            'items.programmeCode',
            'documents',
            'mda',
            'financialYear',
            'schedule',
            'approvals.user',
            'creator',
            'bankActivity',
        ]);

        $this->activityLogger->log(
            "Viewed voucher {$voucher->voucher_number}",
            [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'status' => $voucher->status,
                'total_amount' => $voucher->total_amount,
                'viewed_by' => auth()->id()
            ],
            'voucher'
        );

        $voucherData = $voucher->toArray();

        $voucherData['mda'] = $voucher->mda ? [
            'id' => $voucher->mda->id,
            'name' => $voucher->mda->name,
            'code' => $voucher->mda->code,
        ] : null;

        $voucherData['financial_year'] = $voucher->financialYear ? $voucher->financialYear->name : null;

        $voucherData['schedule'] = $voucher->schedule ? [
            'id' => $voucher->schedule->id,
            'schedule_number' => $voucher->schedule->schedule_number,
        ] : null;
        
        $voucherData['bankActivity'] = $voucher->bankActivity ? [
            'bank_name' => $voucher->bankActivity->bank_name,
            'tag' => $voucher->bankActivity->tag,
            'title' => $voucher->bankActivity->title,
            'account_number' => $voucher->bankActivity->account_number,
            'economic_code' => $voucher->bankActivity->economic_code,
        ] : null;

        $voucherData['items'] = $voucher->items->map(function ($item) {
            return [
                'id' => $item->id,
                'description' => $item->description,
                'economy_code_id' => $item->economy_code_id,
                'economy_code_item_id' => $item->economy_code_item_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'sub_total' => $item->sub_total,
                'programme_code_id' => $item->programme_code_id,
                'programme_code' => $item->programme_code,
                'programme_name' => $item->programme_name,
                'budget_code' => $item->budget_code,
                'economy_code' => $item->economyCode ? [
                    'id' => $item->economyCode->id,
                    'code' => $item->economyCode->code,
                    'name' => $item->economyCode->name,
                ] : null,
                'economy_code_item' => $item->economyCodeItem ? [
                    'id' => $item->economyCodeItem->id,
                    'code' => $item->economyCodeItem->code,
                    'name' => $item->economyCodeItem->name,
                ] : null,
                'programme' => $item->programmeCode ? [
                    'id' => $item->programmeCode->id,
                    'code' => $item->programmeCode->code,
                    'name' => $item->programmeCode->name,
                    'budget_code' => $item->programmeCode->budget_code,
                ] : null,
            ];
        })->toArray();

        $voucherData['documents'] = $voucher->documents->map(function ($document) {
            return [
                'id' => $document->id,
                'file_name' => $document->file_name,
                'file_path' => $document->file_path,
                'url' => $document->file_path ? Storage::url($document->file_path) : null,
                'file_size' => $document->file_size,
                'document_type_label' => $document->document_type_label ?? 'Document',
                'is_pdf' => str_contains($document->mime_type ?? '', 'pdf'),
                'is_image' => str_starts_with($document->mime_type ?? '', 'image/'),
            ];
        })->toArray();

        $voucherData['approvals'] = $voucher->approvals->map(function ($approval) {
            return [
                'id' => $approval->id,
                'approval_step' => $approval->approval_step,
                'approval_role' => $approval->approval_role,
                'action' => $approval->action,
                'comment' => $approval->comment,
                'action_at' => $approval->action_at,
                'user' => $approval->user ? [
                    'id' => $approval->user->id,
                    'name' => $approval->user->name,
                ] : null,
            ];
        })->toArray();

        $voucherData['creator'] = $voucher->creator ? [
            'id' => $voucher->creator->id,
            'name' => $voucher->creator->name,
        ] : null;

        // dd($voucherData);

        return Inertia::render('admin/vouchers/show', [
            'voucher' => $voucherData,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Voucher $voucher)
    {
        // Check if user has permission to edit
        if (!$this->isDFAMain() && !$this->isDFASubordinate()) {
            abort(403, 'You do not have permission to edit vouchers.');
        }

        // If subordinate, they can only edit draft vouchers
        if ($this->isDFASubordinate() && $voucher->status !== 'Draft') {
            abort(403, 'DFA Subordinates can only edit draft vouchers.');
        }
            
        // Eager load all necessary relationships
        $voucher->load([
            'items.economyCode',
            'items.economyCodeItem',
            'items.programmeCode',
            'documents',
            'mda',
            'financialYear'
        ]);

        // Log edit form access
        $this->activityLogger->log(
            "Accessed edit form for voucher {$voucher->voucher_number}",
            [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'status' => $voucher->status,
                'accessed_by' => auth()->id()
            ],
            'voucher'
        );

        $economyCodes = EconomyCode::select('id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($code) {
                return [
                    'value' => $code->id,
                    'label' => $code->code . ' - ' . $code->name,
                    'code' => $code->code,
                    'name' => $code->name,
                    'type' => $code->type ?? 'operational',
                ];
            })->toArray();

        $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
            ->select('id', 'economy_code_id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                    'economy_code_id' => $item->economy_code_id,
                ];
            })->toArray();

        // Get current financial year
        $financialYearId = $voucher->year_id ?? FinancialYear::where('is_active', true)->first()?->id;
        
        // Fetch Programme Codes for the current financial year
        $programmeCodes = ProgrammeCode::with('economicCode')
            ->active()
            ->projects()
            ->when($financialYearId, function ($query) use ($financialYearId) {
                $query->where('financial_year_id', $financialYearId);
            })
            ->orderBy('code')
            ->get()
            ->map(function ($programme) {
                return [
                    'id' => $programme->id,
                    'code' => $programme->code,
                    'name' => $programme->name,
                    'description' => $programme->project_description ?: $programme->name,
                    'budget_code' => $programme->budget_code,
                    'remaining_budget' => (float) $programme->remaining_budget,
                    'approved_budget' => (float) $programme->approved_budget,
                    'economic_code_id' => $programme->economic_code_id,
                    'economic_code' => $programme->economicCode ? [
                        'id' => $programme->economicCode->id,
                        'code' => $programme->economicCode->code,
                        'name' => $programme->economicCode->name,
                    ] : null,
                    'mda_name' => $programme->mda_name,
                    'sector' => $programme->sector,
                    'label' => "{$programme->code} - {$programme->name} (₦" . number_format($programme->remaining_budget, 2) . ")",
                    'value' => $programme->id,
                ];
            });

        // Format voucher with programme code fields included
        $formattedVoucher = [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'payee_name' => $voucher->payee_name,
            'year_id' => $voucher->year_id,
            'mda_id' => $voucher->mda_id,
            'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
            'narration' => $voucher->narration,
            'status' => $voucher->status,
            'total_amount' => $voucher->total_amount,
            'bank_activity_id' => $voucher->bank_activity_id,
            'items' => $voucher->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'economy_code_id' => $item->economy_code_id,
                    'economy_code_item_id' => $item->economy_code_item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sub_total' => $item->sub_total,
                    'programme_code_id' => $item->programme_code_id,
                    'programme_code' => $item->programme_code,
                    'programme_name' => $item->programme_name,
                    'budget_code' => $item->budget_code,
                ];
            }),
        ];
        
        $schedule = null;
        if ($voucher->schedule_id) {
            $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
                ->find($voucher->schedule_id);
        }

        $dfaPermissions = $this->getDFAPermissions();

        return inertia('admin/vouchers/edit', [
            'voucher' => $formattedVoucher,
            'mdas' => Mda::all()->map(function ($mda) {
                return [
                    'label' => $mda->name,
                    'value' => $mda->id,
                ];
            }),
            'financialYears' => FinancialYear::all()->map(function ($year) {
                return [
                    'label' => $year->name,
                    'value' => $year->id,
                ];
            }),
            'economyCodes' => $economyCodes,
            'economyCodeItems' => $economyCodeItems,
            'programmeCodes' => $programmeCodes,
            'existingDocuments' => $voucher->documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'file_size' => $document->file_size,
                    'mime_type' => $document->mime_type,
                    'document_type' => $document->document_type,
                    'document_label' => $document->document_label,
                ];
            }),
            'today' => now()->format('Y-m-d'),
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'year_id' => $schedule->year_id,
                'mda_id' => $schedule->mda_id,
                'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
                'budget_code' => $schedule->budgetCode?->code,
                'total_amount' => $schedule->total_amount,
                'amount_posted' => $schedule->vouchers->sum('total_amount'),
                'voucher_count' => $schedule->vouchers->count(),
                'narration' => $schedule->narration,
                'payee_name' => $schedule->payee_name,
            ] : null,
            'dfaPermissions' => $dfaPermissions,
        ]);
    }

    /**
     * Approve a voucher (for prepayment retirement)
     */
    public function approve(Voucher $voucher, Request $request)
    {
        // Validate voucher can be approved
        if ($voucher->voucher_type !== 'prepayment') {
            return back()->withErrors(['message' => 'Only prepayment vouchers can be approved for retirement.']);
        }

        if ($voucher->status !== 'Submitted') {
            return back()->withErrors(['message' => 'Voucher is not in submitted status.']);
        }

        // Validate budget availability before approval
        $budgetValidation = $this->budgetService->validateVoucherBudget($voucher);
        
        if (!$budgetValidation['is_valid']) {
            $errorMessage = implode('; ', $budgetValidation['errors']);
            return back()->withErrors(['message' => 'Budget validation failed: ' . $errorMessage]);
        }

        DB::beginTransaction();
        
        try {
            // Update voucher status
            $voucher->update([
                'status' => 'Approved',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
            
            // Deduct budget
            $this->budgetService->deductBudgetForVoucher($voucher);

            DB::commit();

            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => 'Submitted', 
                    'new_status' => 'Approved',
                    'budget_deducted' => true
                ])
                ->log('approved voucher and deducted budget');

            return response()->json([
                'success' => true,
                'message' => 'Voucher approved and budget deducted successfully.',
                'voucher' => $voucher->fresh(),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Approval failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to approve voucher: ' . $e->getMessage()]);
        }
    }

    /**
     * Reject a voucher and release budget
     */
    public function reject(Voucher $voucher, Request $request)
    {
        if ($voucher->status !== 'Submitted') {
            return back()->withErrors(['message' => 'Voucher is not in submitted status.']);
        }

        DB::beginTransaction();
        
        try {
            $oldStatus = $voucher->status;
            
            $voucher->update([
                'status' => 'Rejected',
                'rejection_reason' => $request->input('reason'),
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ]);
            
            // Release budget if it was deducted
            $this->budgetService->releaseBudgetForVoucher($voucher);

            DB::commit();

            activity()
                ->performedOn($voucher)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $oldStatus, 
                    'new_status' => 'Rejected',
                    'budget_released' => true
                ])
                ->log('rejected voucher and released budget');

            return response()->json([
                'success' => true,
                'message' => 'Voucher rejected and budget released successfully.',
                'voucher' => $voucher->fresh(),
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Rejection failed: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Failed to reject voucher: ' . $e->getMessage()]);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VoucherStoreUpdateRequest $request, Voucher $voucher)
    {
        \Log::info('Update Controller - Voucher:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
        ]);

        $data = $request->validated();
        
        // Handle voucher date
        if (isset($data['voucher_date'])) {
            $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
            $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();
        }
        
        // Handle files
        $files = [];
        if ($request->hasFile('documents')) {
            $files = $request->file('documents');
        }
        
        // Handle document types
        $documentTypes = [];
        if ($request->has('document_types')) {
            $inputTypes = $request->input('document_types');
            if (is_array($inputTypes)) {
                $documentTypes = $inputTypes;
            } elseif (is_string($inputTypes) && !empty($inputTypes)) {
                $decoded = json_decode($inputTypes, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $documentTypes = $decoded;
                }
            }
        }

        // Ensure programme code fields are properly mapped from items
        if (isset($data['items']) && is_array($data['items'])) {
            foreach ($data['items'] as &$item) {
                if (isset($item['programme_code_id']) && !isset($item['programme_code'])) {
                    $programmeCode = ProgrammeCode::find($item['programme_code_id']);
                    if ($programmeCode) {
                        $item['programme_code'] = $programmeCode->code;
                        $item['programme_name'] = $programmeCode->name;
                        if (!isset($item['budget_code'])) {
                            $item['budget_code'] = $programmeCode->budget_code;
                        }
                    }
                }
            }
        }

        $originalData = $voucher->toArray();

        \Log::info('Voucher Update Request Data:', [
            'voucher_id' => $voucher->id,
            'data_keys' => array_keys($data),
            'total_amount_in_data' => $data['total_amount'] ?? 'NOT FOUND',
            'files_count' => count($files),
            'document_types_count' => count($documentTypes),
        ]);

        try {
            if (!isset($data['total_amount'])) {
                throw new \Exception('Total amount is required but not provided or calculated.');
            }

            $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);

            // Determine what changed
            $changes = [];
            foreach ($data as $key => $value) {
                if (!is_array($value) && isset($originalData[$key]) && $originalData[$key] != $value && $key !== 'updated_at') {
                    $changes[$key] = [
                        'from' => $originalData[$key],
                        'to' => $value
                    ];
                }
            }

            $this->activityLogger->log(
                "Updated voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'changes' => $changes,
                    'total_amount' => $data['total_amount'],
                    'document_updates' => [
                        'files_added' => count($files),
                        'document_types' => $documentTypes
                    ],
                    'updated_by' => auth()->id(),
                    'old_status' => $originalData['status'] ?? null,
                    'new_status' => $updatedVoucher->status
                ],
                'voucher'
            );

            $this->activityLogger->logAction('updated', $voucher, [
                'changes' => $changes,
                'document_count' => $updatedVoucher->documents->count(),
                'updated_by' => auth()->id()
            ]);

            \Log::info('Voucher Updated Successfully:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'documents_created' => $updatedVoucher->documents->count(),
                'total_amount' => $updatedVoucher->total_amount,
            ]);

            return redirect()
                ->route('vouchers.index')
                ->with('success', 'Voucher ' . $voucher->voucher_number . ' updated successfully.');
                
        } catch (\Exception $e) {
            \Log::error('Voucher Update Failed: ' . $e->getMessage(), [
                'voucher_id' => $voucher->id,
                'data_keys' => array_keys($data),
                'has_total_amount' => isset($data['total_amount']),
                'document_types' => $documentTypes,
                'trace' => $e->getTraceAsString(),
            ]);

            $this->activityLogger->log(
                "Failed to update voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id()
                ],
                'voucher'
            );

            return back()
                ->withInput()
                ->with('error', 'Failed to update voucher: ' . $e->getMessage());
        }
    }

    /**
     * Save voucher as draft
     */
    public function makeDraft(Voucher $voucher, Request $request)
    {
        $old_status = $voucher->status;
        $voucher->update([
            'status' => 'Draft',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        activity()
            ->performedOn($voucher)
            ->causedBy(auth()->user())
            ->withProperties(['old_status' => $old_status, 'new_status' => 'Draft'])
            ->log('saved voucher as draft');

        return response()->json([
            'success' => true,
            'message' => 'Voucher saved as draft successfully.',
            'voucher' => $voucher->fresh(),
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $voucherNumber = $voucher->voucher_number;
            $voucherData = $voucher->toArray();

            $this->activityLogger->log(
                "Attempting to delete voucher {$voucherNumber}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucherNumber,
                    'total_amount' => $voucher->total_amount,
                    'status' => $voucher->status,
                    'deleted_by' => auth()->id()
                ],
                'voucher'
            );

            $this->voucherService->deleteVoucher($voucher);

            $this->activityLogger->log(
                "Deleted voucher {$voucherNumber}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucherNumber,
                    'total_amount' => $voucherData['total_amount'],
                    'status' => $voucherData['status'],
                    'mda_id' => $voucherData['mda_id'],
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now()
                ],
                'voucher'
            );

            $this->activityLogger->logAction('deleted', $voucher, [
                'voucher_number' => $voucherNumber,
                'amount' => $voucherData['total_amount'],
                'deleted_by' => auth()->id()
            ]);

            return redirect()
                ->route('vouchers.index')
                ->with('success', "Voucher {$voucherNumber} deleted successfully.");
                
        } catch (\Exception $e) {
            \Log::error('Voucher Deletion Failed: ' . $e->getMessage());

            $this->activityLogger->log(
                "Failed to delete voucher",
                [
                    'voucher_id' => $id,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id()
                ],
                'voucher'
            );

            return back()
                ->with('error', 'Failed to delete voucher: ' . $e->getMessage());
        }
    }

    /**
     * Print a voucher
     */
    public function print(Voucher $voucher)
    {
        $voucher->load([
            'items.economyCode:id,code,name',
            'items.economyCodeItem:id,code,name',
            'items.programmeCode:id,code,name,budget_code',
            'financialYear:id,name',
            'schedule:id,schedule_number,total_amount,schedule_date,budget_code_id',
            'schedule.budgetCode:id,code,name,type,initials',
        ]);

        $this->activityLogger->log(
            "Printed voucher {$voucher->voucher_number}",
            [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'total_amount' => $voucher->total_amount,
                'printed_by' => auth()->id(),
                'print_time' => now()
            ],
            'voucher'
        );

        $administrativeSectorCode = null;
        $mdaName = '';
        $budgetCode = '';

        if ($voucher->schedule && $voucher->schedule->budgetCode) {
            $administrativeSectorCode = $voucher->schedule->budgetCode;
            $mdaName = $administrativeSectorCode->name;
            $budgetCode = $administrativeSectorCode->code;
        } else if ($voucher->mda) {
            $mdaName = $voucher->mda->name;
            $budgetCode = $voucher->mda->code;
            $administrativeSectorCode = $voucher->mda;
        }

        $economyCodeData = null;
        if ($voucher->items->isNotEmpty()) {
            $firstItem = $voucher->items->first();
            if ($firstItem->economyCode) {
                $economyCodeData = [
                    'code' => $firstItem->economyCode->code,
                    'name' => $firstItem->economyCode->name,
                ];
            }
        }

        $voucherData = [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
            'total_amount' => $voucher->total_amount,
            'mda_name_name' => $voucher->mda->name,
            'mda_name_code' => $voucher->mda->code,
            'narration' => $voucher->narration,
            'status' => $voucher->status,
            'voucher_type' => $voucher->voucher_type,
            'items' => $voucher->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sub_total' => $item->sub_total,
                    'programme_code' => $item->programme_code,
                    'programme_name' => $item->programme_name,
                    'budget_code' => $item->budget_code,
                    'economy_code' => $item->economyCode ? [
                        'id' => $item->economyCode->id,
                        'code' => $item->economyCode->code,
                        'name' => $item->economyCode->name,
                    ] : null,
                    'economy_code_item' => $item->economyCodeItem ? [
                        'id' => $item->economyCodeItem->id,
                        'code' => $item->economyCodeItem->code,
                        'name' => $item->economyCodeItem->name,
                    ] : null,
                    'programme' => $item->programmeCode ? [
                        'id' => $item->programmeCode->id,
                        'code' => $item->programmeCode->code,
                        'name' => $item->programmeCode->name,
                        'budget_code' => $item->programmeCode->budget_code,
                    ] : null,
                ];
            })->toArray(),
        ];

        

        $scheduleData = $voucher->schedule ? [
            'id' => $voucher->schedule->id,
            'schedule_number' => $voucher->schedule->schedule_number,
            'total_amount' => $voucher->schedule->total_amount,
            'schedule_date' => $voucher->schedule->schedule_date?->format('Y-m-d'),
        ] : null;

        $adminSectorCodeData = $administrativeSectorCode ? [
            'id' => $administrativeSectorCode->id,
            'code' => $administrativeSectorCode->code,
            'name' => $administrativeSectorCode->name,
            'type' => $administrativeSectorCode->type,
            'initials' => $administrativeSectorCode->initials,
        ] : null;

        // dd($voucherData);
        // dd($scheduleData);
        // dd($adminSectorCodeData);

        return Inertia::render('admin/vouchers/print2', [
            'voucher' => $voucherData,
            'administrativeSectorCode' => $adminSectorCodeData,
            'schedule' => $scheduleData,
            'economyCode' => $economyCodeData,
        ]);
    }

    /**
     * Get bank activities (API endpoint)
     */
    public function getBankActivities(Request $request)
    {
        $filter = $request->input('filter', '');
        
        $this->activityLogger->log(
            "Searched bank activities",
            [
                'filter' => $filter,
                'searched_by' => auth()->id()
            ],
            'bank'
        );

        $items = BankActivity::when($filter, function ($query, $filter) {
            return $query->where('tag', 'like', "%{$filter}%")
                ->orWhere('bank_name', 'like', "%{$filter}%")
                ->orWhere('account_number', 'like', "%{$filter}%")
                ->orWhere('title', 'like', "%{$filter}%");
        })
        ->paginate(15);

        return response()->json($items);
    }

    /**
     * Show the form for creating a final accounts voucher (direct approval)
     */
    public function createFinal(Request $request)
    {
        $schedule = null;

        if ($request->has('schedule_id')) {
            $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
                ->find($request->schedule_id);
        }

        $economyCodes = EconomyCode::select('id', 'code', 'name')
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(function ($code) {
                return [
                    'value' => $code->id,
                    'label' => $code->code . ' - ' . $code->name,
                    'code' => $code->code,
                    'name' => $code->name,
                    'type' => $code->type ?? 'operational',
                ];
            })->toArray();

        $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
            ->select('id', 'economy_code_id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                    'economy_code_id' => $item->economy_code_id,
                ];
            })->toArray();

        $financialYearId = $schedule?->year_id ?? FinancialYear::where('is_active', true)->first()?->id;
        
        $programmeCodes = ProgrammeCode::with('economicCode')
            ->active()
            ->projects()
            ->when($financialYearId, function ($query) use ($financialYearId) {
                $query->where('financial_year_id', $financialYearId);
            })
            ->orderBy('code')
            ->get()
            ->map(function ($programme) {
                return [
                    'id' => $programme->id,
                    'code' => $programme->code,
                    'name' => $programme->name,
                    'description' => $programme->project_description ?: $programme->name,
                    'budget_code' => $programme->budget_code,
                    'remaining_budget' => (float) $programme->remaining_budget,
                    'approved_budget' => (float) $programme->approved_budget,
                    'economic_code_id' => $programme->economic_code_id,
                    'economic_code' => $programme->economicCode ? [
                        'id' => $programme->economicCode->id,
                        'code' => $programme->economicCode->code,
                        'name' => $programme->economicCode->name,
                    ] : null,
                    'mda_name' => $programme->mda_name,
                    'sector' => $programme->sector,
                    'label' => "{$programme->code} - {$programme->name} (₦" . number_format($programme->remaining_budget, 2) . ")",
                    'value' => $programme->id,
                ];
            });

        return Inertia::render('admin/vouchers/create-final', [
            'voucherType' => $request->get('type', 'standard'),
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'year_id' => $schedule->year_id,
                'mda_id' => $schedule->mda_id,
                'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
                'budget_code' => $schedule->budgetCode?->code,
                'total_amount' => $schedule->total_amount,
                'amount_posted' => $schedule->vouchers->sum('total_amount'),
                'voucher_count' => $schedule->vouchers->count(),
                'narration' => $schedule->narration,
                'payee_name' => $schedule->payee_name,
            ] : null,
            'mdas' => Mda::all()->map(fn($mda) => [
                'value' => $mda->id,
                'label' => $mda->name
            ]),
            'financialYears' => FinancialYear::all()->map(fn($year) => [
                'value' => $year->id,
                'label' => $year->name
            ]),
            'economyCodes' => $economyCodes,
            'economyCodeItems' => $economyCodeItems,
            'programmeCodes' => $programmeCodes,
            'today' => now()->format('Y-m-d'),
            'voucherNumber' => $this->generateVoucherNumber(),
        ]);
    }

    /**
     * Store a final accounts voucher (direct approval, no workflow)
     */
    public function storeFinal(VoucherStoreUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('documents') ?? [];
        $documentTypes = $request->input('document_types', []);
        
        // Override status to Approved for final accounts
        $data['status'] = 'Approved';
        $data['is_final_accounts'] = true;

        Log::info('Final Accounts Voucher Store Request:', [
            'data_keys' => array_keys($data),
            'files_count' => count($files),
            'user_id' => auth()->id(),
        ]);

        try {
            // Create voucher with approved status directly
            $voucher = $this->voucherService->createFinalAccountsVoucher($data, $files, $documentTypes);

            $this->activityLogger->log(
                "Created final accounts voucher {$voucher->voucher_number}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'total_amount' => $voucher->total_amount,
                    'status' => 'Approved (Final Accounts)',
                    'user_id' => auth()->id()
                ],
                'voucher'
            );

            return redirect()
                ->route('vouchers.index')
                ->with('success', 'Final Accounts Voucher ' . $voucher->voucher_number . ' created and approved successfully.');
        } catch (\Exception $e) {
            \Log::error('Final Accounts Voucher Creation Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create final accounts voucher: ' . $e->getMessage());
        }
    }

    public function getApprovals(Voucher $voucher)
    {
        return response()->json($voucher->approvals()->with('user')->get());
    }
}