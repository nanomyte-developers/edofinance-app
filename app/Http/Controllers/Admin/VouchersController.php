<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Mda;
use Inertia\Inertia;
use App\Models\Voucher;
use App\Models\Schedule;
use App\Models\EconomyCode;
use App\Models\BankActivity;
use Illuminate\Http\Request;
use App\Models\FinancialYear;
use App\Helpers\NumberToWords;
use App\Models\EconomyCodeItem;
use App\Models\ProgrammeCode;
use App\Services\ActivityLogger;
use App\Services\VoucherService;
use App\Models\AdministrativeCode;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Resources\VoucherResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\VoucherStoreUpdateRequest;
use App\Services\BudgetService;

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

    public function index(Request $request)
    {
        try {
            $this->activityLogger->log(
                "Viewed vouchers list",
                [
                    'search' => $request->input('search', ''),
                    'per_page' => $request->input('per_page', 100),
                    'filters' => $request->except(['search', 'per_page', 'page'])
                ],
                'voucher'
            );

            return Inertia::render('admin/vouchers/index', []);
        } catch (\Exception $e) {
            \Log::error('Voucher Index Error: ' . $e->getMessage());
            return Inertia::render('admin/vouchers/index', [
                'vouchers' => [
                    'data' => [],
                    'total' => 0,
                    'current_page' => 1,
                    'per_page' => 10,
                    'links' => []
                ]
            ]);
        }
    }

    public function search(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 100);
            $search = $request->input('search', '');
            $query = Voucher::query();

            if ($search !== '') {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $word) {
                    $query->where(function ($query) use ($word) {
                        $query->where('voucher_number', 'like', "%{$word}%")
                            ->orWhere('narration', 'like', "%{$word}%")
                            ->orWhereHas('mda', function ($query) use ($word) {
                                $query->where('name', 'like', "%{$word}%")
                                    ->orWhere('oracle_name', 'like', "%{$word}%");
                            })
                            ->orWhere('total_amount', 'like', "%{$word}%")
                            ->orWhere('status', 'like', "%{$word}%")
                            ->orWhere('voucher_type', 'like', "%{$word}%")
                            ->orWhere('voucher_date', 'like', "%{$word}%");
                    });
                }
            }
            
            $vouchers = $query->with(['mda', 'bankActivity', 'items'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->through(function ($voucher) {
                    return [
                        'id' => $voucher->id,
                        'voucher_number' => $voucher->voucher_number,
                        'voucher_type' => strtoupper($voucher->voucher_type),
                        'voucher_date' => $voucher->voucher_date?->toDateString(),
                        'narration' => $voucher->narration,
                        'total_amount' => $voucher->total_amount,
                        'payee_name' => $voucher->payee_name,
                        'status' => $voucher->status,
                        'mda' => $voucher->mda ? [
                            'id' => $voucher->mda->id,
                            'name' => $voucher->mda->name,
                            'initials' => $voucher->mda->initials,
                            'code' => $voucher->mda->code
                        ] : null,
                        'bank_activity' => $voucher->bankActivity ? [
                            'account_number' => $voucher->bankActivity->account_number,
                            'bank_name' => $voucher->bankActivity->bank_name,
                            'tag' => $voucher->bankActivity->tag,
                            'title' => $voucher->bankActivity->title,
                            'economic_code' => $voucher->bankActivity->economic_code,
                        ] : null,
                        'items' => $voucher->items->isNotEmpty() ? [
                            'description' => $voucher->items[0]->description,
                            'economy_code_item' => $voucher->items[0]->economyCodeItem ? [
                                'id' => $voucher->items[0]->economyCodeItem->id,
                                'code' => $voucher->items[0]->economyCodeItem->code,
                                'description' => $voucher->items[0]->economyCodeItem->description,
                            ] : null,
                            'amount' => $voucher->items[0]->sub_total,
                        ] : null,
                        'created_at' => $voucher->created_at?->toDateTimeString(),
                        'updated_at' => $voucher->updated_at?->toDateTimeString(),
                    ];
                });

            $paginator = [
                "total" => $vouchers->total(),
                "per_page" => $vouchers->perPage(),
                "current_page" => $vouchers->currentPage(),
                "last_page" => $vouchers->lastPage(),
                "first_page_url" => $vouchers->url(1),
                "last_page_url" => $vouchers->url($vouchers->lastPage()),
                "next_page_url" => $vouchers->nextPageUrl(),
                "prev_page_url" => $vouchers->previousPageUrl(),
                "path" => $vouchers->path(),
                "from" => $vouchers->currentPage(),
                "to" => $vouchers->perPage(),
            ];

            $this->activityLogger->log(
                "Searched vouchers",
                [
                    'search_term' => $search,
                    'results_count' => $vouchers->total(),
                    'per_page' => $perPage,
                    'user_id' => auth()->id()
                ],
                'voucher'
            );

            return response()->json(['status' => 'success', 'vouchers' => $vouchers, 'paginator' => $paginator]);
        } catch (\Exception $e) {
            \Log::error('Voucher Search Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Voucher Index Error: ' . $e->getMessage()]);
        }
    }

    public function create(Request $request)
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

        $this->activityLogger->log(
            "Accessed voucher creation form",
            [
                'schedule_id' => $request->get('schedule_id'),
                'voucher_type' => $request->get('type', 'standard'),
                'programme_codes_count' => $programmeCodes->count(),
                'user_id' => auth()->id()
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

    // public function store(VoucherStoreUpdateRequest $request): RedirectResponse
    // {
    //     $data = $request->validated();
    //     $files = $request->file('documents') ?? [];
    //     $documentTypes = $request->input('document_types', []);

    //     \Log::info('Voucher Store Request Data:', [
    //         'data_keys' => array_keys($data),
    //         'files_count' => count($files),
    //         'document_types_count' => count($documentTypes),
    //         'user_id' => auth()->id(),
    //     ]);

    //     try {
    //         $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);

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
    //                 'user_id' => auth()->id()
    //             ],
    //             'voucher'
    //         );

    //         $this->activityLogger->logAction('created', $voucher, [
    //             'amount' => $voucher->total_amount,
    //             'payee_name' => $voucher->payee_name,
    //             'narration' => $voucher->narration
    //         ]);

    //         \Log::info('Voucher Created Successfully:', [
    //             'voucher_id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'status' => $voucher->status,
    //             'documents_created' => $voucher->documents->count(),
    //         ]);

    //         return redirect()
    //             ->route('vouchers.index')
    //             ->with('success', 'Voucher ' . $voucher->voucher_number . ' created successfully.');
    //     } catch (\Exception $e) {
    //         \Log::error('Voucher Creation Failed: ' . $e->getMessage(), [
    //             'trace' => $e->getTraceAsString(),
    //             'data' => $request->except(['documents', 'document_types']),
    //             'user_id' => auth()->id(),
    //         ]);

    //         $this->activityLogger->log(
    //             "Failed to create voucher",
    //             [
    //                 'error' => $e->getMessage(),
    //                 'data_keys' => array_keys($data),
    //                 'attempted_by' => auth()->id()
    //             ],
    //             'voucher'
    //         );

    //         return back()
    //             ->withInput()
    //             ->with('error', 'Failed to create voucher: ' . $e->getMessage());
    //     }
    // }

    // public function approve(Voucher $voucher, Request $request)
    // {
    //     if ($voucher->voucher_type !== 'prepayment') {
    //         return back()->withErrors(['message' => 'Only prepayment vouchers can be approved for retirement.']);
    //     }

    //     if ($voucher->status !== 'Submitted') {
    //         return back()->withErrors(['message' => 'Voucher is not in submitted status.']);
    //     }

    //     $voucher->update([
    //         'status' => 'Approved',
    //         'approved_by' => auth()->id(),
    //         'approved_at' => now(),
    //     ]);

    //     activity()
    //         ->performedOn($voucher)
    //         ->causedBy(auth()->user())
    //         ->withProperties(['old_status' => 'Submitted', 'new_status' => 'Approved'])
    //         ->log('approved prepayment voucher for retirement');

    //     return response()->json([
    //         'success' => true,
    //         'message' => 'Voucher approved successfully.',
    //         'voucher' => $voucher->fresh(),
    //     ]);
    // }

    // Replace the existing approve method with this one
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

    // Add a new method to reject voucher and release budget
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

    // Also update the store method to validate budget on submission
    public function store(VoucherStoreUpdateRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $files = $request->file('documents') ?? [];
        $documentTypes = $request->input('document_types', []);

        \Log::info('Voucher Store Request Data:', [
            'data_keys' => array_keys($data),
            'files_count' => count($files),
            'document_types_count' => count($documentTypes),
            'user_id' => auth()->id(),
        ]);

        try {
            $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);
            
            // If submitted for approval, validate budget
            if ($data['status'] === 'Submitted') {
                $budgetValidation = $this->budgetService->validateVoucherBudget($voucher);
                
                if (!$budgetValidation['is_valid']) {
                    // Budget validation failed - delete the voucher
                    $voucher->delete();
                    $errorMessage = implode('; ', $budgetValidation['errors']);
                    return back()
                        ->withInput()
                        ->with('error', 'Budget validation failed: ' . $errorMessage);
                }
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
                    'document_count' => $voucher->documents->count(),
                    'schedule_id' => $voucher->schedule_id,
                    'user_id' => auth()->id()
                ],
                'voucher'
            );

            $this->activityLogger->logAction('created', $voucher, [
                'amount' => $voucher->total_amount,
                'payee_name' => $voucher->payee_name,
                'narration' => $voucher->narration
            ]);

            \Log::info('Voucher Created Successfully:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'status' => $voucher->status,
                'documents_created' => $voucher->documents->count(),
            ]);

            return redirect()
                ->route('vouchers.index')
                ->with('success', 'Voucher ' . $voucher->voucher_number . ' created successfully.');
        } catch (\Exception $e) {
            \Log::error('Voucher Creation Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['documents', 'document_types']),
                'user_id' => auth()->id(),
            ]);

            $this->activityLogger->log(
                "Failed to create voucher",
                [
                    'error' => $e->getMessage(),
                    'data_keys' => array_keys($data),
                    'attempted_by' => auth()->id()
                ],
                'voucher'
            );

            return back()
                ->withInput()
                ->with('error', 'Failed to create voucher: ' . $e->getMessage());
        }
    }

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

        return Inertia::render('admin/vouchers/show', [
            'voucher' => $voucherData,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Voucher $voucher)
    {
        // Eager load all necessary relationships - FIXED with proper programmeCode loading
        $voucher->load([
            'items.economyCode',
            'items.economyCodeItem',
            'items.programmeCode', // This relationship must exist in VoucherItem model
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
        ]);
    }

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
                // If programme_code_id is provided but programme_code is missing, we can fetch it
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

        return Inertia::render('admin/vouchers/print2', [
            'voucher' => $voucherData,
            'administrativeSectorCode' => $adminSectorCodeData,
            'schedule' => $scheduleData,
            'economyCode' => $economyCodeData,
        ]);
    }

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

        \Log::info('Final Accounts Voucher Store Request:', [
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
}