<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRetirementRequest;
use App\Http\Requests\UpdateRetirementStatusRequest;
use App\Http\Resources\RetirementVoucherCollection;
use App\Http\Resources\RetirementVoucherResource;
use App\Models\EconomyCode;
use App\Models\RetirementVoucher;
use App\Models\Voucher;
use App\Services\RetirementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;



class RetirementController extends Controller
{
    protected $retirementService;

    public function __construct(RetirementService $retirementService)
    {
        $this->retirementService = $retirementService;
    }

    /**
     * Display retirement index page
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $query = RetirementVoucher::with(['originalVoucher', 'creator', 'mda'])
            ->latest();

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('retirement_type')) {
            $query->where('retirement_type', $request->retirement_type);
        }

        if ($user->mda_id) {
            $query->where('mda_id', $user->mda_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('retirement_number', 'LIKE', "%{$search}%")
                    ->orWhereHas('originalVoucher', function ($q) use ($search) {
                        $q->where('voucher_number', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('creator', function ($q) use ($search) {
                        $q->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        $perPage = $request->get('per_page', 20);
        $retirementVouchers = $query->paginate($perPage);

        return Inertia::render('Retirement/Index', [
            'retirementVouchers' => new RetirementVoucherCollection($retirementVouchers),
            'filters' => $request->only(['search', 'status', 'retirement_type']),
            'stats' => $this->retirementService->getStatistics(
                $user->mda_id,
                null,
                now()->startOfMonth()->format('Y-m-d'),
                now()->endOfMonth()->format('Y-m-d')
            ),
        ]);
    }

    /**
     * Show create retirement page for a voucher
     */
    public function create(Voucher $voucher)
    {
        // Temporarily disable authorization for debugging
        // Gate::authorize('retire', $voucher);

        // Check if voucher can be retired
        if ($voucher->status !== 'approved') {
            return redirect()->back()->with('error', 'Only approved vouchers can be retired.');
        }

        if ($voucher->voucher_type !== 'prepayment') {
            return redirect()->back()->with('error', 'Only prepayment vouchers can be retired.');
        }

        if ($voucher->is_fully_retired) {
            return redirect()->back()->with('error', 'This voucher is already fully retired.');
        }

        $voucher->load(['items.economicCode', 'items.codeItem', 'mda', 'schedule']);

        // Calculate available balance
        $availableBalance = $voucher->amount - ($voucher->retired_amount ?? 0);

        // Get economy codes for this MDA
        $economicCodes = EconomyCode::when($voucher->mda_id, function ($query) use ($voucher) {
            return $query->where('mda_id', $voucher->mda_id);
        })
            ->with('codeItems')
            ->get()
            ->map(function ($code) {
                return [
                    'id' => $code->id,
                    'code' => $code->code,
                    'name' => $code->name,
                    'label' => $code->code.' - '.$code->name,
                    'code_items' => $code->codeItems->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'label' => $item->name,
                        ];
                    }),
                ];
            });

        return Inertia::render('Voucher/Retire', [
            'voucher' => $voucher,
            'economicCodes' => $economicCodes,
            'availableBalance' => $availableBalance,
            'retiredAmount' => $voucher->retired_amount ?? 0,
        ]);
    }

    /**
     * Store a new retirement
     */
    public function store(StoreRetirementRequest $request, Voucher $voucher)
    {
        try {
            // Get validated data
            $validated = $request->validated();

            // Log for debugging
            \Log::info('Retirement submission data:', [
                'voucher_id' => $voucher->id,
                'validated_data' => $validated,
                'user_id' => Auth::id(),
            ]);

            // Create retirement using service
            $result = $this->retirementService->createRetirement(
                $validated,
                $voucher,
                Auth::id()
            );

            if (! $result['success']) {
                \Log::error('Retirement creation failed:', [
                    'error' => $result['error'],
                    'voucher_id' => $voucher->id,
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'message' => $result['error'],
                    ], 422);
                }

                return back()->withErrors([
                    'error' => $result['error'],
                ]);
            }

            // Success response
            $message = $validated['remaining_balance'] > 0
                ? 'Partial retirement submitted successfully!'
                : 'Voucher fully retired successfully!';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                    'retirement' => $result['retirement'],
                ], 201);
            }

            return redirect()->route('vouchers.index')->with('success', $message);

        } catch (\Exception $e) {
            \Log::error('Retirement store error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'voucher_id' => $voucher->id ?? null,
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Failed to submit retirement: '.$e->getMessage(),
                ], 500);
            }

            return back()->withErrors([
                'error' => 'Failed to submit retirement. Please try again.',
            ]);
        }
    }

    /**
     * Show retirement voucher details
     */
    public function show(RetirementVoucher $retirementVoucher, Voucher $voucher)
    {
        // Temporarily disable authorization
        // Gate::authorize('view', $retirementVoucher);

        $retirementVoucher->load([
            'originalVoucher',
            'items.economicCode',
            'items.codeItem',
            'creator',
            'approver',
            'logs.user',
            'mda',
            'year',
            'bankActivity',
            'retirements', // Load retirement relationships if you have them
        ]);

        // Calculate retirement statistics
        $retirementStats = [
            'total_retired' => $voucher->retired_amount ?? 0,
            'available_balance' => $voucher->total_amount - ($voucher->retired_amount ?? 0),
            'retirement_count' => $voucher->retirements()->count(),
            'last_retirement' => $voucher->retirements()->latest()->first(),
        ];

        return Inertia::render('Retirement/Show', [
            'retirementVoucher' => new RetirementVoucherResource($retirementVoucher),
            'retirementStats' => $retirementStats,
            'canApprove' => $retirementVoucher->canBeApproved() && Auth::user()->can('approveRetirement', $retirementVoucher),
            'canDelete' => $retirementVoucher->canBeDeleted() && Auth::user()->can('delete', $retirementVoucher),
        ]);
    }

    /**
     * Approve retirement voucher
     */
    public function approve(UpdateRetirementStatusRequest $request, RetirementVoucher $retirementVoucher)
    {
        try {
            $result = $this->retirementService->approveRetirement(
                $retirementVoucher,
                Auth::id(),
                $request->comment
            );

            if (! $result['success']) {
                return back()->withErrors([
                    'error' => $result['error'],
                ]);
            }

            return redirect()->route('retirements.show', $retirementVoucher)->with([
                'success' => 'Retirement voucher approved successfully!',
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to approve retirement: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Reject retirement voucher
     */
    public function reject(UpdateRetirementStatusRequest $request, RetirementVoucher $retirementVoucher)
    {
        try {
            $result = $this->retirementService->rejectRetirement(
                $retirementVoucher,
                Auth::id(),
                $request->comment
            );

            if (! $result['success']) {
                return back()->withErrors([
                    'error' => $result['error'],
                ]);
            }

            return redirect()->route('retirements.show', $retirementVoucher)->with([
                'success' => 'Retirement voucher rejected!',
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to reject retirement: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Delete retirement voucher
     */
    public function destroy(Request $request, RetirementVoucher $retirementVoucher)
    {
        // Temporarily disable authorization
        // Gate::authorize('delete', $retirementVoucher);

        try {
            $result = $this->retirementService->deleteRetirement(
                $retirementVoucher,
                Auth::id(),
                $request->comment
            );

            if (! $result['success']) {
                return back()->withErrors([
                    'error' => $result['error'],
                ]);
            }

            return redirect()->route('retirements.index')->with([
                'success' => 'Retirement voucher deleted successfully!',
            ]);
        } catch (\Exception $e) {
            return back()->withErrors([
                'error' => 'Failed to delete retirement: '.$e->getMessage(),
            ]);
        }
    }

    /**
     * Get retirement history for a voucher
     */
    public function history(Voucher $voucher)
    {
        // Temporarily disable authorization
        // Gate::authorize('view', $voucher);

        $retirementHistory = RetirementVoucher::with([
            'items' => function ($query) {
                $query->with(['economicCode', 'codeItem']);
            },
            'creator',
        ])
            ->where('original_voucher_id', $voucher->id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Debug output
        foreach ($retirementHistory as $retirement) {
            foreach ($retirement->items as $item) {
                Log::info('Retirement Item:', [
                    'id' => $item->id,
                    'economic_code_id' => $item->economic_code_id,
                    'economic_code_item_id' => $item->economic_code_item_id,
                    'has_economicCode' => $item->economicCode ? 'Yes' : 'No',
                    'has_codeItem' => $item->codeItem ? 'Yes' : 'No',
                ]);
            }
        }

        return RetirementVoucherResource::collection($retirementHistory);

    }

    /**
     * Get pending retirements for approval
     */
    public function pending(Request $request)
    {
        $user = Auth::user();
        $query = RetirementVoucher::with(['originalVoucher', 'creator', 'mda'])
            ->where('status', 'pending') // Changed from 'submitted' to 'pending'
            ->latest();

        // Apply MDA filter
        if ($user->mda_id) {
            $query->where('mda_id', $user->mda_id);
        }

        $perPage = $request->get('per_page', 15);
        $pending = $query->paginate($perPage);

        return Inertia::render('Retirement/Pending', [
            'pendingRetirements' => new RetirementVoucherCollection($pending),
            'filters' => $request->only(['search']),
        ]);
    }

    /**
     * Get retirement statistics
     */
    public function stats(Request $request)
    {
        $user = Auth::user();

        $stats = $this->retirementService->getStatistics(
            $request->get('mda_id', $user->mda_id),
            $request->get('year_id'),
            $request->get('start_date'),
            $request->get('end_date')
        );

        if ($request->expectsJson()) {
            return response()->json([
                'data' => $stats,
            ]);
        }

        return Inertia::render('Retirement/Stats', [
            'stats' => $stats,
            'filters' => $request->only(['mda_id', 'year_id', 'start_date', 'end_date']),
            'mdas' => \App\Models\Mda::all()->map(function ($mda) {
                return ['id' => $mda->id, 'name' => $mda->name];
            }),
            'years' => \App\Models\FinancialYear::all()->map(function ($year) {
                return ['id' => $year->id, 'name' => $year->name];
            }),
        ]);
    }

    /**
     * Check if voucher can be retired
     */
    // public function checkRetirementStatus(Voucher $voucher)
    // {
    //     // Temporarily disable authorization
    //     // Gate::authorize('retire', $voucher);

    //     $availableBalance = $voucher->total_amount - ($voucher->retired_amount ?? 0);
    //     $isFullyRetired = $availableBalance <= 0;

    //     return response()->json([
    //         'can_retire' => $voucher->status === 'Approved' &&
    //                       $voucher->voucher_type === 'prepayment' &&
    //                       ! $isFullyRetired &&
    //                       $availableBalance > 0,
    //         'available_balance' => $availableBalance,
    //         'already_retired' => $isFullyRetired,
    //         'retired_amount' => $voucher->retired_amount ?? 0,
    //         'remaining_balance' => $availableBalance,
    //         'voucher_status' => $voucher->status,
    //         'voucher_type' => $voucher->voucher_type,
    //     ]);
    // }

    public function checkRetirementStatus(Voucher $voucher)
    {
        // Check if voucher can be retired (for retirement button)
        $availableBalance = $voucher->total_amount - ($voucher->retired_amount ?? 0);
        $isFullyRetired = $availableBalance <= 0;
        
        $can_retire = $voucher->status === 'Approved' &&
                    $voucher->voucher_type === 'prepayment' &&
                    !$isFullyRetired &&
                    $availableBalance > 0;

        return response()->json([
            'can_retire' => $can_retire,
            'available_balance' => $availableBalance,
            'already_retired' => $isFullyRetired,
            'retired_amount' => $voucher->retired_amount ?? 0,
            'remaining_balance' => $availableBalance,
            'voucher_status' => $voucher->status,
            'voucher_type' => $voucher->voucher_type,
            'can_be_approved' => $voucher->status === 'Submitted' && $voucher->voucher_type === 'prepayment', // Added this
        ]);
    }

    /**
     * Test endpoint for debugging
     */
    public function testRetirement(Voucher $voucher)
    {
        return response()->json([
            'message' => 'Route is working',
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'amount' => $voucher->amount,
            'retired_amount' => $voucher->retired_amount ?? 0,
            'status' => $voucher->status,
            'type' => $voucher->voucher_type,
            'available_balance' => $voucher->amount - ($voucher->retired_amount ?? 0),
        ]);
    }
}
