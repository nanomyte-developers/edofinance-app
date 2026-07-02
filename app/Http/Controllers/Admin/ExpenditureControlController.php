<?php
// app/Http/Controllers/Admin/ExpenditureControlController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mda;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class ExpenditureControlController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display list of vouchers pending Expenditure Control review
     * These are vouchers forwarded by Final Accounts (Step 4)
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
            
    //         // Get vouchers that are forwarded by FA and ready for EC
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'assignedTo'])
    //             ->where('status', 'forwarded')
    //             ->where('is_final_accounts', 1)
    //             ->orderBy('created_at', 'desc');
            
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
    //         $transformedVouchers = $vouchers->through(function ($voucher) {
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
    //             }
                
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
    //                 'payment_status' => $paymentStatus,
    //                 'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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
    //                 'assigned_to' => $voucher->assignedTo ? [
    //                     'id' => $voucher->assignedTo->id,
    //                     'name' => $voucher->assignedTo->name,
    //                     'email' => $voucher->assignedTo->email,
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
    //             'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
    //             'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
    //         ];
            
    //         // Get users for assignment
    //         $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
    //         return Inertia::render('admin/expenditureControl/index', [
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
    //         Log::error('Expenditure Control Index Error: ' . $e->getMessage());
    //         return Inertia::render('admin/expenditureControl/index', [
    //             'vouchers' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'per_page' => 15,
    //                 'current_page' => 1,
    //                 'from' => 0,
    //                 'to' => 0,
    //             ],
    //             'stats' => [
    //                 'pending_count' => 0, 
    //                 'approved_today' => 0, 
    //                 'rejected_today' => 0, 
    //                 'total_processed' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

    /**
     * Display list of vouchers pending Expenditure Control review
     * These are vouchers forwarded by Final Accounts (Step 4)
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
            
    //         // Check if the assigned_to_user_id column exists
    //         $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
    //         // Build the query with conditional eager loading
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals']);
            
    //         // Only load assignedTo relationship if the column exists and relationship is defined
    //         if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
    //             $query->with('assignedTo');
    //         }
            
    //         $query->where('status', 'forwarded')
    //               ->where('is_final_accounts', 1)
    //               ->orderBy('created_at', 'desc');
            
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
    //             }
                
    //             $data = [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => (float) $voucher->total_amount,
    //                 'payee_name' => $voucher->payee_name,
    //                 'status' => $voucher->status,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'payment_status' => $paymentStatus,
    //                 'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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
    //             'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
    //             'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
    //         ];
            
    //         // Get users for assignment
    //         $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
    //         return Inertia::render('admin/expenditureControl/index', [
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
    //         Log::error('Expenditure Control Index Error: ' . $e->getMessage());
    //         return Inertia::render('admin/expenditureControl/index', [
    //             'vouchers' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'per_page' => 15,
    //                 'current_page' => 1,
    //                 'from' => 0,
    //                 'to' => 0,
    //             ],
    //             'stats' => [
    //                 'pending_count' => 0, 
    //                 'approved_today' => 0, 
    //                 'rejected_today' => 0, 
    //                 'total_processed' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

    // /**
    //  * Search for vouchers (API endpoint for AJAX calls)
    //  */
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
            
    //         // Check if the assigned_to_user_id column exists
    //         $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
    //         // Build query with conditional eager loading
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals']);
            
    //         // Only load assignedTo relationship if the column exists and relationship is defined
    //         if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
    //             $query->with('assignedTo');
    //         }
            
    //         $query->where('status', 'forwarded')
    //               ->where('is_final_accounts', 1)
    //               ->orderBy('created_at', 'desc');
            
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
    //             }
                
    //             $data = [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
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
    //             ];
                
    //             // Only add assigned_to if the column exists and relationship is loaded
    //             if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
    //                 $data['assigned_to'] = [
    //                     'id' => $voucher->assignedTo->id,
    //                     'name' => $voucher->assignedTo->name,
    //                 ];
    //             } else {
    //                 $data['assigned_to'] = null;
    //             }
                
    //             return $data;
    //         })->values()->toArray();
            
    //         // Get statistics
    //         $stats = [
    //             'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
    //             'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
    //         ];
            
    //         // Get users for assignment
    //         $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
    //         return response()->json([
    //             'success' => true,
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
    //         Log::error('Expenditure Control Search Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage(),
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
    //                 'pending_count' => 0,
    //                 'approved_today' => 0,
    //                 'rejected_today' => 0,
    //                 'total_processed' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

    /**
     * Display list of vouchers pending Expenditure Control review
     * These are vouchers forwarded by Final Accounts (Step 4)
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
            
    //         // Check if the assigned_to_user_id column exists
    //         $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
    //         // Build the query with conditional eager loading
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals']);
            
    //         // Only load assignedTo relationship if the column exists and relationship is defined
    //         if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
    //             $query->with('assignedTo');
    //         }
            
    //         $query->where('status', 'forwarded')
    //               ->where('is_final_accounts', 1)
    //               ->orderBy('created_at', 'desc');
            
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
    //             }
                
    //             $data = [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_date' => $voucher->voucher_date?->toDateString(),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => (float) $voucher->total_amount,
    //                 'payee_name' => $voucher->payee_name,
    //                 'status' => $voucher->status,
    //                 'voucher_type' => $voucher->voucher_type,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'payment_status' => $paymentStatus,
    //                 'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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

    //             $data['available_destinations'] = $this->getAvailableDestinations($voucher);
    //             $data['workflow'] = $this->getVoucherWorkflow($voucher);
    //             // return $data;
                
    //             return $data;
    //         });

    //         // Make sure approvals are included
    //         $approvals = $vouchers->approvals->map(function ($approval) {
    //             return [
    //                 'id' => $approval->id,
    //                 'action' => $approval->action,
    //                 'comment' => $approval->comment,
    //                 'action_at' => $approval->action_at?->toDateTimeString(),
    //                 'created_at' => $approval->created_at?->toDateTimeString(),
    //                 'approval_role' => $approval->approval_role,
    //                 'status' => $approval->status,
    //                 'user' => $approval->user ? [
    //                     'id' => $approval->user->id,
    //                     'name' => $approval->user->name,
    //                 ] : null,
    //             ];
    //         });
            
    //         // Get statistics
    //         $stats = [
    //             'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
    //             'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
    //         ];
            
    //         // Get users for assignment - FIX: Use Spatie roles
    //         $users = $this->getUsersForAssignment();
            
    //         return Inertia::render('admin/expenditureControl/index', [
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
    //             'approvals' => $approvals,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Expenditure Control Index Error: ' . $e->getMessage());
    //         return Inertia::render('admin/expenditureControl/index', [
    //             'vouchers' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'per_page' => 15,
    //                 'current_page' => 1,
    //                 'from' => 0,
    //                 'to' => 0,
    //             ],
    //             'stats' => [
    //                 'pending_count' => 0, 
    //                 'approved_today' => 0, 
    //                 'rejected_today' => 0, 
    //                 'total_processed' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

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
        
        // Check if the assigned_to_user_id column exists
        $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
        
        // Build the query with conditional eager loading
        $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'approvals.user']);
        
        // Only load assignedTo relationship if the column exists and relationship is defined
        if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
            $query->with('assignedTo');
        }
        
        $query->where('status', 'forwarded')
              ->where('is_final_accounts', 1)
              ->orderBy('created_at', 'desc');
        
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
            }
            
            // Transform approvals for this specific voucher
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
            
            $data = [
                'id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'voucher_date' => $voucher->voucher_date?->toDateString(),
                'narration' => $voucher->narration,
                'total_amount' => (float) $voucher->total_amount,
                'payee_name' => $voucher->payee_name,
                'status' => $voucher->status,
                'voucher_type' => $voucher->voucher_type,
                'created_at' => $voucher->created_at?->toDateTimeString(),
                'payment_status' => $paymentStatus,
                'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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
                'approvals' => $approvals, // Add approvals to each voucher
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

            $data['available_destinations'] = $this->getAvailableDestinations($voucher);
            $data['workflow'] = $this->getVoucherWorkflow($voucher);
            
            return $data;
        });
        
        // Get statistics
        $stats = [
            'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
            'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
            'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
            'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
            'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
            'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
            'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
            'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
            'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
        ];
        
        // Get users for assignment - FIX: Use Spatie roles
        $users = $this->getUsersForAssignment();
        
        // Get MDAs for filter
        $mdas = Mda::orderBy('name')->get(['id', 'name', 'code']);
        
        return Inertia::render('admin/expenditureControl/index', [
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
            'mdas' => $mdas, // Add MDAs for filter
        ]);
        
    } catch (\Exception $e) {
        Log::error('Expenditure Control Index Error: ' . $e->getMessage());
        return Inertia::render('admin/expenditureControl/index', [
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
                'total_processed' => 0,
                'paid_count' => 0,
                'pending_mas_count' => 0,
                'pending_ag_count' => 0,
                'total_amount_paid' => 0,
                'total_amount_pending' => 0,
            ],
            'users' => [],
            'mdas' => [],
        ]);
    }
}

    public function getApprovals(Voucher $voucher)
    {
        return response()->json($voucher->approvals()->with('user')->get());
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
            $tab = $request->input('tab', 'all'); // <-- ADD THIS LINE HERE
            
            // Check if the assigned_to_user_id column exists
            $hasAssignment = Schema::hasColumn('vouchers', 'assigned_to_user_id');
            
            // Build query with conditional eager loading
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals']);
            
            // Only load assignedTo relationship if the column exists and relationship is defined
            if ($hasAssignment && method_exists(Voucher::class, 'assignedTo')) {
                $query->with('assignedTo');
            }
            
            $query->where('status', 'forwarded')
                ->where('is_final_accounts', 1)
                ->orderBy('created_at', 'desc');
            
            // =============================================
            // ADD THE TAB FILTER HERE - AFTER THE WHERE CLAUSES
            // =============================================
            if ($tab === 'liability') {
                $query->whereDate('final_approved_at', today());
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
                }
                
                $data = [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'final_approved_at' => $voucher->final_approved_at?->toDateTimeString(), // <-- ADD THIS FOR LIABILITY CHECK
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
                
                // Only add assigned_to if the column exists and relationship is loaded
                if ($hasAssignment && method_exists(Voucher::class, 'assignedTo') && $voucher->relationLoaded('assignedTo') && $voucher->assignedTo) {
                    $data['assigned_to'] = [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                    ];
                } else {
                    $data['assigned_to'] = null;
                }
                
                return $data;
            })->values()->toArray();
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
                'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
                'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
                // =============================================
                // ADD LIABILITY COUNT TO STATS HERE
                // =============================================
                'liability_count' => Voucher::where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->whereDate('final_approved_at', today())
                    ->count(),
            ];
            
            // Get users for assignment - FIX: Use Spatie roles
            $users = $this->getUsersForAssignment();
            
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
            Log::error('Expenditure Control Search Error: ' . $e->getMessage(), [
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
                    'paid_count' => 0,
                    'pending_mas_count' => 0,
                    'pending_ag_count' => 0,
                    'total_amount_paid' => 0,
                    'total_amount_pending' => 0,
                    'liability_count' => 0, // <-- ADD THIS TO ERROR RESPONSE TOO
                ],
                'users' => [],
            ]);
        }
    }

    /**
     * Get users for assignment using Spatie roles
     */
    private function getUsersForAssignment()
    {
        try {
            // Get users with either Expenditure Control Admin or Expenditure Control Staff role
            $users = User::role(['Expenditure Control Admin', 'Expenditure Control Staff'])->get(['id', 'name', 'email']);
            
            // If no users found with those specific roles, fallback to users with any role
            if ($users->isEmpty()) {
                $users = User::whereHas('roles', function ($query) {
                    $query->whereIn('name', ['admin', 'staff']);
                })->get(['id', 'name', 'email']);
            }
            
            return $users;
            
        } catch (\Exception $e) {
            Log::warning('Error fetching users for assignment: ' . $e->getMessage());
            // Fallback: get all users
            return User::select('id', 'name', 'email')->get();
        }
    }

    /**
     * Show voucher details for Expenditure Control
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
                    'economic_code' => $voucher->bankActivity->economic_code,
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
            
            return Inertia::render('admin/expenditureControl/show', [
                'voucher' => $voucherData,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Expenditure Control Show Error: ' . $e->getMessage());
            return redirect()->route('expenditure-control.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Approve voucher from Expenditure Control
     * Forwards to Accountant General (AG) for standard/prepayment vouchers
     */
    // public function approve(Voucher $voucher, Request $request)
    // {
    //     Log::info('========================================');
    //     Log::info('EXPENDITURE CONTROL APPROVAL - STARTED');
    //     Log::info('========================================');
    //     Log::info('Expenditure Control Approval Request:', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'current_status' => $voucher->status,
    //         'user_id' => auth()->id(),
    //         'user_name' => auth()->user()?->name,
    //     ]);

    //     DB::beginTransaction();
        
    //     try {
    //         // CHECK 1: Validate voucher status
    //         Log::info('CHECK 1: Validating voucher status...');
    //         if ($voucher->status !== 'forwarded') {
    //             Log::warning('CHECK 1 FAILED: Voucher not forwarded', [
    //                 'current_status' => $voucher->status,
    //                 'expected_status' => 'forwarded'
    //             ]);
    //             DB::rollBack();
    //             return redirect()->route('expenditure-control.index')
    //                 ->with('error', "Voucher {$voucher->voucher_number} must be forwarded by Final Accounts first.");
    //         }
    //         Log::info('CHECK 1 PASSED: Voucher status is forwarded ✅');

    //         // CHECK 2: Validate is_final_accounts
    //         Log::info('CHECK 2: Validating is_final_accounts...');
    //         if (!$voucher->is_final_accounts) {
    //             Log::warning('CHECK 2 FAILED: Voucher not processed by FA', [
    //                 'is_final_accounts' => $voucher->is_final_accounts
    //             ]);
    //             DB::rollBack();
    //             return redirect()->route('expenditure-control.index')
    //                 ->with('error', "Voucher {$voucher->voucher_number} must be processed by Final Accounts first.");
    //         }
    //         Log::info('CHECK 2 PASSED: is_final_accounts is 1 ✅');

    //         // Get the current maximum approval step
    //         Log::info('Getting current approval step...');
    //         $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
    //         $ecStep = $maxStep + 1;
    //         $nextStep = $ecStep + 1;
    //         Log::info('Approval steps calculated:', [
    //             'max_step' => $maxStep,
    //             'ec_step' => $ecStep,
    //             'next_step' => $nextStep
    //         ]);

    //         // Next role is Accountant General
    //         $nextRole = VoucherApproval::ROLE_AG;
    //         $nextRoleDisplay = 'Accountant General (AG)';
    //         Log::info('Next role determined:', [
    //             'next_role' => $nextRole,
    //             'next_role_display' => $nextRoleDisplay
    //         ]);

    //         // Create EC approval record
    //         Log::info('Creating EC approval record...');
    //         $ecApproval = VoucherApproval::create([
    //             'voucher_id' => $voucher->id,
    //             'user_id' => auth()->id(),
    //             'approval_role' => VoucherApproval::ROLE_EC,
    //             'approval_step' => $ecStep,
    //             'approval_level' => $ecStep,
    //             'action' => VoucherApproval::ACTION_APPROVED,
    //             'status' => VoucherApproval::STATUS_APPROVED,
    //             'comment' => $request->input('comment', 'Approved by Expenditure Control'),
    //             'action_at' => now(),
    //             'approved_at' => now(),
    //         ]);
    //         Log::info('EC approval record created:', ['approval_id' => $ecApproval->id]);

    //         // Create forward to next stage record
    //         Log::info('Creating forward to next stage record...');
    //         $forwardApproval = VoucherApproval::create([
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
    //         Log::info('Forward record created:', ['approval_id' => $forwardApproval->id]);

    //         // Update voucher status
    //         Log::info('Updating voucher status to ec_approved...');
    //         $updateData = [
    //             'status' => 'ec_approved',
    //             'ec_approved_by' => auth()->id(),
    //             'ec_approved_at' => now(),
    //         ];
    //         $updated = $voucher->update($updateData);
            
    //         if (!$updated) {
    //             Log::error('Voucher update FAILED!', [
    //                 'voucher_id' => $voucher->id,
    //                 'update_data' => $updateData
    //             ]);
    //             throw new \Exception('Failed to update voucher record');
    //         }
    //         Log::info('Voucher updated successfully ✅');

    //         // Log activity
    //         $this->activityLogger->log(
    //             "Expenditure Control approved voucher {$voucher->voucher_number}",
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'forwarded_to' => $nextRoleDisplay,
    //                 'ec_step' => $ecStep,
    //                 'approved_by' => auth()->id(),
    //                 'approved_by_name' => auth()->user()?->name,
    //             ],
    //             'voucher'
    //         );
            
    //         DB::commit();
            
    //         Log::info('========================================');
    //         Log::info('EXPENDITURE CONTROL APPROVAL - COMPLETED SUCCESSFULLY');
    //         Log::info('========================================');
            
    //         return redirect()->route('expenditure-control.index')
    //             ->with('success', "Voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay}.");
                
    //     } catch (\Exception $e) {
    //         DB::rollBack();
            
    //         Log::error('========================================');
    //         Log::error('EXPENDITURE CONTROL APPROVAL - FAILED');
    //         Log::error('========================================');
    //         Log::error('Approval Exception:', [
    //             'voucher_id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'error_message' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString(),
    //         ]);
            
    //         return redirect()->route('expenditure-control.index')
    //             ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
    //     }
    // }

    /**
     * Reject voucher from Expenditure Control (send back to DFA)
     */
    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('EXPENDITURE CONTROL REJECTION - STARTED');
        Log::info('========================================');
        Log::info('Expenditure Control Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'current_status' => $voucher->status,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'reason' => $request->input('reason'),
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate reason
            Log::info('CHECK 1: Validating rejection reason...');
            $reason = $request->input('reason');
            
            if (empty($reason)) {
                Log::warning('CHECK 1 FAILED: Rejection reason is empty');
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', 'Rejection reason is required.');
            }
            Log::info('CHECK 1 PASSED: Rejection reason provided ✅', ['reason_length' => strlen($reason)]);

            // CHECK 2: Validate voucher status
            Log::info('CHECK 2: Validating voucher status...');
            if ($voucher->status !== 'forwarded') {
                Log::warning('CHECK 2 FAILED: Voucher not forwarded', [
                    'current_status' => $voucher->status,
                    'expected_status' => 'forwarded'
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be forwarded by Final Accounts first.");
            }
            Log::info('CHECK 2 PASSED: Voucher status is forwarded ✅');

            // Get the current maximum approval step
            Log::info('Getting current approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $rejectionStep = $maxStep + 1;
            Log::info('Rejection step calculated:', ['rejection_step' => $rejectionStep]);

            // Create rejection record
            Log::info('Creating rejection record...');
            $rejection = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_EC,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);
            Log::info('Rejection record created:', ['rejection_id' => $rejection->id]);

            // Update voucher status
            Log::info('Updating voucher status to sent_back...');
            $updateData = [
                'status' => 'sent_back',
                'rejection_reason' => $reason,
                'rejected_by' => auth()->id(),
                'rejected_at' => now(),
            ];
            $updated = $voucher->update($updateData);
            
            if (!$updated) {
                Log::error('Voucher update FAILED!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            Log::info('Voucher updated successfully ✅');

            // Log activity
            $this->activityLogger->log(
                "Expenditure Control rejected voucher {$voucher->voucher_number}",
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
            Log::info('EXPENDITURE CONTROL REJECTION - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            
            return redirect()->route('expenditure-control.index')
                ->with('success', "Voucher {$voucher->voucher_number} has been rejected and returned to DFA.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('EXPENDITURE CONTROL REJECTION - FAILED');
            Log::error('========================================');
            Log::error('Rejection Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.index')
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Assign voucher to a staff member
     */
    public function assign(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('ASSIGN VOUCHER TO STAFF - STARTED');
        Log::info('========================================');
        Log::info('Assign Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'assign_to_user_id' => $request->input('user_id'),
        ]);

        DB::beginTransaction();
        
        try {
            $userId = $request->input('user_id');
            
            if (empty($userId)) {
                Log::warning('CHECK FAILED: User ID is required');
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', 'Please select a staff member to assign this voucher.');
            }

            // Check if user exists
            $user = User::find($userId);
            if (!$user) {
                Log::warning('CHECK FAILED: User not found', ['user_id' => $userId]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', 'Selected staff member not found.');
            }

            // Update voucher with assigned user
            $voucher->update([
                'assigned_to_user_id' => $userId,
                'assigned_at' => now(),
                'assigned_by' => auth()->id(),
            ]);

            // Log activity
            $this->activityLogger->log(
                "Voucher {$voucher->voucher_number} assigned to {$user->name}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'assigned_to' => $userId,
                    'assigned_to_name' => $user->name,
                    'assigned_by' => auth()->id(),
                    'assigned_by_name' => auth()->user()?->name,
                    'assigned_at' => now()->toDateTimeString(),
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('========================================');
            Log::info('ASSIGN VOUCHER - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            
            return redirect()->route('expenditure-control.index')
                ->with('success', "Voucher {$voucher->voucher_number} assigned to {$user->name} successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('ASSIGN VOUCHER - FAILED');
            Log::error('========================================');
            Log::error('Assign Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.index')
                ->with('error', 'Failed to assign voucher: ' . $e->getMessage());
        }
    }

    /**
     * Search for vouchers (API endpoint for AJAX calls)
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
            
    //         $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'approvals', 'assignedTo'])
    //             ->where('status', 'forwarded')
    //             ->where('is_final_accounts', 1)
    //             ->orderBy('created_at', 'desc');
            
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
    //         $transformedVouchers = $vouchers->map(function ($voucher) {
    //             // Determine payment status
    //             $paymentStatus = 'unknown';
    //             if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
    //                 $paymentStatus = 'paid';
    //             } elseif ($voucher->status === 'ag_approved') {
    //                 $paymentStatus = 'awaiting_mas';
    //             } elseif ($voucher->status === 'ec_approved') {
    //                 $paymentStatus = 'awaiting_ag';
    //             }
                
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
    //                 ] : null,
    //                 'assigned_to' => $voucher->assignedTo ? [
    //                     'id' => $voucher->assignedTo->id,
    //                     'name' => $voucher->assignedTo->name,
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
    //             ];
    //         })->values()->toArray();
            
    //         // Get statistics
    //         $stats = [
    //             'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
    //             'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
    //             'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
    //             'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
    //             'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
    //             'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
    //             'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
    //             'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
    //             'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
    //         ];
            
    //         // Get users for assignment
    //         $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
    //         return response()->json([
    //             'success' => true,
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
    //         Log::error('Expenditure Control Search Error: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString()
    //         ]);
            
    //         return response()->json([
    //             'success' => false,
    //             'error' => $e->getMessage(),
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
    //                 'pending_count' => 0,
    //                 'approved_today' => 0,
    //                 'rejected_today' => 0,
    //                 'total_processed' => 0,
    //                 'paid_count' => 0,
    //                 'pending_mas_count' => 0,
    //                 'pending_ag_count' => 0,
    //                 'total_amount_paid' => 0,
    //                 'total_amount_pending' => 0,
    //             ],
    //             'users' => [],
    //         ]);
    //     }
    // }

    /**
     * Get statistics (API endpoint for AJAX calls)
     */
    public function stats()
    {
        try {
            $stats = [
                'pending_count' => Voucher::where('status', 'forwarded')->where('is_final_accounts', 1)->count(),
                'approved_today' => Voucher::where('status', 'ec_approved')->whereDate('updated_at', today())->count(),
                'rejected_today' => Voucher::where('status', 'sent_back')->whereDate('updated_at', today())->count(),
                'total_processed' => Voucher::whereIn('status', ['ec_approved', 'ec_rejected'])->count(),
                'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Expenditure Control Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_count' => 0,
                'approved_today' => 0,
                'rejected_today' => 0,
                'total_processed' => 0,
                'paid_count' => 0,
                'pending_mas_count' => 0,
                'pending_ag_count' => 0,
                'total_amount_paid' => 0,
                'total_amount_pending' => 0,
            ]);
        }
    }

    /**
     * Mark voucher as paid (for MAS)
     */
    public function markAsPaid(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('MARK AS PAID - STARTED');
        Log::info('========================================');
        Log::info('Mark Voucher as Paid Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'current_status' => $voucher->status,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'payment_reference' => $request->input('payment_reference'),
            'payment_comment' => $request->input('paymentComment'),
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate payment reference
            Log::info('CHECK 1: Validating payment reference...');
            $paymentReference = $request->input('payment_reference');
            $paymentComment = $request->input('paymentComment');
            
            if (empty($paymentReference)) {
                Log::warning('CHECK 1 FAILED: Payment reference is empty');
                DB::rollBack();
                return redirect()->route('expenditure-control.payment-status')
                    ->with('error', 'Payment reference number is required.');
            }
            Log::info('CHECK 1 PASSED: Payment reference provided ✅');

            // CHECK 2: Validate voucher status
            Log::info('CHECK 2: Validating voucher status...');
            if ($voucher->status !== 'ag_approved') {
                Log::warning('CHECK 2 FAILED: Voucher not AG approved', [
                    'current_status' => $voucher->status,
                    'expected_status' => 'ag_approved'
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.payment-status')
                    ->with('error', "Voucher must be approved by Accountant General before marking as paid. Current status: {$voucher->status}");
            }
            Log::info('CHECK 2 PASSED: Voucher status is ag_approved ✅');

            // CHECK 3: Check if already marked as paid
            Log::info('CHECK 3: Checking if already marked as paid...');
            if ($voucher->status === 'closed' && $voucher->mas_approved_by) {
                Log::warning('CHECK 3 FAILED: Voucher already marked as paid', [
                    'mas_approved_by' => $voucher->mas_approved_by
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.payment-status')
                    ->with('warning', "Voucher {$voucher->voucher_number} has already been marked as paid.");
            }
            Log::info('CHECK 3 PASSED: Voucher not yet marked as paid ✅');

            // Get current step
            Log::info('Getting current approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $masStep = $maxStep + 1;
            Log::info('MAS step calculated:', ['mas_step' => $masStep]);

            // Create MAS approval record
            Log::info('Creating MAS approval record...');
            $masApproval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_MAS,
                'approval_step' => $masStep,
                'approval_level' => $masStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $paymentComment ?: 'Payment processed by Management Account Section',
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            Log::info('MAS approval record created:', ['approval_id' => $masApproval->id]);

            // Update voucher status with all fields
            Log::info('Updating voucher status...');
            $updateData = [
                'status' => 'closed',
                'mas_approved_by' => auth()->id(),
                'mas_approved_at' => now(),
                'closed_at' => now(),
                'payment_reference' => $paymentReference,
                'payment_comment' => $paymentComment,
                'payment_date' => now(),
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

            // Log activity
            if (isset($this->activityLogger)) {
                $this->activityLogger->log(
                    "Management Account Section marked voucher {$voucher->voucher_number} as paid",
                    [
                        'voucher_id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'total_amount' => $voucher->total_amount,
                        'payment_reference' => $paymentReference,
                        'payment_comment' => $paymentComment,
                        'mas_step' => $masStep,
                        'marked_by' => auth()->id(),
                        'marked_by_name' => auth()->user()?->name,
                        'marked_at' => now()->toDateTimeString(),
                    ],
                    'voucher'
                );
                
                $this->activityLogger->logAction('marked_as_paid', $voucher, [
                    'payment_reference' => $paymentReference,
                    'payment_comment' => $paymentComment,
                    'amount' => $voucher->total_amount,
                    'marked_by' => auth()->user()?->name,
                ]);
            }
            
            DB::commit();
            
            Log::info('========================================');
            Log::info('MARK AS PAID - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Voucher marked as paid successfully:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'payment_reference' => $paymentReference,
            ]);
            
            return redirect()->route('expenditure-control.payment-status')
                ->with('success', "Voucher {$voucher->voucher_number} marked as paid successfully.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('MARK AS PAID - FAILED');
            Log::error('========================================');
            Log::error('Mark as Paid Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.payment-status')
                ->with('error', 'Failed to mark voucher as paid: ' . $e->getMessage());
        }
    }

    /**
     * Search payment status (API endpoint)
     */
    public function searchPaymentStatus(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $paymentStatus = $request->input('payment_status', 'all');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'assignedTo'])
                ->whereIn('status', ['ec_approved', 'ag_approved', 'closed'])
                ->orderBy('created_at', 'desc');
            
            // Apply payment status filter
            if ($paymentStatus === 'paid') {
                $query->where('status', 'closed')->whereNotNull('mas_approved_at');
            } elseif ($paymentStatus === 'awaiting_mas') {
                $query->where('status', 'ag_approved')->whereNull('mas_approved_at');
            } elseif ($paymentStatus === 'awaiting_ag') {
                $query->where('status', 'ec_approved')->whereNull('ag_approved_at');
            }
            
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
            
            // Transform data to include payment_status
            $transformedVouchers = $vouchers->through(function ($voucher) {
                // Determine payment status
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                    $paymentDate = $voucher->mas_approved_at;
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                    $paymentDate = null;
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
                    $paymentDate = null;
                } else {
                    $paymentStatus = 'unknown';
                    $paymentDate = null;
                }
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'payment_status' => $paymentStatus,
                    'payment_date' => $paymentDate?->toDateTimeString(),
                    'bank_activity' => $voucher->bankActivity ? [
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                    ] : null,
                    'mda' => $voucher->mda ? [
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                ];
            });
            
            return response()->json([
                'vouchers' => $transformedVouchers,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Payment Status Search Error: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get payment status statistics (API endpoint)
     */
    public function paymentStats()
    {
        try {
            $stats = [
                'total_vouchers' => Voucher::whereIn('status', ['ec_approved', 'ag_approved', 'closed'])->count(),
                'paid_count' => Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->count(),
                'pending_mas_count' => Voucher::where('status', 'ag_approved')->whereNull('mas_approved_at')->count(),
                'pending_ag_count' => Voucher::where('status', 'ec_approved')->whereNull('ag_approved_at')->count(),
                'total_amount_paid' => (float) Voucher::where('status', 'closed')->whereNotNull('mas_approved_at')->sum('total_amount'),
                'total_amount_pending' => (float) Voucher::whereIn('status', ['ec_approved', 'ag_approved'])->sum('total_amount'),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Payment Stats Error: ' . $e->getMessage());
            return response()->json([
                'total_vouchers' => 0,
                'paid_count' => 0,
                'pending_mas_count' => 0,
                'pending_ag_count' => 0,
                'total_amount_paid' => 0,
                'total_amount_pending' => 0,
            ]);
        }
    }

    /**
     * ==============================================
     * SALARY VOUCHER METHODS
     * ==============================================
     */

    /**
     * Display list of salary vouchers pending Expenditure Control review
     * Salary vouchers follow different flow: DFA -> IA -> FA -> EC -> Inspectorate -> TCO -> Closed
     */
    public function salaryIndex(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $salaryType = $request->input('voucher_type', '');
            $statusFilter = $request->input('status', '');
            
            // Get salary vouchers that are forwarded by FA and ready for EC
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'assignedTo'])
                ->where('voucher_type', 'salary')
                ->whereIn('status', ['forwarded', 'ec_approved', 'inspectorate_pending'])
                ->orderBy('created_at', 'desc');
            
            // Apply salary type filter
            if ($salaryType) {
                $query->where('voucher_type', $salaryType);
            }
            
            // Apply status filter
            if ($statusFilter) {
                $query->where('status', $statusFilter);
            }
            
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
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $iaApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_IA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_date' => $voucher->voucher_date?->toDateString(),
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ia_approved_at' => $iaApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'forwarded_to_inspectorate_at' => $voucher->forwarded_to_inspectorate_at?->toDateTimeString(),
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
                ];
            });
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'forwarded')
                    ->count(),
                'ec_approved_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'ec_approved')
                    ->count(),
                'forwarded_to_inspectorate' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'inspectorate_pending')
                    ->count(),
                'total_salary' => Voucher::where('voucher_type', 'salary')->count(),
            ];
            
            // Get users for assignment
            $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
            return Inertia::render('admin/expenditureControl/salary', [
                'vouchers' => [
                    'data' => $transformedVouchers,
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'from' => $vouchers->firstItem(),
                    'to' => $vouchers->lastItem(),
                ],
                'stats' => $stats,
                'salaryTypes' => ['monthly', 'bonus', 'allowance', 'gratuity', 'pension', 'other'],
                'users' => $users,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Salary Index Error: ' . $e->getMessage());
            return Inertia::render('admin/expenditureControl/salary', [
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
                    'ec_approved_count' => 0,
                    'forwarded_to_inspectorate' => 0,
                    'total_salary' => 0,
                ],
                'salaryTypes' => ['monthly', 'bonus', 'allowance', 'gratuity', 'pension', 'other'],
                'users' => [],
            ]);
        }
    }

    /**
     * Show salary voucher details for Expenditure Control
     */
    public function showSalaryVoucher($id)
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
            
            // Ensure it's a salary voucher
            if ($voucher->voucher_type !== 'salary') {
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', 'This is not a salary voucher.');
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
                    'economic_code' => $voucher->bankActivity->economic_code,
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
                'forwarded_to_inspectorate_at' => $voucher->forwarded_to_inspectorate_at?->toDateTimeString(),
                'forwarded_to_inspectorate_by' => $voucher->forwarded_to_inspectorate_by,
                'inspectorate_approved_at' => $voucher->inspectorate_approved_at?->toDateTimeString(),
                'inspectorate_approved_by' => $voucher->inspectorate_approved_by,
                'tco_approved_at' => $voucher->tco_approved_at?->toDateTimeString(),
                'tco_approved_by' => $voucher->tco_approved_by,
            ];
            
            // Get approval flow for salary
            $approvalFlow = [
                'draft' => 'Draft',
                'dfa_approved' => 'DFA Approved',
                'ia_approved' => 'Internal Audit Approved',
                'fa_approved' => 'Final Accounts Approved',
                'ec_approved' => 'Expenditure Control Approved',
                'inspectorate_pending' => 'With Inspectorate',
                'inspectorate_approved' => 'Inspectorate Approved',
                'tco_approved' => 'TCO Approved (Closed)',
            ];
            
            $currentStep = $voucher->approvals()->max('approval_step') ?? 0;
            $nextRole = $this->getSalaryNextRole($voucher);
            
            return Inertia::render('admin/expenditureControl/show-salary', [
                'voucher' => $voucherData,
                'approvalFlow' => $approvalFlow,
                'currentStep' => $currentStep,
                'nextRole' => $nextRole,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Show Salary Error: ' . $e->getMessage());
            return redirect()->route('expenditure-control.salary.index')
                ->with('error', 'Voucher not found.');
        }
    }

    /**
     * Get next role for salary voucher
     */
    private function getSalaryNextRole(Voucher $voucher)
    {
        $status = $voucher->status;
        
        $nextRoles = [
            'forwarded' => VoucherApproval::ROLE_EC,
            'ec_approved' => VoucherApproval::ROLE_INSPECTORATE,
            'inspectorate_pending' => VoucherApproval::ROLE_INSPECTORATE,
            'inspectorate_approved' => VoucherApproval::ROLE_TCO,
        ];
        
        return $nextRoles[$status] ?? null;
    }

    /**
     * Approve salary voucher from Expenditure Control
     * Forwards to Inspectorate (not AG like regular vouchers)
     */
    public function approveSalaryVoucher(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('SALARY APPROVAL - METHOD STARTED');
        Log::info('========================================');
        Log::info('Expenditure Control Salary Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'current_status' => $voucher->status,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_data' => $request->all(),
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate voucher type
            Log::info('CHECK 1: Validating voucher type...');
            if ($voucher->voucher_type !== 'salary') {
                Log::warning('CHECK 1 FAILED: Not a salary voucher', [
                    'voucher_id' => $voucher->id,
                    'voucher_type' => $voucher->voucher_type,
                    'expected_type' => 'salary'
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Not a salary voucher');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', 'This is not a salary voucher.');
            }
            Log::info('CHECK 1 PASSED: Voucher type is salary ✅');

            // CHECK 2: Validate voucher status
            Log::info('CHECK 2: Validating voucher status...');
            Log::info('Current status check:', [
                'current_status' => $voucher->status,
                'expected_status' => 'forwarded',
                'voucher_number' => $voucher->voucher_number
            ]);
            
            if ($voucher->status !== 'forwarded') {
                Log::warning('CHECK 2 FAILED: Voucher not in correct state', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'current_status' => $voucher->status,
                    'expected_status' => 'forwarded'
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Voucher must be forwarded by FA');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be forwarded by Final Accounts (FA) first. Current status: {$voucher->status}");
            }
            Log::info('CHECK 2 PASSED: Voucher status is forwarded ✅');

            // All checks passed
            Log::info('ALL VALIDATION CHECKS PASSED ✅');
            Log::info('Proceeding with salary voucher approval...');

            // Get the current maximum approval step
            Log::info('Getting current approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $ecStep = $maxStep + 1;
            $nextStep = $ecStep + 1;
            Log::info('Approval steps calculated:', [
                'max_step' => $maxStep,
                'ec_step' => $ecStep,
                'next_step' => $nextStep
            ]);

            // Next role is Inspectorate (not AG)
            $nextRole = VoucherApproval::ROLE_INSPECTORATE;
            $nextRoleDisplay = 'Inspectorate';
            Log::info('Next role determined:', [
                'next_role' => $nextRole,
                'next_role_display' => $nextRoleDisplay
            ]);

            // Create EC approval record
            Log::info('Creating EC approval record...');
            $ecApproval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_EC,
                'approval_step' => $ecStep,
                'approval_level' => $ecStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $request->input('comment', 'Approved by Expenditure Control'),
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            Log::info('EC approval record created:', [
                'approval_id' => $ecApproval->id,
                'voucher_id' => $voucher->id
            ]);

            // Create forward to Inspectorate record
            Log::info('Creating forward to Inspectorate record...');
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
            Log::info('Forward to Inspectorate record created:', [
                'approval_id' => $forwardApproval->id,
                'voucher_id' => $voucher->id
            ]);

            // Update voucher status - EC approved, ready for Inspectorate
            Log::info('Updating voucher status to ec_approved...');
            $updateData = [
                'status' => 'ec_approved',
                'ec_approved_by' => auth()->id(),
                'ec_approved_at' => now(),
            ];
            Log::info('Update data:', $updateData);

            $updated = $voucher->update($updateData);
            
            if (!$updated) {
                Log::error('Voucher update failed!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            
            Log::info('Voucher updated successfully:', [
                'voucher_id' => $voucher->id,
                'new_status' => 'ec_approved',
                'ec_approved_by' => auth()->id(),
                'ec_approved_at' => now()->toDateTimeString()
            ]);

            DB::commit();
            
            Log::info('========================================');
            Log::info('SALARY APPROVAL - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Salary voucher approved by EC:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'forwarded_to' => $nextRoleDisplay,
                'approved_by' => auth()->user()?->name,
                'approved_at' => now()->toDateTimeString()
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('success', "Salary voucher {$voucher->voucher_number} approved and forwarded to {$nextRoleDisplay}.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('SALARY APPROVAL - FAILED');
            Log::error('========================================');
            Log::error('Salary Approval Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('error', 'Failed to approve salary voucher: ' . $e->getMessage());
        }
    }

    /**
     * Reject salary voucher from Expenditure Control (send back to FA)
     */
    public function rejectSalaryVoucher(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('SALARY REJECTION - METHOD STARTED');
        Log::info('========================================');
        Log::info('Expenditure Control Salary Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'current_status' => $voucher->status,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
            'request_data' => $request->all(),
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate reason
            Log::info('CHECK 1: Validating rejection reason...');
            $reason = $request->input('reason');
            
            if (empty($reason)) {
                Log::warning('CHECK 1 FAILED: Rejection reason is empty');
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Rejection reason required');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', 'Rejection reason is required.');
            }
            Log::info('CHECK 1 PASSED: Rejection reason provided ✅', ['reason_length' => strlen($reason)]);

            // CHECK 2: Validate voucher type
            Log::info('CHECK 2: Validating voucher type...');
            if ($voucher->voucher_type !== 'salary') {
                Log::warning('CHECK 2 FAILED: Not a salary voucher', [
                    'voucher_id' => $voucher->id,
                    'voucher_type' => $voucher->voucher_type
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Not a salary voucher');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', 'This is not a salary voucher.');
            }
            Log::info('CHECK 2 PASSED: Voucher type is salary ✅');

            // CHECK 3: Validate voucher status
            Log::info('CHECK 3: Validating voucher status...');
            Log::info('Current status check:', [
                'current_status' => $voucher->status,
                'expected_status' => 'forwarded',
                'voucher_number' => $voucher->voucher_number
            ]);
            
            if ($voucher->status !== 'forwarded') {
                Log::warning('CHECK 3 FAILED: Voucher not in correct state for rejection', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'current_status' => $voucher->status,
                    'expected_status' => 'forwarded'
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Voucher must be FA approved');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be forwarded by Final Accounts (FA) first. Current status: {$voucher->status}");
            }
            Log::info('CHECK 3 PASSED: Voucher status is forwarded ✅');

            // All checks passed
            Log::info('ALL VALIDATION CHECKS PASSED ✅');
            Log::info('Proceeding with salary voucher rejection...');

            // Get the current maximum approval step
            Log::info('Getting current approval step...');
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
                'approval_role' => VoucherApproval::ROLE_EC,
                'approval_step' => $rejectionStep,
                'approval_level' => $rejectionStep,
                'action' => VoucherApproval::ACTION_DECLINED,
                'status' => VoucherApproval::STATUS_REJECTED,
                'comment' => $reason,
                'action_at' => now(),
                'rejected_at' => now(),
            ]);
            Log::info('Rejection record created:', [
                'rejection_id' => $rejection->id,
                'voucher_id' => $voucher->id
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
                Log::error('Voucher update failed!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            
            Log::info('Voucher updated successfully:', [
                'voucher_id' => $voucher->id,
                'new_status' => 'sent_back',
                'rejected_by' => auth()->id(),
                'rejected_at' => now()->toDateTimeString()
            ]);

            DB::commit();
            
            Log::info('========================================');
            Log::info('SALARY REJECTION - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Salary voucher rejected:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'reason' => $reason,
                'rejected_by' => auth()->user()?->name,
                'rejected_at' => now()->toDateTimeString()
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('success', "Salary voucher {$voucher->voucher_number} has been rejected and returned to FA.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('SALARY REJECTION - FAILED');
            Log::error('========================================');
            Log::error('Salary Rejection Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('error', 'Failed to reject salary voucher: ' . $e->getMessage());
        }
    }

    /**
     * Forward salary voucher to Inspectorate (after EC approval)
     */
    public function forwardToInspectorate(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('FORWARD TO INSPECTORATE - METHOD STARTED');
        Log::info('========================================');
        Log::info('Forward to Inspectorate Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'current_status' => $voucher->status,
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate voucher type
            Log::info('CHECK 1: Validating voucher type...');
            if ($voucher->voucher_type !== 'salary') {
                Log::warning('CHECK 1 FAILED: Not a salary voucher', [
                    'voucher_id' => $voucher->id,
                    'voucher_type' => $voucher->voucher_type
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Not a salary voucher');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', 'This is not a salary voucher.');
            }
            Log::info('CHECK 1 PASSED: Voucher type is salary ✅');

            // CHECK 2: Validate voucher status
            Log::info('CHECK 2: Validating voucher status...');
            Log::info('Current status check:', [
                'current_status' => $voucher->status,
                'expected_status' => 'ec_approved',
                'voucher_number' => $voucher->voucher_number
            ]);
            
            if ($voucher->status !== 'ec_approved') {
                Log::warning('CHECK 2 FAILED: Voucher not EC approved', [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'current_status' => $voucher->status,
                    'expected_status' => 'ec_approved'
                ]);
                DB::rollBack();
                Log::info('Redirecting to salary index with error: Voucher must be EC approved');
                return redirect()->route('expenditure-control.salary.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be EC approved first. Current status: {$voucher->status}");
            }
            Log::info('CHECK 2 PASSED: Voucher status is ec_approved ✅');

            // All checks passed
            Log::info('ALL VALIDATION CHECKS PASSED ✅');
            Log::info('Proceeding with forwarding to Inspectorate...');

            // Get the current maximum approval step
            Log::info('Getting current approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $step = $maxStep + 1;
            Log::info('Forward step calculated:', [
                'max_step' => $maxStep,
                'forward_step' => $step
            ]);

            // Create forward to Inspectorate record
            Log::info('Creating forward to Inspectorate record...');
            $forward = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_EC,
                'approval_step' => $step,
                'approval_level' => $step,
                'action' => VoucherApproval::ACTION_FORWARDED,
                'status' => VoucherApproval::STATUS_FORWARDED,
                'comment' => $request->input('comment', 'Forwarded to Inspectorate for review'),
                'action_at' => now(),
            ]);
            Log::info('Forward record created:', [
                'forward_id' => $forward->id,
                'voucher_id' => $voucher->id
            ]);

            // Update voucher status to inspectorate_pending
            Log::info('Updating voucher status to inspectorate_pending...');
            $updateData = [
                'status' => 'inspectorate_pending',
                'forwarded_to_inspectorate_at' => now(),
                'forwarded_to_inspectorate_by' => auth()->id(),
            ];
            Log::info('Update data:', $updateData);

            $updated = $voucher->update($updateData);
            
            if (!$updated) {
                Log::error('Voucher update failed!', [
                    'voucher_id' => $voucher->id,
                    'update_data' => $updateData
                ]);
                throw new \Exception('Failed to update voucher record');
            }
            
            Log::info('Voucher updated successfully:', [
                'voucher_id' => $voucher->id,
                'new_status' => 'inspectorate_pending',
                'forwarded_by' => auth()->id(),
                'forwarded_at' => now()->toDateTimeString()
            ]);

            DB::commit();
            
            Log::info('========================================');
            Log::info('FORWARD TO INSPECTORATE - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            Log::info('Salary voucher forwarded to Inspectorate:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'forwarded_by' => auth()->user()?->name,
                'forwarded_at' => now()->toDateTimeString()
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('success', "Salary voucher {$voucher->voucher_number} forwarded to Inspectorate.");
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('FORWARD TO INSPECTORATE - FAILED');
            Log::error('========================================');
            Log::error('Forward to Inspectorate Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'error_code' => $e->getCode(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.salary.index')
                ->with('error', 'Failed to forward to Inspectorate: ' . $e->getMessage());
        }
    }

    /**
     * Search salary vouchers (API endpoint for AJAX calls)
     */
    public function searchSalaryVouchers(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 15);
            $search = $request->input('search', '');
            $salaryType = $request->input('voucher_type', '');
            $statusFilter = $request->input('status', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'assignedTo'])
                ->where('voucher_type', 'salary')
                ->whereIn('status', ['forwarded', 'ec_approved', 'inspectorate_pending'])
                ->orderBy('created_at', 'desc');
            
            // Apply salary type filter
            if ($salaryType) {
                $query->where('voucher_type', $salaryType);
            }
            
            // Apply status filter
            if ($statusFilter) {
                $query->where('status', $statusFilter);
            }
            
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
            
            // Transform data
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
                    // 'voucher_type' => $voucher->voucher_type,
                    'mda' => $voucher->mda ? [
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'tag' => $voucher->bankActivity->tag,
                    ] : null,
                    'assigned_to' => $voucher->assignedTo ? [
                        'id' => $voucher->assignedTo->id,
                        'name' => $voucher->assignedTo->name,
                    ] : null,
                ];
            });
            
            // Get statistics
            $stats = [
                'pending_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'forwarded')
                    ->count(),
                'ec_approved_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'ec_approved')
                    ->count(),
                'forwarded_to_inspectorate' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'inspectorate_pending')
                    ->count(),
                'total_salary' => Voucher::where('voucher_type', 'salary')->count(),
            ];
            
            // Get users for assignment
            $users = User::where('role', 'staff')->orWhere('role', 'admin')->get(['id', 'name', 'email']);
            
            return response()->json([
                'success' => true,
                'vouchers' => $transformedVouchers,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ],
                'stats' => $stats,
                'users' => $users,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Salary Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'vouchers' => [],
                'paginator' => [
                    'total' => 0,
                    'per_page' => 15,
                    'current_page' => 1,
                    'last_page' => 1,
                ],
                'stats' => [
                    'pending_count' => 0,
                    'ec_approved_count' => 0,
                    'forwarded_to_inspectorate' => 0,
                    'total_salary' => 0,
                ],
                'users' => [],
            ]);
        }
    }

    /**
     * Get salary statistics (API endpoint for AJAX calls)
     */
    public function salaryStats()
    {
        try {
            $stats = [
                'pending_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'forwarded')
                    ->count(),
                'ec_approved_count' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'ec_approved')
                    ->count(),
                'forwarded_to_inspectorate' => Voucher::where('voucher_type', 'salary')
                    ->where('status', 'inspectorate_pending')
                    ->count(),
                'total_salary' => Voucher::where('voucher_type', 'salary')->count(),
            ];
            
            return response()->json($stats);
            
        } catch (\Exception $e) {
            Log::error('Salary Stats Error: ' . $e->getMessage());
            return response()->json([
                'pending_count' => 0,
                'ec_approved_count' => 0,
                'forwarded_to_inspectorate' => 0,
                'total_salary' => 0,
            ]);
        }
    }

    /**
     * Get the default destination based on voucher type
     * Flow: DFA -> IA -> FA -> EC -> [DESTINATION]
     */
    private function getDefaultDestination(Voucher $voucher)
    {
        $voucherType = strtolower($voucher->voucher_type);
        
        // Define the flow for each voucher type
        $flowMap = [
            // Capital and Recurrent: IA -> EC -> AG -> MAS
            'capital' => 'ag',
            'recurrent' => 'ag',
            
            // Prepayment: IA -> EC -> MAS
            'prepayment' => 'mas',
            
            // Pension and Salary: IA -> EC -> Inspectorate -> TCO
            'pension' => 'inspectorate',
            'salary' => 'inspectorate',
            
            // Gratuity: IA -> EC -> AG -> TCO
            'gratuity' => 'ag',
            
            // Overhead: IA -> EC -> MAS
            'overhead' => 'mas',
        ];
        
        // Return the mapped destination or default to AG
        return $flowMap[$voucherType] ?? 'ag';
    }

    /**
     * Get the full workflow for a voucher type
     */
    private function getVoucherWorkflow(Voucher $voucher)
    {
        $voucherType = strtolower($voucher->voucher_type);
        
        $workflows = [
            'capital' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'AG', 'MAS'],
                'destinations' => ['ag', 'mas'],
                'final' => 'MAS',
                'description' => 'Capital Expenditure'
            ],
            'recurrent' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'AG', 'MAS'],
                'destinations' => ['ag', 'mas'],
                'final' => 'MAS',
                'description' => 'Recurrent Expenditure'
            ],
            'prepayment' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'MAS'],
                'destinations' => ['mas'],
                'final' => 'MAS',
                'description' => 'Prepayment'
            ],
            'pension' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'Inspectorate', 'TCO'],
                'destinations' => ['inspectorate', 'tco'],
                'final' => 'TCO',
                'description' => 'Pension Payment'
            ],
            'salary' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'Inspectorate', 'TCO'],
                'destinations' => ['inspectorate', 'tco'],
                'final' => 'TCO',
                'description' => 'Salary Payment'
            ],
            'gratuity' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'AG', 'TCO'],
                'destinations' => ['ag', 'tco'],
                'final' => 'TCO',
                'description' => 'Gratuity Payment'
            ],
            'overhead' => [
                'steps' => ['DFA', 'IA', 'FA', 'EC', 'MAS'],
                'destinations' => ['mas'],
                'final' => 'MAS',
                'description' => 'Overhead Expenditure'
            ],
        ];
        
        return $workflows[$voucherType] ?? [
            'steps' => ['DFA', 'IA', 'FA', 'EC', 'AG', 'MAS'],
            'destinations' => ['ag', 'mas'],
            'final' => 'MAS',
            'description' => 'Unknown'
        ];
    }

    /**
     * Get the next destination options based on voucher type
     * Used for the frontend dropdown
     */
    private function getAvailableDestinations(Voucher $voucher)
    {
        $voucherType = strtolower($voucher->voucher_type);
        
        $destinationOptions = [
            'capital' => [
                ['value' => 'ag', 'label' => 'Accountant General (AG)', 'icon' => 'pi pi-user'],
                ['value' => 'mas', 'label' => 'Management Account Section (MAS)', 'icon' => 'pi pi-money-bill']
            ],
            'recurrent' => [
                ['value' => 'ag', 'label' => 'Accountant General (AG)', 'icon' => 'pi pi-user'],
                ['value' => 'mas', 'label' => 'Management Account Section (MAS)', 'icon' => 'pi pi-money-bill']
            ],
            'prepayment' => [
                ['value' => 'mas', 'label' => 'Management Account Section (MAS)', 'icon' => 'pi pi-money-bill']
            ],
            'pension' => [
                ['value' => 'inspectorate', 'label' => 'Inspectorate', 'icon' => 'pi pi-search'],
                ['value' => 'tco', 'label' => 'Treasury Cash Office (TCO)', 'icon' => 'pi pi-building']
            ],
            'salary' => [
                ['value' => 'inspectorate', 'label' => 'Inspectorate', 'icon' => 'pi pi-search'],
                ['value' => 'tco', 'label' => 'Treasury Cash Office (TCO)', 'icon' => 'pi pi-building']
            ],
            'gratuity' => [
                ['value' => 'ag', 'label' => 'Accountant General (AG)', 'icon' => 'pi pi-user'],
                ['value' => 'tco', 'label' => 'Treasury Cash Office (TCO)', 'icon' => 'pi pi-building']
            ],
            'overhead' => [
                ['value' => 'mas', 'label' => 'Management Account Section (MAS)', 'icon' => 'pi pi-money-bill']
            ],
        ];
        
        return $destinationOptions[$voucherType] ?? [
            ['value' => 'ag', 'label' => 'Accountant General (AG)', 'icon' => 'pi pi-user'],
            ['value' => 'mas', 'label' => 'Management Account Section (MAS)', 'icon' => 'pi pi-money-bill']
        ];
    }

    /**
     * Get the final destination for a voucher type
     */
    private function getFinalDestination(Voucher $voucher)
    {
        $voucherType = strtolower($voucher->voucher_type);
        
        $finalDestinations = [
            'capital' => 'MAS',
            'recurrent' => 'MAS',
            'prepayment' => 'MAS',
            'pension' => 'TCO',
            'salary' => 'TCO',
            'gratuity' => 'TCO',
            'overhead' => 'MAS',
        ];
        
        return $finalDestinations[$voucherType] ?? 'MAS';
    }

    /**
     * Forward voucher to a specific destination (from modal selection)
     * If no destination is selected, uses the default flow based on voucher type
     */
    public function forward(Voucher $voucher, Request $request)
    {
        Log::info('========================================');
        Log::info('EXPENDITURE CONTROL FORWARD - STARTED');
        Log::info('========================================');
        Log::info('Forward Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'current_status' => $voucher->status,
            'voucher_type' => $voucher->voucher_type,
            'destination' => $request->input('destination'),
            'comment' => $request->input('comment'),
            'user_id' => auth()->id(),
            'user_name' => auth()->user()?->name,
        ]);

        DB::beginTransaction();
        
        try {
            // CHECK 1: Validate voucher status
            Log::info('CHECK 1: Validating voucher status...');
            if ($voucher->status !== 'forwarded') {
                Log::warning('CHECK 1 FAILED: Voucher not forwarded', [
                    'current_status' => $voucher->status,
                    'expected_status' => 'forwarded'
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be forwarded by Final Accounts first.");
            }
            Log::info('CHECK 1 PASSED: Voucher status is forwarded ✅');

            // CHECK 2: Validate is_final_accounts
            Log::info('CHECK 2: Validating is_final_accounts...');
            if (!$voucher->is_final_accounts) {
                Log::warning('CHECK 2 FAILED: Voucher not processed by FA', [
                    'is_final_accounts' => $voucher->is_final_accounts
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', "Voucher {$voucher->voucher_number} must be processed by Final Accounts first.");
            }
            Log::info('CHECK 2 PASSED: is_final_accounts is 1 ✅');

            // Get destination from request or determine default
            $destination = $request->input('destination');
            $comment = $request->input('comment', '');
            
            // If no destination selected, use default flow based on voucher type
            if (empty($destination)) {
                Log::info('No destination selected, using default flow based on voucher type...');
                $destination = $this->getDefaultDestination($voucher);
                Log::info('Default destination determined:', [
                    'voucher_type' => $voucher->voucher_type,
                    'destination' => $destination
                ]);
            }

            // Map destination to role and display name
            $destinationMap = [
                'ag' => [
                    'role' => VoucherApproval::ROLE_AG,
                    'display' => 'Accountant General (AG)',
                    'status' => 'ec_approved'
                ],
                'mas' => [
                    'role' => VoucherApproval::ROLE_MAS,
                    'display' => 'Management Account Section (MAS)',
                    'status' => 'ec_approved'
                ],
                'inspectorate' => [
                    'role' => VoucherApproval::ROLE_INSPECTORATE,
                    'display' => 'Inspectorate',
                    'status' => 'ec_approved'
                ],
                'tco' => [
                    'role' => VoucherApproval::ROLE_TCO,
                    'display' => 'Treasury Cash Office (TCO)',
                    'status' => 'ec_approved'
                ],
            ];

            // Validate destination exists
            if (!isset($destinationMap[$destination])) {
                Log::warning('CHECK 3 FAILED: Invalid destination', ['destination' => $destination]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', 'Invalid destination selected.');
            }

            // Validate destination is allowed for this voucher type
            $availableDestinations = $this->getAvailableDestinations($voucher);
            $allowedDestinations = array_column($availableDestinations, 'value');
            
            if (!in_array($destination, $allowedDestinations)) {
                Log::warning('CHECK 4 FAILED: Destination not allowed for this voucher type', [
                    'voucher_type' => $voucher->voucher_type,
                    'destination' => $destination,
                    'allowed_destinations' => $allowedDestinations
                ]);
                DB::rollBack();
                return redirect()->route('expenditure-control.index')
                    ->with('error', "Destination '{$destinationMap[$destination]['display']}' is not allowed for {$voucher->voucher_type} vouchers.");
            }

            $nextRole = $destinationMap[$destination]['role'];
            $nextRoleDisplay = $destinationMap[$destination]['display'];
            $nextStatus = $destinationMap[$destination]['status'];

            Log::info('Destination mapped successfully:', [
                'voucher_type' => $voucher->voucher_type,
                'destination' => $destination,
                'next_role' => $nextRole,
                'next_role_display' => $nextRoleDisplay,
                'next_status' => $nextStatus
            ]);

            // Get the current maximum approval step
            Log::info('Getting current approval step...');
            $maxStep = $voucher->approvals()->max('approval_step') ?? 0;
            $ecStep = $maxStep + 1;
            $nextStep = $ecStep + 1;
            Log::info('Approval steps calculated:', [
                'max_step' => $maxStep,
                'ec_step' => $ecStep,
                'next_step' => $nextStep
            ]);

            // Create EC approval record
            Log::info('Creating EC approval record...');
            $ecApproval = VoucherApproval::create([
                'voucher_id' => $voucher->id,
                'user_id' => auth()->id(),
                'approval_role' => VoucherApproval::ROLE_EC,
                'approval_step' => $ecStep,
                'approval_level' => $ecStep,
                'action' => VoucherApproval::ACTION_APPROVED,
                'status' => VoucherApproval::STATUS_APPROVED,
                'comment' => $comment ?: 'Approved by Expenditure Control',
                'action_at' => now(),
                'approved_at' => now(),
            ]);
            Log::info('EC approval record created:', ['approval_id' => $ecApproval->id]);

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
                'comment' => "Forwarded to {$nextRoleDisplay} for further processing" . ($comment ? ": {$comment}" : ''),
                'action_at' => now(),
            ]);
            Log::info('Forward record created:', ['approval_id' => $forwardApproval->id]);

            // Update voucher status
            Log::info('Updating voucher status...');
            $updateData = [
                'status' => $nextStatus,
                'ec_approved_by' => auth()->id(),
                'ec_approved_at' => now(),
            ];
            
            // Add specific fields based on destination
            if ($destination === 'ag') {
                $updateData['ag_approved_at'] = null;
                $updateData['ag_approved_by'] = null;
            } elseif ($destination === 'mas') {
                $updateData['mas_approved_at'] = null;
                $updateData['mas_approved_by'] = null;
            } elseif ($destination === 'inspectorate') {
                $updateData['forwarded_to_inspectorate_at'] = now();
                $updateData['forwarded_to_inspectorate_by'] = auth()->id();
            } elseif ($destination === 'tco') {
                $updateData['tco_approved_at'] = null;
                $updateData['tco_approved_by'] = null;
            }
            
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

            // Log activity
            $this->activityLogger->log(
                "Expenditure Control forwarded {$voucher->voucher_type} voucher {$voucher->voucher_number} to {$nextRoleDisplay}",
                [
                    'voucher_id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'forwarded_to' => $nextRoleDisplay,
                    'destination' => $destination,
                    'ec_step' => $ecStep,
                    'next_step' => $nextStep,
                    'comment' => $comment,
                    'approved_by' => auth()->id(),
                    'approved_by_name' => auth()->user()?->name,
                ],
                'voucher'
            );
            
            DB::commit();
            
            Log::info('========================================');
            Log::info('EXPENDITURE CONTROL FORWARD - COMPLETED SUCCESSFULLY');
            Log::info('========================================');
            
            return redirect()->route('expenditure-control.index')
                ->with('success', "{$voucher->voucher_type} voucher {$voucher->voucher_number} forwarded to {$nextRoleDisplay} successfully.");
                
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('========================================');
            Log::error('EXPENDITURE CONTROL FORWARD - FAILED');
            Log::error('========================================');
            Log::error('Forward Exception:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'error_message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return redirect()->route('expenditure-control.index')
                ->with('error', 'Failed to forward voucher: ' . $e->getMessage());
        }
    }

    /**
     * Display assigned vouchers for staff (non-admin users)
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
            
            // Build query - only vouchers assigned to current user
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'approvals.user'])
                ->where('assigned_to_user_id', $userId)
                ->where('is_final_accounts', 1)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'forwarded');
            } elseif ($tab === 'approved') {
                $query->where('status', 'ec_approved');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'sent_back');
            } elseif ($tab === 'forwarded') {
                $query->where('status', 'forwarded')->whereNotNull('assigned_to_user_id');
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
            
            $vouchers = $query->paginate($perPage);
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->through(function ($voucher) {
                // Get approval records for display
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
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
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
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
                    // =============================================
                    // ADD APPROVALS TO THE RETURN ARRAY
                    // =============================================
                    'approvals' => $approvals,
                    'available_destinations' => $this->getAvailableDestinations($voucher),
                    'workflow' => $this->getVoucherWorkflow($voucher),
                ];
            });
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('is_final_accounts', 1)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'sent_back')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'forwarded_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'forwarded')
                    ->whereNotNull('assigned_to_user_id')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->where('is_final_accounts', 1)
                    ->sum('total_amount'),
            ];
            
            return Inertia::render('admin/expenditureControl/assigned', [
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
            Log::error('Assigned Index Error: ' . $e->getMessage());
            return Inertia::render('admin/expenditureControl/assigned', [
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
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'approvals.user'])
                ->where('assigned_to_user_id', $userId)
                ->where('is_final_accounts', 1)
                ->orderBy('created_at', 'desc');
            
            // Apply tab filter
            if ($tab === 'pending') {
                $query->where('status', 'forwarded');
            } elseif ($tab === 'approved') {
                $query->where('status', 'ec_approved');
            } elseif ($tab === 'rejected') {
                $query->where('status', 'sent_back');
            } elseif ($tab === 'forwarded') {
                $query->where('status', 'forwarded')->whereNotNull('assigned_to_user_id');
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
            
            // Transform the data for the frontend
            $transformedVouchers = $vouchers->map(function ($voucher) {
                // Get approval records for display
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                
                // Determine payment status
                $paymentStatus = 'unknown';
                if ($voucher->status === 'closed' && $voucher->mas_approved_at) {
                    $paymentStatus = 'paid';
                } elseif ($voucher->status === 'ag_approved') {
                    $paymentStatus = 'awaiting_mas';
                } elseif ($voucher->status === 'ec_approved') {
                    $paymentStatus = 'awaiting_ag';
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
                    'narration' => $voucher->narration,
                    'total_amount' => (float) $voucher->total_amount,
                    'payee_name' => $voucher->payee_name,
                    'status' => $voucher->status,
                    'voucher_type' => $voucher->voucher_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'payment_status' => $paymentStatus,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
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
                    // =============================================
                    // ADD APPROVALS TO THE RETURN ARRAY
                    // =============================================
                    'approvals' => $approvals,
                ];
            })->values()->toArray();
            
            // Get statistics specific to assigned vouchers
            $stats = [
                'total_assigned' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('is_final_accounts', 1)
                    ->count(),
                'pending_review' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'forwarded')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'approved_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'ec_approved')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'rejected_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'sent_back')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'forwarded_count' => Voucher::where('assigned_to_user_id', $userId)
                    ->where('status', 'forwarded')
                    ->whereNotNull('assigned_to_user_id')
                    ->where('is_final_accounts', 1)
                    ->count(),
                'total_amount' => (float) Voucher::where('assigned_to_user_id', $userId)
                    ->where('is_final_accounts', 1)
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
            Log::error('Assigned Search Error: ' . $e->getMessage(), [
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
     * Display the recurrent expenditure ledger
     * Only shows vouchers that are paid/closed
     */
    public function ledger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            
            // Build the query - ONLY recurrent vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'recurrent')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
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
            
            // Get vouchers ordered by date
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Calculate running balance
            $runningBalance = 0;
            $entries = [];
            $totalPayments = 0;
            $economyCodeStats = [];
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount; // For payments, balance increases
                $totalPayments += $amount;
                
                // Get approval info
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
                // $entries[] = [
                //     'id' => $voucher->id,
                //     'transaction_date' => $voucher->voucher_date?->toDateString(),
                //     'voucher_number' => $voucher->voucher_number,
                //     'description' => $this->getLedgerDescription($voucher),
                //     'payee_name' => $voucher->payee_name,
                //     'amount' => $amount,
                //     'running_balance' => $runningBalance,
                //     'mda' => $voucher->mda ? [
                //         'id' => $voucher->mda->id,
                //         'name' => $voucher->mda->name,
                //         'code' => $voucher->mda->code,
                //     ] : null,
                //     'bank_activity' => $voucher->bankActivity ? [
                //         'id' => $voucher->bankActivity->id,
                //         'bank_name' => $voucher->bankActivity->bank_name,
                //         'account_number' => $voucher->bankActivity->account_number,
                //     ] : null,
                //     'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                //     'payment_reference' => $voucher->payment_reference,
                //     'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                //     'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                //     'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                //     'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                //     'items' => $voucher->items->map(function ($item) {
                //         return [
                //             'id' => $item->id,
                //             'description' => $item->description,
                //             'quantity' => (float) $item->quantity,
                //             'unit_price' => (float) $item->unit_price,
                //             'sub_total' => (float) $item->sub_total,
                //             'programme_code' => $item->programme_code,
                //             'programme_name' => $item->programme_name,
                //             'economy_code_item' => $item->economyCodeItem ? [
                //                 'id' => $item->economyCodeItem->id,
                //                 'code' => $item->economyCodeItem->code,
                //                 'name' => $item->economyCodeItem->name,
                //             ] : null,
                //         ];
                //     }),
                // ];
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'MAS', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
                
                // Calculate economy code statistics
                foreach ($voucher->items as $item) {
                    $code = $item->economyCodeItem?->code ?? $item->programme_code ?? 'Other';
                    $name = $item->economyCodeItem?->name ?? $item->programme_name ?? 'Other';
                    $key = $code . ' - ' . $name;
                    
                    if (!isset($economyCodeStats[$key])) {
                        $economyCodeStats[$key] = [
                            'code' => $code,
                            'name' => $name,
                            'total' => 0,
                            'count' => 0,
                        ];
                    }
                    
                    $economyCodeStats[$key]['total'] += (float) $item->sub_total;
                    $economyCodeStats[$key]['count']++;
                }
            }
            
            // Get opening balance (total of all previous paid recurrent vouchers)
            $openingBalance = $this->getRecurrentOpeningBalance($month, $year, $mdaId);
            
            // Get MDAs for filter
            $mdas = Mda::orderBy('name')->get(['id', 'name', 'code']);
            
            // Get summary statistics
            $summary = [
                'opening_balance' => $openingBalance,
                'total_payments' => $totalPayments,
                'closing_balance' => $openingBalance + $totalPayments,
                'total_vouchers' => $vouchers->count(),
            ];
            
            return Inertia::render('admin/expenditureControl/recurrent-ledger', [
                'entries' => $entries,
                'summary' => $summary,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'month' => $month,
                'mdas' => $mdas,
                'filters' => [
                    'mda_id' => $mdaId,
                    'search' => $search,
                ],
                'economyCodeStats' => array_values($economyCodeStats),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Recurrent Ledger Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('admin/expenditureControl/recurrent-ledger', [
                'entries' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_payments' => 0,
                    'closing_balance' => 0,
                    'total_vouchers' => 0,
                ],
                'month_name' => Carbon::now()->format('F'),
                'year' => now()->year,
                'month' => now()->month,
                'mdas' => [],
                'filters' => [],
                'economyCodeStats' => [],
            ]);
        }
    }

    /**
     * Get description for the ledger entry
     */
    private function getLedgerDescription($voucher)
    {
        $parts = [];
        
        if ($voucher->payee_name) {
            $parts[] = $voucher->payee_name;
        }
        
        if ($voucher->mda) {
            $parts[] = $voucher->mda->name;
        }
        
        if ($voucher->narration) {
            $parts[] = $voucher->narration;
        }
        
        return implode(' - ', $parts) ?: $voucher->voucher_number;
    }

    /**
     * Get opening balance for recurrent expenditure ledger
     * Sum of all paid recurrent vouchers before the selected month
     */
    private function getRecurrentOpeningBalance($month, $year, $mdaId = null)
    {
        $query = Voucher::where('voucher_type', 'recurrent')
            ->where('status', 'closed')
            ->whereNotNull('mas_approved_at')
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth('voucher_date', '<', $month)
                ->whereYear('voucher_date', '<=', $year);
            });
        
        if ($mdaId) {
            $query->where('mda_id', $mdaId);
        }
        
        return (float) $query->sum('total_amount');
    }

    /**
     * Search ledger entries (API endpoint for AJAX calls)
     */
    public function searchLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            
            // Build the query - ONLY recurrent vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'recurrent')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
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
            
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Calculate running balance
            $runningBalance = $this->getRecurrentOpeningBalance($month, $year, $mdaId);
            $entries = [];
            $totalPayments = 0;
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // $entries[] = [
                //     'id' => $voucher->id,
                //     'transaction_date' => $voucher->voucher_date?->toDateString(),
                //     'voucher_number' => $voucher->voucher_number,
                //     'description' => $this->getLedgerDescription($voucher),
                //     'payee_name' => $voucher->payee_name,
                //     'amount' => $amount,
                //     'running_balance' => $runningBalance,
                //     'mda' => $voucher->mda ? [
                //         'id' => $voucher->mda->id,
                //         'name' => $voucher->mda->name,
                //         'code' => $voucher->mda->code,
                //     ] : null,
                //     'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                //     'payment_reference' => $voucher->payment_reference,
                // ];
                // In the capitalLedger method, update the entries array to include:
                // dd($entries[]);
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'MAS', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
            }
            
            $summary = [
                'opening_balance' => $this->getRecurrentOpeningBalance($month, $year, $mdaId),
                'total_payments' => $totalPayments,
                'closing_balance' => $this->getRecurrentOpeningBalance($month, $year, $mdaId) + $totalPayments,
                'total_vouchers' => $vouchers->total(),
            ];
            
            return response()->json([
                'success' => true,
                'entries' => $entries,
                'summary' => $summary,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Ledger Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export ledger to Excel
     */
    public function exportLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'recurrent')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')->get();
            
            // Prepare data for export
            $exportData = [];
            $runningBalance = $this->getRecurrentOpeningBalance($month, $year, $mdaId);
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                
                $exportData[] = [
                    'Date' => $voucher->voucher_date?->toDateString(),
                    'Voucher #' => $voucher->voucher_number,
                    'MDA' => $voucher->mda?->name,
                    'Payee' => $voucher->payee_name,
                    'Description' => $this->getLedgerDescription($voucher),
                    'Amount' => $amount,
                    'Running Balance' => $runningBalance,
                    'Payment Date' => $voucher->mas_approved_at?->toDateString(),
                    'Payment Ref' => $voucher->payment_reference,
                ];
            }
            
            // Generate Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $headers = array_keys($exportData[0] ?? []);
            foreach ($headers as $index => $header) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
            }
            
            // Data
            $row = 2;
            foreach ($exportData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto size columns
            foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = "Recurrent_Ledger_{$month}_{$year}.xlsx";
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Ledger Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to export ledger'], 500);
        }
    }

    /**
     * Display the capital expenditure ledger
     * Only shows vouchers that are paid/closed for capital expenditure
     */
    public function capitalLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            
            // Build the query - ONLY capital vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'capital')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
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
            
            // Get vouchers ordered by date
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Calculate running balance
            $runningBalance = 0;
            $entries = [];
            $totalPayments = 0;
            $economyCodeStats = [];
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // Get approval info
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                
                // $entries[] = [
                //     'id' => $voucher->id,
                //     'transaction_date' => $voucher->voucher_date?->toDateString(),
                //     'voucher_number' => $voucher->voucher_number,
                //     'description' => $this->getLedgerDescription($voucher),
                //     'payee_name' => $voucher->payee_name,
                //     'amount' => $amount,
                //     'running_balance' => $runningBalance,
                //     'mda' => $voucher->mda ? [
                //         'id' => $voucher->mda->id,
                //         'name' => $voucher->mda->name,
                //         'code' => $voucher->mda->code,
                //     ] : null,
                //     'bank_activity' => $voucher->bankActivity ? [
                //         'id' => $voucher->bankActivity->id,
                //         'bank_name' => $voucher->bankActivity->bank_name,
                //         'account_number' => $voucher->bankActivity->account_number,
                //     ] : null,
                //     'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                //     'payment_reference' => $voucher->payment_reference,
                //     'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                //     'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                //     'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                //     'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                //     'items' => $voucher->items->map(function ($item) {
                //         return [
                //             'id' => $item->id,
                //             'description' => $item->description,
                //             'quantity' => (float) $item->quantity,
                //             'unit_price' => (float) $item->unit_price,
                //             'sub_total' => (float) $item->sub_total,
                //             'programme_code' => $item->programme_code,
                //             'programme_name' => $item->programme_name,
                //             'economy_code_item' => $item->economyCodeItem ? [
                //                 'id' => $item->economyCodeItem->id,
                //                 'code' => $item->economyCodeItem->code,
                //                 'name' => $item->economyCodeItem->name,
                //             ] : null,
                //         ];
                //     }),
                // ];
                // In the capitalLedger method, update the entries array to include:
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'MAS', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
                
                // Calculate economy code statistics
                foreach ($voucher->items as $item) {
                    $code = $item->economyCodeItem?->code ?? $item->programme_code ?? 'Other';
                    $name = $item->economyCodeItem?->name ?? $item->programme_name ?? 'Other';
                    $key = $code . ' - ' . $name;
                    
                    if (!isset($economyCodeStats[$key])) {
                        $economyCodeStats[$key] = [
                            'code' => $code,
                            'name' => $name,
                            'total' => 0,
                            'count' => 0,
                        ];
                    }
                    
                    $economyCodeStats[$key]['total'] += (float) $item->sub_total;
                    $economyCodeStats[$key]['count']++;
                }
            }
            
            // Get opening balance (total of all previous paid capital vouchers)
            $openingBalance = $this->getCapitalOpeningBalance($month, $year, $mdaId);
            
            // Get MDAs for filter
            $mdas = Mda::orderBy('name')->get(['id', 'name', 'code']);
            
            // Get summary statistics
            $summary = [
                'opening_balance' => $openingBalance,
                'total_payments' => $totalPayments,
                'closing_balance' => $openingBalance + $totalPayments,
                'total_vouchers' => $vouchers->count(),
            ];
            
            return Inertia::render('admin/expenditureControl/capital-ledger', [
                'entries' => $entries,
                'summary' => $summary,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'month' => $month,
                'mdas' => $mdas,
                'filters' => [
                    'mda_id' => $mdaId,
                    'search' => $search,
                ],
                'economyCodeStats' => array_values($economyCodeStats),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Capital Ledger Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('admin/expenditureControl/capital-ledger', [
                'entries' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_payments' => 0,
                    'closing_balance' => 0,
                    'total_vouchers' => 0,
                ],
                'month_name' => Carbon::now()->format('F'),
                'year' => now()->year,
                'month' => now()->month,
                'mdas' => [],
                'filters' => [],
                'economyCodeStats' => [],
            ]);
        }
    }

    /**
     * Get opening balance for capital expenditure ledger
     * Sum of all paid capital vouchers before the selected month
     */
    private function getCapitalOpeningBalance($month, $year, $mdaId = null)
    {
        $query = Voucher::where('voucher_type', 'capital')
            // ->whereNull('deleted_at')
            ->where('status', 'closed')
            ->whereNotNull('mas_approved_at')
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth('voucher_date', '<', $month)
                ->whereYear('voucher_date', '<=', $year);
            });
        
        if ($mdaId) {
            $query->where('mda_id', $mdaId);
        }
        
        return (float) $query->sum('total_amount');
    }

    /**
     * Search capital ledger entries (API endpoint for AJAX calls)
     */
    public function searchCapitalLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            
            // Build the query - ONLY capital vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'capital')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
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
            
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Calculate running balance
            $runningBalance = $this->getCapitalOpeningBalance($month, $year, $mdaId);
            $entries = [];
            $totalPayments = 0;
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // $entries[] = [
                //     'id' => $voucher->id,
                //     'transaction_date' => $voucher->voucher_date?->toDateString(),
                //     'voucher_number' => $voucher->voucher_number,
                //     'description' => $this->getLedgerDescription($voucher),
                //     'payee_name' => $voucher->payee_name,
                //     'amount' => $amount,
                //     'running_balance' => $runningBalance,
                //     'mda' => $voucher->mda ? [
                //         'id' => $voucher->mda->id,
                //         'name' => $voucher->mda->name,
                //         'code' => $voucher->mda->code,
                //     ] : null,
                //     'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                //     'payment_reference' => $voucher->payment_reference,
                // ];
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'MAS', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'mas_approved_at' => $masApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
            }
            
            $summary = [
                'opening_balance' => $this->getCapitalOpeningBalance($month, $year, $mdaId),
                'total_payments' => $totalPayments,
                'closing_balance' => $this->getCapitalOpeningBalance($month, $year, $mdaId) + $totalPayments,
                'total_vouchers' => $vouchers->total(),
            ];
            
            return response()->json([
                'success' => true,
                'entries' => $entries,
                'summary' => $summary,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Capital Ledger Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export capital ledger to Excel
     */
    public function exportCapitalLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'capital')
                ->where('status', 'closed')
                ->whereNotNull('mas_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')->get();
            
            // Prepare data for export
            $exportData = [];
            $runningBalance = $this->getCapitalOpeningBalance($month, $year, $mdaId);
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                
                $exportData[] = [
                    'Date' => $voucher->voucher_date?->toDateString(),
                    'Voucher #' => $voucher->voucher_number,
                    'MDA' => $voucher->mda?->name,
                    'Payee' => $voucher->payee_name,
                    'Description' => $this->getLedgerDescription($voucher),
                    'Amount' => $amount,
                    'Running Balance' => $runningBalance,
                    'Payment Date' => $voucher->mas_approved_at?->toDateString(),
                    'Payment Ref' => $voucher->payment_reference,
                ];
            }
            
            // Generate Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $headers = array_keys($exportData[0] ?? []);
            foreach ($headers as $index => $header) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
            }
            
            // Data
            $row = 2;
            foreach ($exportData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto size columns
            foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = "Capital_Ledger_{$month}_{$year}.xlsx";
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Capital Ledger Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to export ledger'], 500);
        }
    }

    /**
     * Display the salary expenditure ledger
     * Only shows vouchers that are paid/closed for salary expenditure
     */
    public function salaryLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            
            // Build the query - ONLY salary vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'salary')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            // Apply search filter
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                        ->orWhere('narration', 'like', "%{$word}%")
                        ->orWhere('payee_name', 'like', "%{$word}%")
                        ->orWhere('voucher_type', 'like', "%{$word}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                            $mdaQuery->where('name', 'like', "%{$word}%");
                        });
                    });
                }
            }
            
            // Get vouchers ordered by date
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Calculate running balance
            $runningBalance = 0;
            $entries = [];
            $totalPayments = 0;
            $economyCodeStats = [];
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // Get approval info
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $agApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_AG)->first();
                $masApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_MAS)->first();
                $inspectorateApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();
                
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'TCO', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
                
                // Calculate economy code statistics
                foreach ($voucher->items as $item) {
                    $code = $item->economyCodeItem?->code ?? $item->programme_code ?? 'Other';
                    $name = $item->economyCodeItem?->name ?? $item->programme_name ?? 'Other';
                    $key = $code . ' - ' . $name;
                    
                    if (!isset($economyCodeStats[$key])) {
                        $economyCodeStats[$key] = [
                            'code' => $code,
                            'name' => $name,
                            'total' => 0,
                            'count' => 0,
                        ];
                    }
                    
                    $economyCodeStats[$key]['total'] += (float) $item->sub_total;
                    $economyCodeStats[$key]['count']++;
                }
            }
            
            // Get opening balance (total of all previous paid salary vouchers)
            $openingBalance = $this->getSalaryOpeningBalance($month, $year, $mdaId);
            
            // Get MDAs for filter
            $mdas = Mda::orderBy('name')->get(['id', 'name', 'code']);
            
            // Get summary statistics
            $summary = [
                'opening_balance' => $openingBalance,
                'total_payments' => $totalPayments,
                'closing_balance' => $openingBalance + $totalPayments,
                'total_vouchers' => $vouchers->count(),
            ];
            
            return Inertia::render('admin/expenditureControl/salary-ledger', [
                'entries' => $entries,
                'summary' => $summary,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'month' => $month,
                'mdas' => $mdas,
                'filters' => [
                    'mda_id' => $mdaId,
                    'search' => $search,
                ],
                'economyCodeStats' => array_values($economyCodeStats),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Salary Ledger Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('admin/expenditureControl/salary-ledger', [
                'entries' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_payments' => 0,
                    'closing_balance' => 0,
                    'total_vouchers' => 0,
                ],
                'month_name' => Carbon::now()->format('F'),
                'year' => now()->year,
                'month' => now()->month,
                'mdas' => [],
                'filters' => [],
                'economyCodeStats' => [],
            ]);
        }
    }

    /**
     * Get opening balance for salary expenditure ledger
     * Sum of all paid salary vouchers before the selected month
     */
    private function getSalaryOpeningBalance($month, $year, $mdaId = null)
    {
        $query = Voucher::where('voucher_type', 'salary')
            ->where('status', 'closed')
            ->whereNotNull('tco_approved_at')
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth('voucher_date', '<', $month)
                ->whereYear('voucher_date', '<=', $year);
            });
        
        if ($mdaId) {
            $query->where('mda_id', $mdaId);
        }
        
        return (float) $query->sum('total_amount');
    }

    /**
     * Search salary ledger entries (API endpoint for AJAX calls)
     */
    public function searchSalaryLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            
            // Build the query - ONLY salary vouchers that are paid/closed
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'salary')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            // Apply search filter
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                        ->orWhere('narration', 'like', "%{$word}%")
                        ->orWhere('payee_name', 'like', "%{$word}%")
                        ->orWhere('voucher_type', 'like', "%{$word}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                            $mdaQuery->where('name', 'like', "%{$word}%");
                        });
                    });
                }
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Calculate running balance
            $runningBalance = $this->getSalaryOpeningBalance($month, $year, $mdaId);
            $entries = [];
            $totalPayments = 0;
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // $entries[] = [
                //     'id' => $voucher->id,
                //     'transaction_date' => $voucher->voucher_date?->toDateString(),
                //     'voucher_number' => $voucher->voucher_number,
                //     'description' => $this->getLedgerDescription($voucher),
                //     'payee_name' => $voucher->payee_name,
                //     'amount' => $amount,
                //     'running_balance' => $runningBalance,
                //     'pay_point' => $voucher->pay_point ?? 'TCO',
                //     'schedule_id' => $voucher->schedule_id,
                //     'schedule_number' => $voucher->schedule?->schedule_number ?? null,
                //     'voucher_type' => $voucher->voucher_type,
                //     'mda' => $voucher->mda ? [
                //         'id' => $voucher->mda->id,
                //         'name' => $voucher->mda->name,
                //         'code' => $voucher->mda->code,
                //     ] : null,
                //     'payment_date' => $voucher->tco_approved_at?->toDateTimeString(),
                //     'payment_reference' => $voucher->payment_reference,
                // ];
                // In the capitalLedger method, update the entries array to include:
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'TCO', // Add pay point
                    'schedule_id' => $voucher->schedule_id, // Add schedule ID
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null, // Add schedule number
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->mas_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'ag_approved_at' => $agApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
            }
            
            $summary = [
                'opening_balance' => $this->getSalaryOpeningBalance($month, $year, $mdaId),
                'total_payments' => $totalPayments,
                'closing_balance' => $this->getSalaryOpeningBalance($month, $year, $mdaId) + $totalPayments,
                'total_vouchers' => $vouchers->total(),
            ];
            
            return response()->json([
                'success' => true,
                'entries' => $entries,
                'summary' => $summary,
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Salary Ledger Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export salary ledger to Excel
     */
    public function exportSalaryLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'salary')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')->get();
            
            // Prepare data for export
            $exportData = [];
            $runningBalance = $this->getSalaryOpeningBalance($month, $year, $mdaId);
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                
                $exportData[] = [
                    'Date' => $voucher->voucher_date?->toDateString(),
                    'Voucher #' => $voucher->voucher_number,
                    'MDA' => $voucher->mda?->name,
                    'Payee' => $voucher->payee_name,
                    'Salary Type' => $voucher->voucher_type,
                    'Description' => $this->getLedgerDescription($voucher),
                    'Amount' => $amount,
                    'Running Balance' => $runningBalance,
                    'Payment Date' => $voucher->tco_approved_at?->toDateString(),
                    'Payment Ref' => $voucher->payment_reference,
                ];
            }
            
            // Generate Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $headers = array_keys($exportData[0] ?? []);
            foreach ($headers as $index => $header) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
            }
            
            // Data
            $row = 2;
            foreach ($exportData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto size columns
            foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = "Salary_Ledger_{$month}_{$year}.xlsx";
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Salary Ledger Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to export ledger'], 500);
        }
    }

    /**
     * Display the pension expenditure ledger
     * Only shows vouchers that are paid/closed for pension expenditure
     */
    public function pensionLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            $pensionType = $request->input('pension_type', '');
            
            // Build the query - ONLY pension vouchers that are paid/closed (TCO approved)
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'pension')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            // Filter by Pension Type if provided
            if ($pensionType) {
                $query->where('pension_type', $pensionType);
            }
            
            // Apply search filter
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                        ->orWhere('narration', 'like', "%{$word}%")
                        ->orWhere('payee_name', 'like', "%{$word}%")
                        ->orWhere('voucher_type', 'like', "%{$word}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                            $mdaQuery->where('name', 'like', "%{$word}%");
                        });
                    });
                }
            }
            
            // Get vouchers ordered by date
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Calculate running balance
            $runningBalance = 0;
            $entries = [];
            $totalPayments = 0;
            $economyCodeStats = [];
            $pensionTypeStats = [];
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // Get approval info
                $faApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_FA)->first();
                $ecApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_EC)->first();
                $iApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_INSPECTORATE)->first();
                $tcoApproval = $voucher->approvals->where('approval_role', VoucherApproval::ROLE_TCO)->first();
                
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => $voucher->voucher_type,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'TCO',
                    'schedule_id' => $voucher->schedule_id,
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null,
                    'pension_type' => $voucher->pension_type ?? 'regular',
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'bank_activity' => $voucher->bankActivity ? [
                        'id' => $voucher->bankActivity->id,
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                    ] : null,
                    'payment_date' => $voucher->tco_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                    'final_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
                    'ec_approved_at' => $ecApproval?->approved_at?->toDateTimeString(),
                    'i_approved_at' => $iApproval?->approved_at?->toDateTimeString(),
                    'tco_approved_at' => $tcoApproval?->approved_at?->toDateTimeString(),
                    'items' => $voucher->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'description' => $item->description,
                            'quantity' => (float) $item->quantity,
                            'unit_price' => (float) $item->unit_price,
                            'sub_total' => (float) $item->sub_total,
                            'programme_code' => $item->programme_code,
                            'programme_name' => $item->programme_name,
                            'economy_code_item' => $item->economyCodeItem ? [
                                'id' => $item->economyCodeItem->id,
                                'code' => $item->economyCodeItem->code,
                                'name' => $item->economyCodeItem->name,
                            ] : null,
                        ];
                    }),
                ];
                
                // Calculate pension type statistics
                $type = $voucher->pension_type ?? 'regular';
                if (!isset($pensionTypeStats[$type])) {
                    $pensionTypeStats[$type] = [
                        'type' => $type,
                        'total' => 0,
                        'count' => 0,
                    ];
                }
                $pensionTypeStats[$type]['total'] += $amount;
                $pensionTypeStats[$type]['count']++;
                
                // Calculate economy code statistics
                foreach ($voucher->items as $item) {
                    $code = $item->economyCodeItem?->code ?? $item->programme_code ?? 'Other';
                    $name = $item->economyCodeItem?->name ?? $item->programme_name ?? 'Other';
                    $key = $code . ' - ' . $name;
                    
                    if (!isset($economyCodeStats[$key])) {
                        $economyCodeStats[$key] = [
                            'code' => $code,
                            'name' => $name,
                            'total' => 0,
                            'count' => 0,
                        ];
                    }
                    
                    $economyCodeStats[$key]['total'] += (float) $item->sub_total;
                    $economyCodeStats[$key]['count']++;
                }
            }
            
            // Get opening balance (total of all previous paid pension vouchers)
            $openingBalance = $this->getPensionOpeningBalance($month, $year, $mdaId, $pensionType);
            
            // Get MDAs for filter
            $mdas = Mda::orderBy('name')->get(['id', 'name', 'code']);
            
            // Get pension types for filter
            $pensionTypes = [
                ['label' => 'All Types', 'value' => ''],
                ['label' => 'Regular', 'value' => 'regular'],
                ['label' => 'Contributory', 'value' => 'contributory'],
                ['label' => 'Arrears', 'value' => 'arrears'],
                ['label' => 'Gratuity', 'value' => 'gratuity'],
                ['label' => 'Death Benefit', 'value' => 'death_benefit'],
                ['label' => 'Other', 'value' => 'other'],
            ];
            
            // Get summary statistics
            $summary = [
                'opening_balance' => $openingBalance,
                'total_payments' => $totalPayments,
                'closing_balance' => $openingBalance + $totalPayments,
                'total_vouchers' => $vouchers->count(),
            ];
            
            return Inertia::render('admin/expenditureControl/pension-ledger', [
                'entries' => $entries,
                'summary' => $summary,
                'month_name' => Carbon::createFromDate($year, $month, 1)->format('F'),
                'year' => $year,
                'month' => $month,
                'mdas' => $mdas,
                'pensionTypes' => $pensionTypes,
                'filters' => [
                    'mda_id' => $mdaId,
                    'search' => $search,
                    'pension_type' => $pensionType,
                ],
                'economyCodeStats' => array_values($economyCodeStats),
                'pensionTypeStats' => array_values($pensionTypeStats),
            ]);
            
        } catch (\Exception $e) {
            Log::error('Pension Ledger Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return Inertia::render('admin/expenditureControl/pension-ledger', [
                'entries' => [],
                'summary' => [
                    'opening_balance' => 0,
                    'total_payments' => 0,
                    'closing_balance' => 0,
                    'total_vouchers' => 0,
                ],
                'month_name' => Carbon::now()->format('F'),
                'year' => now()->year,
                'month' => now()->month,
                'mdas' => [],
                'pensionTypes' => [],
                'filters' => [],
                'economyCodeStats' => [],
                'pensionTypeStats' => [],
            ]);
        }
    }

    /**
     * Get opening balance for pension expenditure ledger
     * Sum of all paid pension vouchers before the selected month (TCO approved)
     */
    private function getPensionOpeningBalance($month, $year, $mdaId = null, $pensionType = null)
    {
        $query = Voucher::where('voucher_type', 'pension')
            ->where('status', 'closed')
            ->whereNotNull('tco_approved_at')
            ->where(function ($q) use ($month, $year) {
                $q->whereMonth('voucher_date', '<', $month)
                ->whereYear('voucher_date', '<=', $year);
            });
        
        if ($mdaId) {
            $query->where('mda_id', $mdaId);
        }
        
        if ($pensionType) {
            $query->where('pension_type', $pensionType);
        }
        
        return (float) $query->sum('total_amount');
    }

    /**
     * Search pension ledger entries (API endpoint for AJAX calls)
     */
    public function searchPensionLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $search = $request->input('search', '');
            $pensionType = $request->input('pension_type', '');
            $perPage = (int) $request->input('per_page', 15);
            $page = (int) $request->input('page', 1);
            
            // Build the query - ONLY pension vouchers that are paid/closed (TCO approved)
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'approvals', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'pension')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            // Filter by MDA if provided
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            // Filter by Pension Type if provided
            if ($pensionType) {
                $query->where('pension_type', $pensionType);
            }
            
            // Apply search filter
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($q) use ($word) {
                        $q->where('voucher_number', 'like', "%{$word}%")
                        ->orWhere('narration', 'like', "%{$word}%")
                        ->orWhere('payee_name', 'like', "%{$word}%")
                        ->orWhere('voucher_type', 'like', "%{$word}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($word) {
                            $mdaQuery->where('name', 'like', "%{$word}%");
                        });
                    });
                }
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')
                ->orderBy('created_at', 'asc')
                ->paginate($perPage, ['*'], 'page', $page);
            
            // Calculate running balance
            $runningBalance = $this->getPensionOpeningBalance($month, $year, $mdaId, $pensionType);
            $entries = [];
            $totalPayments = 0;
            $pensionTypeStats = [];
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                $totalPayments += $amount;
                
                // Calculate pension type statistics
                $type = $voucher->pension_type ?? 'regular';
                if (!isset($pensionTypeStats[$type])) {
                    $pensionTypeStats[$type] = [
                        'type' => $type,
                        'total' => 0,
                        'count' => 0,
                    ];
                }
                $pensionTypeStats[$type]['total'] += $amount;
                $pensionTypeStats[$type]['count']++;
                
                $entries[] = [
                    'id' => $voucher->id,
                    'transaction_date' => $voucher->voucher_date?->toDateString(),
                    'voucher_number' => $voucher->voucher_number,
                    'description' => $this->getLedgerDescription($voucher),
                    'payee_name' => $voucher->payee_name,
                    'amount' => $amount,
                    'running_balance' => $runningBalance,
                    'pay_point' => $voucher->pay_point ?? 'TCO',
                    'schedule_id' => $voucher->schedule_id,
                    'schedule_number' => $voucher->schedule?->schedule_number ?? null,
                    'pension_type' => $voucher->pension_type ?? 'regular',
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'code' => $voucher->mda->code,
                    ] : null,
                    'payment_date' => $voucher->tco_approved_at?->toDateTimeString(),
                    'payment_reference' => $voucher->payment_reference,
                ];
            }
            
            $summary = [
                'opening_balance' => $this->getPensionOpeningBalance($month, $year, $mdaId, $pensionType),
                'total_payments' => $totalPayments,
                'closing_balance' => $this->getPensionOpeningBalance($month, $year, $mdaId, $pensionType) + $totalPayments,
                'total_vouchers' => $vouchers->total(),
            ];
            
            return response()->json([
                'success' => true,
                'entries' => $entries,
                'summary' => $summary,
                'pensionTypeStats' => array_values($pensionTypeStats),
                'paginator' => [
                    'total' => $vouchers->total(),
                    'per_page' => $vouchers->perPage(),
                    'current_page' => $vouchers->currentPage(),
                    'last_page' => $vouchers->lastPage(),
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Pension Ledger Search Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export pension ledger to Excel
     */
    public function exportPensionLedger(Request $request)
    {
        try {
            $month = $request->input('month', now()->month);
            $year = $request->input('year', now()->year);
            $mdaId = $request->input('mda_id');
            $pensionType = $request->input('pension_type', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.economyCodeItem', 'items.programmeCode', 'schedule'])
                // ->whereNull('deleted_at')
                ->where('voucher_type', 'pension')
                ->where('status', 'closed')
                ->whereNotNull('tco_approved_at')
                ->whereMonth('voucher_date', $month)
                ->whereYear('voucher_date', $year);
            
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            
            if ($pensionType) {
                $query->where('pension_type', $pensionType);
            }
            
            $vouchers = $query->orderBy('voucher_date', 'asc')->get();
            
            // Prepare data for export
            $exportData = [];
            $runningBalance = $this->getPensionOpeningBalance($month, $year, $mdaId, $pensionType);
            
            foreach ($vouchers as $voucher) {
                $amount = (float) $voucher->total_amount;
                $runningBalance += $amount;
                
                $exportData[] = [
                    'Date' => $voucher->voucher_date?->toDateString(),
                    'Voucher #' => $voucher->voucher_number,
                    'MDA' => $voucher->mda?->name,
                    'Payee' => $voucher->payee_name,
                    'Pension Type' => ucfirst($voucher->pension_type ?? 'Regular'),
                    'Description' => $this->getLedgerDescription($voucher),
                    'Amount' => $amount,
                    'Running Balance' => $runningBalance,
                    'Payment Date' => $voucher->tco_approved_at?->toDateString(),
                    'Payment Ref' => $voucher->payment_reference,
                ];
            }
            
            // Generate Excel file
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            
            // Headers
            $headers = array_keys($exportData[0] ?? []);
            foreach ($headers as $index => $header) {
                $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue($col . '1', $header);
                $sheet->getStyle($col . '1')->getFont()->setBold(true);
            }
            
            // Data
            $row = 2;
            foreach ($exportData as $data) {
                $col = 1;
                foreach ($data as $value) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->setCellValue($colLetter . $row, $value);
                    $col++;
                }
                $row++;
            }
            
            // Auto size columns
            foreach (range('A', \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers))) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $filename = "Pension_Ledger_{$month}_{$year}.xlsx";
            
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer->save('php://output');
            exit;
            
        } catch (\Exception $e) {
            Log::error('Pension Ledger Export Error: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to export ledger'], 500);
        }
    }
}