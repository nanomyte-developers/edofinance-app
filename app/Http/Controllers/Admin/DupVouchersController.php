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

            // $perPage = $request->input('per_page', 100);
            // $search = $request->input('search', '');
            // $query = Voucher::Query();

            // if ($search !== '') {
            //     $query->where(function ($query) use ($search) {
            //         $query->where('voucher_number', 'like', '%' . $search . '%')
            //             ->orWhere('narration', 'like', '%' . $search . '%')
            //             ->orWhereHas('mda', function ($query) use ($search) {
            //                 $query->where('name', 'like', '%' . $search . '%')->orWhere('oracle_name', 'like', '%' . $search . '%');
            //             })
            //             // ->orWhereHas('schedule', function ($query) use ($search) {
            //             //     $query->where('name', 'like', '%' . $search . '%');
            //             // })
            //             ->orWhere('total_amount', 'like', '%' . $search . '%')
            //             ->orWhere('status', 'like', '%' . $search . '%')
            //             ->orWhere('voucher_type', 'like', '%' . $search . '%')
            //             ->orWhere('voucher_date', 'like', '%' . $search . '%');
            //     });
            // }
            // $vouchers = $query->with(['mda'])
            //     ->orderBy('created_at', 'desc')
            //     ->paginate($perPage)
            //     ->through(function ($voucher) {
            //         return [
            //             'id' => $voucher->id,
            //             'voucher_number' => $voucher->voucher_number,
            //             'voucher_type' => strtoupper($voucher->voucher_type),
            //             'voucher_date' => $voucher->voucher_date?->toDateString(),
            //             'narration' => $voucher->narration,
            //             'total_amount' => $voucher->total_amount,
            //             'status' => $voucher->status,
            //             'mda' => $voucher->mda ? [
            //                 'id' => $voucher->mda->id,
            //                 'name' => $voucher->mda->name,
            //                 'initials' => $voucher->mda->initials,
            //             ] : null,
            //             'created_at' => $voucher->created_at?->toDateTimeString(),
            //             'updated_at' => $voucher->updated_at?->toDateTimeString(),
            //         ];
            //     });

            // $paginator = [
            //     "total" => $vouchers->total(),
            //     "per_page" => $vouchers->perPage(),
            //     "current_page" => $vouchers->currentPage(),
            //     "last_page" => $vouchers->lastPage(),
            //     "first_page_url" => $vouchers->url(1),
            //     "last_page_url" => $vouchers->url($vouchers->lastPage()),
            //     "next_page_url" => $vouchers->nextPageUrl(),
            //     "prev_page_url" => $vouchers->previousPageUrl(),
            //     "path" => $vouchers->path(),
            //     "from" => $vouchers->currentPage(),
            //     "to" => $vouchers->perPage(),
            // ];

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

        // dd($request);
        try {


            $perPage = $request->input('per_page', 100);
            $search = $request->input('search', '');
            $query = Voucher::query();

            if ($search !== '') {
                // Split the search string into individual words
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);

                // For each word, add a nested where clause
                foreach ($words as $word) {
                    $query->where(function ($query) use ($word) {
                        $query->where('voucher_number', 'like', '%' . $word . '%')
                            ->orWhere('narration', 'like', '%' . $word . '%')
                            ->orWhereHas('mda', function ($query) use ($word) {
                                $query->where('name', 'like', '%' . $word . '%')
                                    ->orWhere('oracle_name', 'like', '%' . $word . '%');
                            })
                            // ->orWhereHas('schedule', function ($query) use ($word) {
                            //     $query->where('name', 'like', '%' . $word . '%');
                            // })
                            ->orWhere('total_amount', 'like', '%' . $word . '%')
                            ->orWhere('status', 'like', '%' . $word . '%')
                            ->orWhere('voucher_type', 'like', '%' . $word . '%')
                            ->orWhere('voucher_date', 'like', '%' . $word . '%');
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

            // dd($vouchers);

            // return Inertia::render('admin/vouchers/index', [
            //     'vouchers' => $vouchers, 
            //     'paginator' => $paginator
            // ]);

            return response()->json(['status' => 'success', 'vouchers' =>   $vouchers, 'paginator' => $paginator]);
        } catch (\Exception $e) {
            \Log::error('Voucher Index Error: ' . $e->getMessage());

            return response()->json(['status' => 'error', 'message' =>   'Voucher Index Error: ' . $e->getMessage()]);

            // return Inertia::render('admin/vouchers/index', [
            //     'vouchers' => [
            //         'data' => [],
            //         'total' => 0,
            //         'current_page' => 1,
            //         'per_page' => 10,
            //         'links' => []
            //     ]
            // ]);
        }
    }

    // public function create(Request $request)
    // {
    //     $schedule = null;

    //     if ($request->has('schedule_id')) {
    //         $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
    //             ->find($request->schedule_id);
    //     }

    //     // Get Economic Codes and Items for dropdowns - ADDED
    //     $economyCodes = EconomyCode::select('id', 'code', 'name')
    //         ->orderBy('code')
    //         ->get()
    //         ->map(function ($code) {
    //             return [
    //                 'value' => $code->id,
    //                 'label' => $code->code . ' - ' . $code->name,
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

    //     return Inertia::render('admin/vouchers/voucher', [
    //         'voucherType' => $request->get('type', 'standard'),
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'year_id' => $schedule->year_id,
    //             'mda_id' => $schedule->mda_id,
    //             'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
    //             'budget_code' => $schedule->budgetCode?->code,
    //             'total_amount' => $schedule->total_amount,
    //             'narration' => $schedule->narration,
    //             'items' => $schedule->items->map(function($item) {
    //                 return [
    //                     'payee_name' => $item->payee_name,
    //                     'economy_code_id' => $item->economy_code_id, // ADDED
    //                     'economy_code_item_id' => $item->economy_code_item_id, // ADDED
    //                     'economy_code' => $item->economyCode ? $item->economyCode->code . ' - ' . $item->economyCode->name : 'N/A',
    //                     'amount' => $item->amount,
    //                 ];
    //             }),
    //         ] : null,
    //         'mdas' => AdministrativeCode::all()->map(fn($mda) => [
    //             'value' => $mda->id,
    //             'label' => $mda->name
    //         ]),
    //         'financialYears' => FinancialYear::all()->map(fn($year) => [
    //             'value' => $year->id,
    //             'label' => $year->name
    //         ]),
    //         'economyCodes' => $economyCodes, // ADDED
    //         'economyCodeItems' => $economyCodeItems, // ADDED
    //         'today' => now()->format('Y-m-d'),
    //     ]);
    // }

    public function create(Request $request)
    {
        $schedule = null;

        if ($request->has('schedule_id')) {
            $schedule = Schedule::with(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode'])
                ->find($request->schedule_id);
        }

        // Get Economic Codes and Items for dropdowns
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

        // dd("here");

        return Inertia::render('admin/vouchers/create', [
            'voucherType' => $request->get('type', 'standard'),
            'schedule' => $schedule ? [
                'id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'year_id' => $schedule->year_id,
                'mda_id' => $schedule->mda_id,
                'mda' => $schedule->mda ? ['name' => $schedule->mda->name] : null,
                'budget_code' => $schedule->budgetCode?->code,
                'total_amount' => $schedule->total_amount, // This is the important total
                'amount_posted' => $schedule->vouchers->sum('total_amount'),
                'voucher_count' => $schedule->vouchers->count(),
                'narration' => $schedule->narration,
                // REMOVED items array - we don't want to pre-populate line items
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

        // Debug: Log the incoming data with more details
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
            // Process the voucher creation with enhanced logging
            $voucher = $this->voucherService->createVoucher($data, $files, $documentTypes);




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

            return back()
                ->withInput()
                ->with('error', 'Failed to create voucher: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified resource.
     */
    // public function show(string $id)
    // {
    //     $voucher = Voucher::with([
    //         'mda',
    //         'financialYear',
    //         'items.economyCode', // ADDED Economic Code relationship
    //         'items.economyCodeItem', // ADDED Economic Code item relationship
    //         'documents',
    //         'approvals.user',
    //         'approvals.nextApprover',
    //         'creator'
    //     ])->findOrFail($id);

    //     return Inertia::render('admin/vouchers/show', [
    //         'voucher' => new VoucherResource($voucher),
    //     ]);
    // }

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
        // Eager load all necessary relationships - UPDATED with Economic Codes
        $voucher->load([
            'items.economyCode', // ADDED
            'items.economyCodeItem', // ADDED
            'documents',
            'mda',
            'financialYear'
        ]);

        // Get Economic Codes and Items for dropdowns - ADDED
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

        // Format the voucher date to remove time component - UPDATED with Economic Codes
        $formattedVoucher = [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'payee_name' => $voucher->payee_name,
            'year_id' => $voucher->year_id,
            'mda_id' => $voucher->mda_id,
            'voucher_date' => $voucher->voucher_date?->format('Y-m-d'), // Format date without time
            'narration' => $voucher->narration,
            'status' => $voucher->status,
            'total_amount' => $voucher->total_amount,
            'items' => $voucher->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'economy_code_id' => $item->economy_code_id, // ADDED
                    'economy_code_item_id' => $item->economy_code_item_id, // ADDED
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
            'economyCodes' => $economyCodes, // ADDED
            'economyCodeItems' => $economyCodeItems, // ADDED
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
                'total_amount' => $schedule->total_amount, // This is the important total
                'amount_posted' => $schedule->vouchers->sum('total_amount'),
                'voucher_count' => $schedule->vouchers->count(),
                'narration' => $schedule->narration,
                // REMOVED items array - we don't want to pre-populate line items
            ] : null,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(VoucherStoreUpdateRequest $request, string $id)
    {

        // dd($request);
        $voucher = Voucher::findOrFail($id);
        $data = $request->validated();

        $cleanDate = preg_replace('/\s\(.*\)$/', '', $data['voucher_date']);
        // Step z2: Parse and format for MySQL (YYYY-MM-DD HH:MM:SS)
        $data['voucher_date'] = Carbon::parse($cleanDate)->toDateString();   // Add 1 day to correct javascript bug
        
        $files = $request->file('documents') ?? [];
        $documentTypes = $request->input('document_types', []);

        \Log::info('Voucher Update Request Data:', [
            'voucher_id' => $voucher->id,
            'data_keys' => array_keys($data),
            'files_count' => count($files),
            'document_types_count' => count($documentTypes),
            'document_types' => $documentTypes,
            'file_names' => array_map(function ($file) {
                return $file->getClientOriginalName();
            }, $files),
        ]);

        try {
            // Pass document_types to the service
            // dd($data);
            $updatedVoucher = $this->voucherService->updateVoucher($voucher, $data, $files, $documentTypes);

            \Log::info('Voucher Updated Successfully:', [
                'voucher_id' => $voucher->id,
                'voucher_number' => $voucher->voucher_number,
                'documents_created' => $updatedVoucher->documents->count(),
                'document_types_assigned' => $updatedVoucher->documents->pluck('document_type', 'file_name')
            ]);

            // dd($data);
            return redirect()
                ->route('vouchers.index')
                ->with('success', 'Voucher ' . $voucher->voucher_number . ' updated successfully.');
        } catch (\Exception $e) {
            \Log::error('Voucher Update Failed: ' . $e->getMessage(), [
                'voucher_id' => $voucher->id,
                'data' => $request->except(['documents', 'document_types']),
                'document_types' => $documentTypes,
            ]);

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

            $this->voucherService->deleteVoucher($voucher);

            return redirect()
                ->route('vouchers.index')
                ->with('success', "Voucher {$voucherNumber} deleted successfully.");
        } catch (\Exception $e) {
            \Log::error('Voucher Deletion Failed: ' . $e->getMessage());

            return back()
                ->with('error', 'Failed to delete voucher: ' . $e->getMessage());
        }
    }

    // public function print(Voucher $voucher)
    // {
    //     $voucher->load(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear']);
    //     $voucher->total_amount_in_words = NumberToWords::convert($voucher->total_amount);

    //     return Inertia::render('admin/vouchers/print', [
    //         'voucher' => $voucher
    //     ]);
    // }

    // public function print(Voucher $voucher)
    // {
    //     // Load all necessary relationships
    //     $voucher->load([
    //         'items.economyCode',
    //         'items.economyCodeItem',
    //         'mda', // Administrative sector code relationship
    //         'financialYear',
    //         'schedule.budgetCode', // Schedule with budget code
    //     ]);

    //     dd($voucher);

    //     // Get the MDA (Administrative sector code) data
    //     $mda = $voucher->mda; // This should be from administrative_sector_codes table

    //     // Get the schedule and budget code
    //     $schedule = $voucher->schedule;
    //     $budgetCode = $schedule->budgetCode ?? null;

    //     // Get the first item's Economic Code (assuming single line item from schedule)
    //     $economyCode = null;
    //     if ($voucher->items->isNotEmpty()) {
    //         $firstItem = $voucher->items->first();
    //         $economyCode = $firstItem->economyCode ?? null;
    //     }

    //     return Inertia::render('admin/vouchers/print', [
    //         'voucher' => $voucher,
    //         'mda' => $mda ? [
    //             'id' => $mda->id,
    //             'name' => $mda->name,
    //             'code' => $mda->code, // Administrative sector code
    //             'type' => $mda->type,
    //             'initials' => $mda->initials,
    //         ] : null,
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'total_amount' => $schedule->total_amount,
    //         ] : null,
    //         'budgetCode' => $budgetCode ? [
    //             'id' => $budgetCode->id,
    //             'code' => $budgetCode->code,
    //             'name' => $budgetCode->name,
    //         ] : null,
    //         'economyCode' => $economyCode ? [
    //             'id' => $economyCode->id,
    //             'code' => $economyCode->code,
    //             'name' => $economyCode->name,
    //         ] : null,
    //     ]);
    // }

    // public function print(Voucher $voucher)
    // {
    //     // Load all necessary relationships
    //     $voucher->load([
    //         'items.economyCode', // For Economic Code
    //         'items.economyCodeItem',
    //         'mda', // For MDA information (this might be different from budget code)
    //         'financialYear',
    //         'schedule.budgetCode', // This is where the budget code (023100100100) is
    //     ]);

    //     // Get the MDA information
    //     $mda = $voucher->mda;

    //     // Get the schedule and budget code (administrative sector code)
    //     $schedule = $voucher->schedule;
    //     $budgetCode = $schedule->budgetCode ?? null;

    //     // Debug: Log what we're getting
    //     \Log::info('Print Voucher Data:', [
    //         'voucher_number' => $voucher->voucher_number,
    //         'mda' => $mda ? $mda->toArray() : null,
    //         'schedule' => $schedule ? $schedule->toArray() : null,
    //         'budgetCode' => $budgetCode ? $budgetCode->toArray() : null,
    //         'items' => $voucher->items->map(function($item) {
    //             return [
    //                 'description' => $item->description,
    //                 'economy_code' => $item->economyCode ? $item->economyCode->toArray() : null,
    //                 'quantity' => $item->quantity,
    //                 'unit_price' => $item->unit_price,
    //                 'sub_total' => $item->sub_total,
    //             ];
    //         }),
    //     ]);

    //     // Prepare data for the view
    //     $printData = [
    //         'voucher' => new VoucherResource($voucher),
    //         'mda' => $mda ? [
    //             'id' => $mda->id,
    //             'name' => $mda->name,
    //             'code' => $mda->code, // This might be different from budget code
    //             'type' => $mda->type,
    //             'initials' => $mda->initials,
    //         ] : null,
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'total_amount' => $schedule->total_amount,
    //             'schedule_date' => $schedule->schedule_date,
    //         ] : null,
    //         'budgetCode' => $budgetCode ? [
    //             'id' => $budgetCode->id,
    //             'code' => $budgetCode->code, // This is 023100100100
    //             'name' => $budgetCode->name,
    //             'type' => $budgetCode->type,
    //             'initials' => $budgetCode->initials,
    //         ] : null,
    //     ];

    //     return Inertia::render('admin/vouchers/print', $printData);
    // }


    // public function print(Voucher $voucher)
    // {
    //     // Load all necessary relationships
    //     $voucher->load([
    //         'items.economyCode', // For Economic Code
    //         'items.economyCodeItem',
    //         'mda', // AdministrativeCode model (not the same as budgetCode)
    //         'financialYear',
    //         'schedule.budgetCode', // This contains 023100100100
    //         'schedule.mda', // MDA from schedule
    //     ]);

    //     // Debug: Log all loaded data
    //     \Log::info('=== PRINT VOUCHER DATA ===', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'voucher_type' => $voucher->voucher_type,
    //         'total_amount' => $voucher->total_amount,
    //         'narration' => $voucher->narration,
    //     ]);

    //     // Get the MDA from voucher (should be AdministrativeCode)
    //     $mda = $voucher->mda;
    //     \Log::info('Voucher MDA:', [
    //         'mda' => $mda ? $mda->toArray() : null,
    //     ]);

    //     // Get the schedule and budget code
    //     $schedule = $voucher->schedule;
    //     $budgetCode = $schedule->budgetCode ?? null;

    //     \Log::info('Schedule and BudgetCode:', [
    //         'schedule' => $schedule ? [
    //             'id' => $schedule->id,
    //             'schedule_number' => $schedule->schedule_number,
    //             'mda_id' => $schedule->mda_id,
    //         ] : null,
    //         'budgetCode' => $budgetCode ? $budgetCode->toArray() : null,
    //     ]);

    //     // Get Economic Code from first voucher item
    //     $economyCode = null;
    //     $economyCodeItem = null;
    //     if ($voucher->items->isNotEmpty()) {
    //         $firstItem = $voucher->items->first();
    //         $economyCode = $firstItem->economyCode;
    //         $economyCodeItem = $firstItem->economyCodeItem;

    //         \Log::info('First Item Economy Data:', [
    //             'item_description' => $firstItem->description,
    //             'economy_code' => $economyCode ? $economyCode->toArray() : null,
    //             'economy_code_item' => $economyCodeItem ? $economyCodeItem->toArray() : null,
    //         ]);
    //     }

    //     // Log all items
    //     \Log::info('All Voucher Items:', $voucher->items->map(function($item) {
    //         return [
    //             'id' => $item->id,
    //             'description' => $item->description,
    //             'quantity' => $item->quantity,
    //             'unit_price' => $item->unit_price,
    //             'sub_total' => $item->sub_total,
    //             'economy_code_id' => $item->economy_code_id,
    //             'economy_code_item_id' => $item->economy_code_item_id,
    //         ];
    //     })->toArray());

    //     // Prepare data for Inertia - Use simple arrays for Vue
    //     $voucherData = [
    //         'id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'voucher_type' => $voucher->voucher_type,
    //         'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //         'total_amount' => $voucher->total_amount,
    //         'narration' => $voucher->narration,
    //         'status' => $voucher->status,
    //         'items' => $voucher->items->map(function($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'description' => $item->description,
    //                 'quantity' => $item->quantity,
    //                 'unit_price' => $item->unit_price,
    //                 'sub_total' => $item->sub_total,
    //                 'economy_code' => $item->economyCode ? [
    //                     'id' => $item->economyCode->id,
    //                     'code' => $item->economyCode->code,
    //                     'name' => $item->economyCode->name,
    //                 ] : null,
    //                 'economy_code_item' => $item->economyCodeItem ? [
    //                     'id' => $item->economyCodeItem->id,
    //                     'code' => $item->economyCodeItem->code,
    //                     'name' => $item->economyCodeItem->name,
    //                 ] : null,
    //             ];
    //         })->toArray(),
    //     ];

    //     $mdaData = $mda ? [
    //         'id' => $mda->id,
    //         'name' => $mda->name,
    //         'code' => $mda->code,
    //         'type' => $mda->type,
    //         'initials' => $mda->initials,
    //     ] : null;

    //     $scheduleData = $schedule ? [
    //         'id' => $schedule->id,
    //         'schedule_number' => $schedule->schedule_number,
    //         'total_amount' => $schedule->total_amount,
    //         'schedule_date' => $schedule->schedule_date?->format('Y-m-d'),
    //     ] : null;

    //     $budgetCodeData = $budgetCode ? [
    //         'id' => $budgetCode->id,
    //         'code' => $budgetCode->code, // This should be 023100100100
    //         'name' => $budgetCode->name,
    //         'type' => $budgetCode->type,
    //         'initials' => $budgetCode->initials,
    //     ] : null;

    //     $economyCodeData = $economyCode ? [
    //         'id' => $economyCode->id,
    //         'code' => $economyCode->code,
    //         'name' => $economyCode->name,
    //     ] : null;

    //     // Log the data being sent to Vue
    //     \Log::info('Data being sent to Vue:', [
    //         'voucher_keys' => array_keys($voucherData),
    //         'mda' => $mdaData,
    //         'schedule' => $scheduleData,
    //         'budgetCode' => $budgetCodeData,
    //         'economyCode' => $economyCodeData,
    //         'items_count' => count($voucherData['items']),
    //     ]);

    //     return Inertia::render('admin/vouchers/print', [
    //         'voucher' => $voucherData, // Use array instead of Resource
    //         'mda' => $mdaData,
    //         'schedule' => $scheduleData,
    //         'budgetCode' => $budgetCodeData,
    //         'economyCode' => $economyCodeData,
    //     ]);
    // }


    // public function print(Voucher $voucher)
    // {
    //     // Load all necessary relationships
    //     $voucher->load([
    //         'items.economyCode',
    //         'items.economyCodeItem',
    //         'mda:id,name,code,type,initials', // Specify columns
    //         'financialYear:id,name',
    //         'schedule:id,schedule_number,total_amount,schedule_date,mda_id,budget_code_id',
    //         'schedule.budgetCode:id,code,name,type,initials', // Specify columns
    //     ]);

    //     // Debug the loaded data
    //     \Log::info('=== VOUCHER PRINT DATA DEBUG ===');

    //     // Check MDA relationship
    //     if ($voucher->mda) {
    //         \Log::info('MDA Relationship:', $voucher->mda->toArray());
    //     } else {
    //         \Log::info('MDA Relationship: NULL');
    //     }

    //     // Check Schedule and BudgetCode
    //     if ($voucher->schedule) {
    //         \Log::info('Schedule:', [
    //             'id' => $voucher->schedule->id,
    //             'schedule_number' => $voucher->schedule->schedule_number,
    //             'budget_code_id' => $voucher->schedule->budget_code_id,
    //             'has_budgetCode' => $voucher->schedule->budgetCode ? 'YES' : 'NO',
    //         ]);

    //         if ($voucher->schedule->budgetCode) {
    //             \Log::info('BudgetCode:', $voucher->schedule->budgetCode->toArray());
    //         }
    //     }

    //     // Check items
    //     \Log::info('Voucher Items Count:', ['count' => $voucher->items->count()]);
    //     foreach ($voucher->items as $index => $item) {
    //         \Log::info("Item {$index}:", [
    //             'description' => $item->description,
    //             'quantity' => $item->quantity,
    //             'unit_price' => $item->unit_price,
    //             'sub_total' => $item->sub_total,
    //             'economy_code_id' => $item->economy_code_id,
    //             'economy_code_item_id' => $item->economy_code_item_id,
    //             'has_economyCode' => $item->economyCode ? 'YES' : 'NO',
    //         ]);
    //     }

    //     // Get MDA data - check both possible relationships
    //     $mdaData = null;
    //     if ($voucher->mda) {
    //         $mdaData = [
    //             'id' => $voucher->mda->id,
    //             'name' => $voucher->mda->name,
    //             'code' => $voucher->mda->code,
    //             'type' => $voucher->mda->type,
    //             'initials' => $voucher->mda->initials,
    //         ];
    //     }

    //     // If voucher mda is null, try to get from schedule
    //     if (!$mdaData && $voucher->schedule && $voucher->schedule->mda) {
    //         $mdaData = [
    //             'id' => $voucher->schedule->mda->id,
    //             'name' => $voucher->schedule->mda->name,
    //             'code' => $voucher->schedule->mda->code,
    //             'type' => $voucher->schedule->mda->type,
    //             'initials' => $voucher->schedule->mda->initials,
    //         ];
    //     }

    //     // Get BudgetCode data
    //     $budgetCodeData = null;
    //     if ($voucher->schedule && $voucher->schedule->budgetCode) {
    //         $budgetCodeData = [
    //             'id' => $voucher->schedule->budgetCode->id,
    //             'code' => $voucher->schedule->budgetCode->code,
    //             'name' => $voucher->schedule->budgetCode->name,
    //             'type' => $voucher->schedule->budgetCode->type,
    //             'initials' => $voucher->schedule->budgetCode->initials,
    //         ];
    //     }

    //     // Get Schedule data
    //     $scheduleData = null;
    //     if ($voucher->schedule) {
    //         $scheduleData = [
    //             'id' => $voucher->schedule->id,
    //             'schedule_number' => $voucher->schedule->schedule_number,
    //             'total_amount' => $voucher->schedule->total_amount,
    //             'schedule_date' => $voucher->schedule->schedule_date?->format('Y-m-d'),
    //         ];
    //     }

    //     // Get EconomyCode from first item
    //     $economyCodeData = null;
    //     if ($voucher->items->isNotEmpty() && $firstItem = $voucher->items->first()) {
    //         if ($firstItem->economyCode) {
    //             $economyCodeData = [
    //                 'id' => $firstItem->economyCode->id,
    //                 'code' => $firstItem->economyCode->code,
    //                 'name' => $firstItem->economyCode->name,
    //             ];
    //         }
    //     }

    //     // Prepare voucher data with items
    //     $voucherData = [
    //         'id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //         'total_amount' => $voucher->total_amount,
    //         'narration' => $voucher->narration,
    //         'status' => $voucher->status,
    //         'voucher_type' => $voucher->voucher_type,
    //         'items' => $voucher->items->map(function($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'description' => $item->description,
    //                 'quantity' => $item->quantity,
    //                 'unit_price' => $item->unit_price,
    //                 'sub_total' => $item->sub_total,
    //                 'economy_code_id' => $item->economy_code_id,
    //                 'economy_code_item_id' => $item->economy_code_item_id,
    //                 'economy_code' => $item->economyCode ? [
    //                     'id' => $item->economyCode->id,
    //                     'code' => $item->economyCode->code,
    //                     'name' => $item->economyCode->name,
    //                 ] : null,
    //                 'economy_code_item' => $item->economyCodeItem ? [
    //                     'id' => $item->economyCodeItem->id,
    //                     'code' => $item->economyCodeItem->code,
    //                     'name' => $item->economyCodeItem->name,
    //                 ] : null,
    //             ];
    //         })->toArray(),
    //     ];

    //     // Log what we're sending
    //     \Log::info('Data being sent to Vue:', [
    //         'voucher_number' => $voucherData['voucher_number'],
    //         'mda_data' => $mdaData,
    //         'budgetCode_data' => $budgetCodeData,
    //         'schedule_data' => $scheduleData,
    //         'economyCode_data' => $economyCodeData,
    //         'items_count' => count($voucherData['items']),
    //     ]);

    //     return Inertia::render('admin/vouchers/print', [
    //         'voucher' => $voucherData,
    //         'mda' => $mdaData,
    //         'schedule' => $scheduleData,
    //         'budgetCode' => $budgetCodeData,
    //         'economyCode' => $economyCodeData,
    //     ]);
    // }

    // public function print(Voucher $voucher)
    // {
    //     // Load relationships - focus on getting administrative sector code via schedule
    //     $voucher->load([
    //         'items.economyCode:id,code,name',
    //         'items.economyCodeItem:id,code,name',
    //         'financialYear:id,name',
    //         // Load schedule with administrative sector code relationship
    //         'schedule:id,schedule_number,total_amount,schedule_date,budget_code_id',
    //         'schedule.administrativeSectorCode:id,code,name,type,initials', // This is the key relationship
    //     ]);

    //     // Debug: Check what we loaded
    //     \Log::info('=== PRINT VOUCHER DEBUG ===', [
    //         'voucher_id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'has_schedule' => $voucher->schedule ? 'YES' : 'NO',
    //     ]);

    //     // Get the administrative sector code from schedule
    //     $administrativeSectorCode = null;
    //     $mdaName = '';
    //     $budgetCode = '';

    //     if ($voucher->schedule && $voucher->schedule->administrativeSectorCode) {
    //         $administrativeSectorCode = $voucher->schedule->administrativeSectorCode;

    //         \Log::info('Administrative Sector Code Found:', [
    //             'id' => $administrativeSectorCode->id,
    //             'code' => $administrativeSectorCode->code,
    //             'name' => $administrativeSectorCode->name,
    //             'type' => $administrativeSectorCode->type,
    //             'initials' => $administrativeSectorCode->initials,
    //         ]);

    //         $mdaName = $administrativeSectorCode->name;
    //         $budgetCode = $administrativeSectorCode->code; // This should be 023100100100
    //     } else {
    //         \Log::info('Administrative Sector Code NOT found via schedule');

    //         // Alternative: Check if there's a direct mda relationship on voucher
    //         if ($voucher->mda) {
    //             \Log::info('Using voucher mda instead:', [
    //                 'name' => $voucher->mda->name,
    //                 'code' => $voucher->mda->code,
    //             ]);
    //             $mdaName = $voucher->mda->name;
    //             $budgetCode = $voucher->mda->code;
    //         }
    //     }

    //     // Get Economic Code from first item
    //     $economyCodeData = null;
    //     if ($voucher->items->isNotEmpty()) {
    //         $firstItem = $voucher->items->first();
    //         if ($firstItem->economyCode) {
    //             $economyCodeData = [
    //                 'code' => $firstItem->economyCode->code,
    //                 'name' => $firstItem->economyCode->name,
    //             ];
    //             \Log::info('Economic Code from item:', $economyCodeData);
    //         }
    //     }

    //     // Prepare data for Vue
    //     $voucherData = [
    //         'id' => $voucher->id,
    //         'voucher_number' => $voucher->voucher_number,
    //         'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //         'total_amount' => $voucher->total_amount,
    //         'narration' => $voucher->narration,
    //         'status' => $voucher->status,
    //         'voucher_type' => $voucher->voucher_type,
    //         'items' => $voucher->items->map(function($item) {
    //             return [
    //                 'id' => $item->id,
    //                 'description' => $item->description,
    //                 'quantity' => $item->quantity,
    //                 'unit_price' => $item->unit_price,
    //                 'sub_total' => $item->sub_total,
    //                 'economy_code' => $item->economyCode ? [
    //                     'id' => $item->economyCode->id,
    //                     'code' => $item->economyCode->code,
    //                     'name' => $item->economyCode->name,
    //                 ] : null,
    //                 'economy_code_item' => $item->economyCodeItem ? [
    //                     'id' => $item->economyCodeItem->id,
    //                     'code' => $item->economyCodeItem->code,
    //                     'name' => $item->economyCodeItem->name,
    //                 ] : null,
    //             ];
    //         })->toArray(),
    //     ];

    //     $scheduleData = $voucher->schedule ? [
    //         'id' => $voucher->schedule->id,
    //         'schedule_number' => $voucher->schedule->schedule_number,
    //         'total_amount' => $voucher->schedule->total_amount,
    //         'schedule_date' => $voucher->schedule->schedule_date?->format('Y-m-d'),
    //     ] : null;

    //     // Prepare administrative sector code data (this is what you need for the header)
    //     $adminSectorCodeData = $administrativeSectorCode ? [
    //         'id' => $administrativeSectorCode->id,
    //         'code' => $administrativeSectorCode->code, // 023100100100
    //         'name' => $administrativeSectorCode->name, // MINISTRY OF MINING AND ENERGY
    //         'type' => $administrativeSectorCode->type,
    //         'initials' => $administrativeSectorCode->initials, // MME
    //     ] : null;

    //     \Log::info('Sending to Vue:', [
    //         'voucher_number' => $voucherData['voucher_number'],
    //         'admin_sector_code' => $adminSectorCodeData,
    //         'economy_code' => $economyCodeData,
    //         'schedule' => $scheduleData,
    //     ]);

    //     return Inertia::render('admin/vouchers/print', [
    //         'voucher' => $voucherData,
    //         'administrativeSectorCode' => $adminSectorCodeData, // This is what you need!
    //         'schedule' => $scheduleData,
    //         'economyCode' => $economyCodeData,
    //     ]);
    // }


    public function print(Voucher $voucher)
    {
        // Load relationships - use budgetCode which is the actual relationship name
        $voucher->load([
            'items.economyCode:id,code,name',
            'items.economyCodeItem:id,code,name',
            'financialYear:id,name',
            // Load schedule with budgetCode relationship (this is the AdministrativeSectorCode)
            'schedule:id,schedule_number,total_amount,schedule_date,budget_code_id',
            'schedule.budgetCode:id,code,name,type,initials', // This is the correct relationship name
        ]);

        // Debug: Check what we loaded
        \Log::info('=== PRINT VOUCHER DEBUG ===', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'has_schedule' => $voucher->schedule ? 'YES' : 'NO',
            'schedule_id' => $voucher->schedule?->id,
            'budget_code_id' => $voucher->schedule?->budget_code_id,
            'has_budgetCode' => $voucher->schedule && $voucher->schedule->budgetCode ? 'YES' : 'NO',
        ]);

        // Get the administrative sector code from schedule's budgetCode relationship
        $administrativeSectorCode = null;
        $mdaName = '';
        $budgetCode = '';

        if ($voucher->schedule && $voucher->schedule->budgetCode) {
            $administrativeSectorCode = $voucher->schedule->budgetCode;

            \Log::info('Administrative Sector Code Found via budgetCode:', [
                'id' => $administrativeSectorCode->id,
                'code' => $administrativeSectorCode->code,
                'name' => $administrativeSectorCode->name,
                'type' => $administrativeSectorCode->type,
                'initials' => $administrativeSectorCode->initials,
            ]);

            $mdaName = $administrativeSectorCode->name;
            $budgetCode = $administrativeSectorCode->code; // This should be 023100100100
        } else {
            \Log::warning('Administrative Sector Code NOT found via schedule.budgetCode');

            // Fallback: Check if there's a direct mda relationship on voucher
            if ($voucher->mda) {
                \Log::info('Using voucher mda as fallback:', [
                    'name' => $voucher->mda->name,
                    'code' => $voucher->mda->code,
                ]);
                $mdaName = $voucher->mda->name;
                $budgetCode = $voucher->mda->code;
                $administrativeSectorCode = $voucher->mda;
            }
        }

        // Get Economic Code from first item
        $economyCodeData = null;
        if ($voucher->items->isNotEmpty()) {
            $firstItem = $voucher->items->first();
            if ($firstItem->economyCode) {
                $economyCodeData = [
                    'code' => $firstItem->economyCode->code,
                    'name' => $firstItem->economyCode->name,
                ];
                \Log::info('Economic Code from item:', $economyCodeData);
            } else {
                \Log::info('First item has no economyCode relationship:', [
                    'item_id' => $firstItem->id,
                    'description' => $firstItem->description,
                    'economy_code_id' => $firstItem->economy_code_id,
                ]);
            }
        }

        // Prepare data for Vue
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

        // Prepare administrative sector code data (this is what you need for the header)
        $adminSectorCodeData = $administrativeSectorCode ? [
            'id' => $administrativeSectorCode->id,
            'code' => $administrativeSectorCode->code, // 023100100100
            'name' => $administrativeSectorCode->name, // MINISTRY OF MINING AND ENERGY
            'type' => $administrativeSectorCode->type,
            'initials' => $administrativeSectorCode->initials, // MME
        ] : null;

        \Log::info('Sending to Vue:', [
            'voucher_number' => $voucherData['voucher_number'],
            'admin_sector_code_exists' => $adminSectorCodeData ? 'YES' : 'NO',
            'admin_sector_code_data' => $adminSectorCodeData,
            'economy_code' => $economyCodeData,
            'schedule' => $scheduleData,
            'items_count' => count($voucherData['items']),
        ]);

        return Inertia::render('admin/vouchers/print2', [
            'voucher' => $voucherData,
            'administrativeSectorCode' => $adminSectorCodeData, // This is what you need!
            'schedule' => $scheduleData,
            'economyCode' => $economyCodeData,
        ]);
    }


    public function getBankActivities(Request $request)
    {
        $filter = $request->input('filter', '');
        $items = BankActivity::when($filter, function ($query, $filter) {
            return $query->where('tag', 'like', "%{$filter}%")->orWhere('bank_name', 'like', "%{$filter}%")->orWhere('account_number', 'like', "%{$filter}%")->orWhere('title', 'like', "%{$filter}%");
        })
            ->paginate(15); // Paginate the results

        return response()->json($items);
    }
}
