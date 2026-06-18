<?php
// app/Http/Controllers/Admin/ExpenditureControlController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use App\Models\VoucherApproval;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;

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
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals']);
            
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
                // return $data;
                
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
            $salaryType = $request->input('salary_type', '');
            $statusFilter = $request->input('status', '');
            
            // Get salary vouchers that are forwarded by FA and ready for EC
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'items.programmeCode', 'creator', 'approvals', 'assignedTo'])
                ->where('voucher_type', 'salary')
                ->whereIn('status', ['forwarded', 'ec_approved', 'inspectorate_pending'])
                ->orderBy('created_at', 'desc');
            
            // Apply salary type filter
            if ($salaryType) {
                $query->where('salary_type', $salaryType);
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
                    'salary_type' => $voucher->salary_type,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'fa_approved_at' => $faApproval?->approved_at?->toDateTimeString(),
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
                'salary_type' => $voucher->salary_type,
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
            $salaryType = $request->input('salary_type', '');
            $statusFilter = $request->input('status', '');
            
            $query = Voucher::with(['mda', 'bankActivity', 'items', 'creator', 'assignedTo'])
                ->where('voucher_type', 'salary')
                ->whereIn('status', ['forwarded', 'ec_approved', 'inspectorate_pending'])
                ->orderBy('created_at', 'desc');
            
            // Apply salary type filter
            if ($salaryType) {
                $query->where('salary_type', $salaryType);
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
                    'salary_type' => $voucher->salary_type,
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
                    'status' => 'ag_approved'
                ],
                'mas' => [
                    'role' => VoucherApproval::ROLE_MAS,
                    'display' => 'Management Account Section (MAS)',
                    'status' => 'mas_approved'
                ],
                'inspectorate' => [
                    'role' => VoucherApproval::ROLE_INSPECTORATE,
                    'display' => 'Inspectorate',
                    'status' => 'inspectorate_pending'
                ],
                'tco' => [
                    'role' => VoucherApproval::ROLE_TCO,
                    'display' => 'Treasury Cash Office (TCO)',
                    'status' => 'tco_pending'
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
}