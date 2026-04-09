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
use App\Services\ActivityLogger;
use App\Services\VoucherService;
use App\Models\AdministrativeCode;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use App\Http\Resources\VoucherResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\VoucherStoreUpdateRequest;

class VouchersController extends Controller
{
    protected VoucherService $voucherService;
    protected $activityLogger;

    public function __construct(VoucherService $voucherService, ActivityLogger $activityLogger)
    {
        $this->voucherService = $voucherService;
        $this->activityLogger = $activityLogger;
    }

    public function index(Request $request)
    {
        try {
            // Log view activity
            $this->activityLogger->log(
                "Viewed vouchers list",
                [
                    'search' => $request->input('search', ''),
                    'per_page' => $request->input('per_page', 100),
                    'filters' => $request->except(['search', 'per_page', 'page'])
                ],
                'voucher'
            );

            return Inertia::render('admin/vouchers/index', [
                // 'vouchers' => $vouchers,
                // 'paginator' => $paginator
            ]);
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
            
            $vouchers = $query->with(['mda'])
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
                        'status' => $voucher->status,
                        'mda' => $voucher->mda ? [
                            'id' => $voucher->mda->id,
                            'name' => $voucher->mda->name,
                            'initials' => $voucher->mda->initials,
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

            // Log search activity
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

        // Log creation form access
        $this->activityLogger->log(
            "Accessed voucher creation form",
            [
                'schedule_id' => $request->get('schedule_id'),
                'voucher_type' => $request->get('type', 'standard'),
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
            'today' => now()->format('Y-m-d'),
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

        // dd($request->all());

        \Log::info('Voucher Store Request Data:', [
            'data_keys' => array_keys($data),
            'files_count' => count($files),
            'document_types_count' => count($documentTypes),
            'document_types' => $documentTypes,
            'file_names' => array_map(function ($file) {
                return $file->getClientOriginalName();
            }, $files),
            'user_id' => auth()->id(),
        ]);

        try {
            // Process the voucher creation
            $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);

            // Log voucher creation activity
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

            // Log specific action for audit
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
                'document_types_assigned' => $voucher->documents->pluck('document_type', 'file_name')
            ]);

            return redirect()
                ->route('vouchers.index')
                ->with('success', 'Voucher ' . $voucher->voucher_number . ' created successfully.');
        } catch (\Exception $e) {
            \Log::error('Voucher Creation Failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'data' => $request->except(['documents', 'document_types']),
                'document_types' => $documentTypes,
                'user_id' => auth()->id(),
            ]);

            // Log failed creation attempt
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
        // Load all necessary relationships
        $voucher->load([
            'items.economyCode',
            'items.economyCodeItem',
            'documents',
            'mda',
            'financialYear',
            'schedule',
            'approvals.user',
            'creator',
            'bankActivity',
        ]);

        // Log voucher view activity
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

        // Convert to array with relationships
        $voucherData = $voucher->toArray();

        // Add the loaded relationships manually
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
        // Eager load all necessary relationships
        $voucher->load([
            'items.economyCode',
            'items.economyCodeItem',
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
            'items' => $voucher->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'economy_code_id' => $item->economy_code_id,
                    'economy_code_item_id' => $item->economy_code_item_id,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sub_total' => $item->sub_total,
                ];
            }),
        ];
        
        $schedule = null;
        if ($voucher->schedule) {
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
            ] : null,
        ]);
    }

    public function approve(Voucher $voucher, Request $request)
    {

        // dd($voucher);
        // Check if user can approve
        // $this->authorize('approve', $voucher);

        // Validate voucher can be approved
        if ($voucher->voucher_type !== 'prepayment') {
            return back()->withErrors(['message' => 'Only prepayment vouchers can be approved for retirement.']);
        }

        if ($voucher->status !== 'Submitted') {
            return back()->withErrors(['message' => 'Voucher is not in submitted status.']);
        }

        // Update voucher status
        $voucher->update([
            'status' => 'Approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Log the approval
        activity()
            ->performedOn($voucher)
            ->causedBy(auth()->user())
            ->withProperties(['old_status' => 'Submitted', 'new_status' => 'Approved'])
            ->log('approved prepayment voucher for retirement');

        return response()->json([
            'success' => true,
            'message' => 'Voucher approved successfully.',
            'voucher' => $voucher->fresh(),
        ]);
    }

    

    /**
     * Update the specified resource in storage.
     */
    // public function update(VoucherStoreUpdateRequest $request, string $id)
    // {
    //     // dd($request->all());
    //     $voucher = Voucher::findOrFail($id);
    //     $data = $request->validated();

    //     $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
    //     $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();
        
    //     $files = $request->file('documents') ?? [];
    //     $documentTypes = $request->input('document_types', []);

    //     // Store original data for logging changes
    //     $originalData = $voucher->toArray();
    //     $changes = [];

    //     \Log::info('Voucher Update Request Data:', [
    //         'voucher_id' => $voucher->id,
    //         'data_keys' => array_keys($data),
    //         'files_count' => count($files),
    //         'document_types_count' => count($documentTypes),
    //         'document_types' => $documentTypes,
    //         'file_names' => array_map(function ($file) {
    //             return $file->getClientOriginalName();
    //         }, $files),
    //     ]);

    //     try {
    //         // Pass document_types to the service
    //         $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);

    //         // Determine what changed
    //         foreach ($data as $key => $value) {
    //             if ($originalData[$key] != $value && $key !== 'updated_at') {
    //                 $changes[$key] = [
    //                     'from' => $originalData[$key],
    //                     'to' => $value
    //                 ];
    //             }
    //         }

    //         // Log voucher update activity
    //         $this->activityLogger->log(
    //             "Updated voucher {$voucher->voucher_number}",
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'changes' => $changes,
    //                 'document_updates' => [
    //                     'files_added' => count($files),
    //                     'document_types' => $documentTypes
    //                 ],
    //                 'updated_by' => auth()->id(),
    //                 'old_status' => $originalData['status'] ?? null,
    //                 'new_status' => $updatedVoucher->status
    //             ],
    //             'voucher'
    //         );

    //         // Log specific action with detailed changes
    //         $this->activityLogger->logAction('updated', $voucher, [
    //             'changes' => $changes,
    //             'document_count' => $updatedVoucher->documents->count(),
    //             'updated_by' => auth()->id()
    //         ]);

    //         \Log::info('Voucher Updated Successfully:', [
    //             'voucher_id' => $voucher->id,
    //             'voucher_number' => $voucher->voucher_number,
    //             'documents_created' => $updatedVoucher->documents->count(),
    //             'document_types_assigned' => $updatedVoucher->documents->pluck('document_type', 'file_name')
    //         ]);

    //         return redirect()
    //             ->route('vouchers.index')
    //             ->with('success', 'Voucher ' . $voucher->voucher_number . ' updated successfully.');
    //     } catch (\Exception $e) {
    //         \Log::error('Voucher Update Failed: ' . $e->getMessage(), [
    //             'voucher_id' => $voucher->id,
    //             'data' => $request->except(['documents', 'document_types']),
    //             'document_types' => $documentTypes,
    //         ]);

    //         // Log failed update attempt
    //         $this->activityLogger->log(
    //             "Failed to update voucher {$voucher->voucher_number}",
    //             [
    //                 'voucher_id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'error' => $e->getMessage(),
    //                 'attempted_by' => auth()->id()
    //             ],
    //             'voucher'
    //         );

    //         return back()
    //             ->withInput()
    //             ->with('error', 'Failed to update voucher: ' . $e->getMessage());
    //     }
    // }
    /**
 * Update the specified resource in storage.
 */
// public function update(VoucherStoreUpdateRequest $request, string $id)
// {
//     // dd($request->all());
//     $voucher = Voucher::findOrFail($id);
//     $data = $request->validated();

//     $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
//     $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();
    
//     // FIX: Check if documents exist before accessing them
//     $files = [];
//     if ($request->hasFile('documents')) {
//         $files = $request->file('documents');
//     }
    
//     $documentTypes = $request->input('document_types', []);

//     // Store original data for logging changes
//     $originalData = $voucher->toArray();
//     $changes = [];

//     \Log::info('Voucher Update Request Data:', [
//         'voucher_id' => $voucher->id,
//         'data_keys' => array_keys($data),
//         'files_count' => count($files),
//         'document_types_count' => count($documentTypes),
//         'document_types' => $documentTypes,
//         'file_names' => $files ? array_map(function ($file) {
//             return $file->getClientOriginalName();
//         }, $files) : [], // FIX: Only map if files exist
//     ]);

//     try {
//         // Pass document_types to the service - check if files exist
//         if (!empty($files)) {
//             $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);
//         } else {
//             // Update voucher without documents
//             $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, [], $documentTypes);
//         }

//         // Determine what changed
//         foreach ($data as $key => $value) {
//             if ($originalData[$key] != $value && $key !== 'updated_at') {
//                 $changes[$key] = [
//                     'from' => $originalData[$key],
//                     'to' => $value
//                 ];
//             }
//         }

//         // Log voucher update activity
//         $this->activityLogger->log(
//             "Updated voucher {$voucher->voucher_number}",
//             [
//                 'voucher_id' => $voucher->id,
//                 'voucher_number' => $voucher->voucher_number,
//                 'changes' => $changes,
//                 'document_updates' => [
//                     'files_added' => count($files),
//                     'document_types' => $documentTypes
//                 ],
//                 'updated_by' => auth()->id(),
//                 'old_status' => $originalData['status'] ?? null,
//                 'new_status' => $updatedVoucher->status
//             ],
//             'voucher'
//         );

//         // Log specific action with detailed changes
//         $this->activityLogger->logAction('updated', $voucher, [
//             'changes' => $changes,
//             'document_count' => $updatedVoucher->documents->count(),
//             'updated_by' => auth()->id()
//         ]);

//         \Log::info('Voucher Updated Successfully:', [
//             'voucher_id' => $voucher->id,
//             'voucher_number' => $voucher->voucher_number,
//             'documents_created' => $updatedVoucher->documents->count(),
//             'document_types_assigned' => $updatedVoucher->documents->pluck('document_type', 'file_name')
//         ]);

//         return redirect()
//             ->route('vouchers.index')
//             ->with('success', 'Voucher ' . $voucher->voucher_number . ' updated successfully.');
//     } catch (\Exception $e) {
//         \Log::error('Voucher Update Failed: ' . $e->getMessage(), [
//             'voucher_id' => $voucher->id,
//             'data' => $request->except(['documents', 'document_types']),
//             'document_types' => $documentTypes,
//         ]);

//         // Log failed update attempt
//         $this->activityLogger->log(
//             "Failed to update voucher {$voucher->voucher_number}",
//             [
//                 'voucher_id' => $voucher->id,
//                 'voucher_number' => $voucher->voucher_number,
//                 'error' => $e->getMessage(),
//                 'attempted_by' => auth()->id()
//             ],
//             'voucher'
//         );

//         return back()
//             ->withInput()
//             ->with('error', 'Failed to update voucher: ' . $e->getMessage());
//     }
// }

/**
 * Update the specified resource in storage.
 */
// public function update(VoucherStoreUpdateRequest $request, string $id)
// {
//     // Debug: Log raw request data
//     \Log::info('Raw Request Data:', [
//         'all_data' => $request->all(),
//         'has_total_amount' => $request->has('total_amount'),
//         'total_amount_value' => $request->input('total_amount'),
//         'files_count' => $request->hasFile('documents') ? count($request->file('documents')) : 0,
//     ]);

//     $voucher = Voucher::findOrFail($id);
    
//     // Get validated data
//     $data = $request->validated();
    
//     // Debug: Log validated data
//     \Log::info('Validated Data:', [
//         'keys' => array_keys($data),
//         'has_total_amount' => isset($data['total_amount']),
//         'total_amount' => $data['total_amount'] ?? 'MISSING',
//         'items_count' => isset($data['items']) ? count($data['items']) : 0,
//     ]);

//     // If total_amount is still missing, calculate it from items
//     if (!isset($data['total_amount']) && isset($data['items'])) {
//         $total = collect($data['items'])->sum(function ($item) {
//             return isset($item['sub_total']) ? (float) $item['sub_total'] : 0;
//         });
//         $data['total_amount'] = round($total, 2);
//         \Log::info('Calculated total_amount:', ['total_amount' => $data['total_amount']]);
//     }

//     $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
//     $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();
    
//     // FIX: Check if documents exist before accessing them
//     $files = [];
//     if ($request->hasFile('documents')) {
//         $files = $request->file('documents');
//     }
    
//     // FIX: Get document_types only if they exist and are an array
//     $documentTypes = [];
//     if ($request->has('document_types')) {
//         $inputTypes = $request->input('document_types');
//         // Ensure it's an array
//         if (is_array($inputTypes)) {
//             $documentTypes = $inputTypes;
//         } elseif (is_string($inputTypes) && !empty($inputTypes)) {
//             // Try to decode if it's a JSON string
//             $decoded = json_decode($inputTypes, true);
//             if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
//                 $documentTypes = $decoded;
//             }
//         }
//     }

//     // Store original data for logging changes
//     $originalData = $voucher->toArray();
//     $changes = [];

//     \Log::info('Voucher Update Request Data:', [
//         'voucher_id' => $voucher->id,
//         'data_keys' => array_keys($data),
//         'total_amount_in_data' => $data['total_amount'] ?? 'NOT FOUND',
//         'files_count' => count($files),
//         'document_types_count' => count($documentTypes),
//     ]);

//     try {
//         // Pass data to service - ensure total_amount is included
//         if (!isset($data['total_amount'])) {
//             throw new \Exception('Total amount is required but not provided or calculated.');
//         }

//         $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);

//         // Determine what changed
//         foreach ($data as $key => $value) {
//             // Skip comparing array values like items
//             if (!is_array($value) && isset($originalData[$key]) && $originalData[$key] != $value && $key !== 'updated_at') {
//                 $changes[$key] = [
//                     'from' => $originalData[$key],
//                     'to' => $value
//                 ];
//             }
//         }

//         // Log voucher update activity
//         $this->activityLogger->log(
//             "Updated voucher {$voucher->voucher_number}",
//             [
//                 'voucher_id' => $voucher->id,
//                 'voucher_number' => $voucher->voucher_number,
//                 'changes' => $changes,
//                 'total_amount' => $data['total_amount'],
//                 'document_updates' => [
//                     'files_added' => count($files),
//                     'document_types' => $documentTypes
//                 ],
//                 'updated_by' => auth()->id(),
//                 'old_status' => $originalData['status'] ?? null,
//                 'new_status' => $updatedVoucher->status
//             ],
//             'voucher'
//         );

//         // Log specific action with detailed changes
//         $this->activityLogger->logAction('updated', $voucher, [
//             'changes' => $changes,
//             'document_count' => $updatedVoucher->documents->count(),
//             'updated_by' => auth()->id()
//         ]);

//         \Log::info('Voucher Updated Successfully:', [
//             'voucher_id' => $voucher->id,
//             'voucher_number' => $voucher->voucher_number,
//             'documents_created' => $updatedVoucher->documents->count(),
//             'total_amount' => $updatedVoucher->total_amount,
//         ]);

//         return redirect()
//             ->route('vouchers.index')
//             ->with('success', 'Voucher ' . $voucher->voucher_number . ' updated successfully.');
//     } catch (\Exception $e) {
//         \Log::error('Voucher Update Failed: ' . $e->getMessage(), [
//             'voucher_id' => $voucher->id,
//             'data_keys' => array_keys($data),
//             'has_total_amount' => isset($data['total_amount']),
//             'document_types' => $documentTypes,
//             'trace' => $e->getTraceAsString(),
//         ]);

//         // Log failed update attempt
//         $this->activityLogger->log(
//             "Failed to update voucher {$voucher->voucher_number}",
//             [
//                 'voucher_id' => $voucher->id,
//                 'voucher_number' => $voucher->voucher_number,
//                 'error' => $e->getMessage(),
//                 'attempted_by' => auth()->id()
//             ],
//             'voucher'
//         );

//         return back()
//             ->withInput()
//             ->with('error', 'Failed to update voucher: ' . $e->getMessage());
//     }
// }

/**
 * Update the specified resource in storage.
 */
public function update(VoucherStoreUpdateRequest $request, Voucher $voucher)
{
    // Debug: Log what we received
    \Log::info('Update Controller - Voucher:', [
        'voucher_id' => $voucher->id,
        'voucher_number' => $voucher->voucher_number,
        'type_of_voucher' => get_class($voucher),
    ]);

    $data = $request->validated();
    $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
    $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();
    
    // FIX: Check if documents exist before accessing them
    $files = [];
    if ($request->hasFile('documents')) {
        $files = $request->file('documents');
    }
    
    // FIX: Get document_types only if they exist and are an array
    $documentTypes = [];
    if ($request->has('document_types')) {
        $inputTypes = $request->input('document_types');
        // Ensure it's an array
        if (is_array($inputTypes)) {
            $documentTypes = $inputTypes;
        } elseif (is_string($inputTypes) && !empty($inputTypes)) {
            // Try to decode if it's a JSON string
            $decoded = json_decode($inputTypes, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $documentTypes = $decoded;
            }
        }
    }

    // Store original data for logging changes
    $originalData = $voucher->toArray();
    $changes = [];

    \Log::info('Voucher Update Request Data:', [
        'voucher_id' => $voucher->id,
        'data_keys' => array_keys($data),
        'total_amount_in_data' => $data['total_amount'] ?? 'NOT FOUND',
        'files_count' => count($files),
        'document_types_count' => count($documentTypes),
    ]);

    try {
        // Pass data to service - ensure total_amount is included
        if (!isset($data['total_amount'])) {
            throw new \Exception('Total amount is required but not provided or calculated.');
        }

        $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);

        // Determine what changed
        foreach ($data as $key => $value) {
            // Skip comparing array values like items
            if (!is_array($value) && isset($originalData[$key]) && $originalData[$key] != $value && $key !== 'updated_at') {
                $changes[$key] = [
                    'from' => $originalData[$key],
                    'to' => $value
                ];
            }
        }

        // Log voucher update activity
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

        // Log specific action with detailed changes
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

        // Log failed update attempt
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
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $voucherNumber = $voucher->voucher_number;
            $voucherData = $voucher->toArray();

            // Log before deletion
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

            // Log successful deletion
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

            // Log specific delete action
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

            // Log failed deletion
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
        // Load relationships
        $voucher->load([
            'items.economyCode:id,code,name',
            'items.economyCodeItem:id,code,name',
            'financialYear:id,name',
            'schedule:id,schedule_number,total_amount,schedule_date,budget_code_id',
            'schedule.budgetCode:id,code,name,type,initials',
        ]);

        // Log print activity
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

        \Log::info('=== PRINT VOUCHER DEBUG ===', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'has_schedule' => $voucher->schedule ? 'YES' : 'NO',
            'schedule_id' => $voucher->schedule?->id,
            'budget_code_id' => $voucher->schedule?->budget_code_id,
            'has_budgetCode' => $voucher->schedule && $voucher->schedule->budgetCode ? 'YES' : 'NO',
        ]);

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
        
        // Log bank activities search
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
}