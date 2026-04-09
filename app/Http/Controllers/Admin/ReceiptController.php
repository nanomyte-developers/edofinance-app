<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use App\Models\Mda;
use Inertia\Inertia;
use App\Models\Receipt;
use App\Models\EconomyCode;
use App\Models\BankActivity;
use Illuminate\Http\Request;
use App\Models\EconomyCodeItem;
use App\Models\ReceiptActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use App\Services\ActivityLogger;


class ReceiptController extends Controller
{
    protected $activityLogger;

    public function __construct(ActivityLogger $activityLogger)
    {

        $this->activityLogger = $activityLogger;
    }

    /**
     * Display the main listing page.
     */

    public function index(Request $request)
    {
        // Start building the query
        $query = Receipt::query();

        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;

            // Split search string into individual terms
            $terms = preg_split('/\s+/', trim($search));
            $terms = array_filter($terms); // Remove empty terms

            if (!empty($terms)) {
                $query->where(function ($q) use ($terms) {
                    foreach ($terms as $term) {
                        $q->orWhere(function ($subQuery) use ($term) {
                            $subQuery->where('receipt_number', 'like', "%{$term}%")
                                ->orWhere('mda_name', 'like', "%{$term}%")
                                ->orWhere('account_name', 'like', "%{$term}%")
                                ->orWhere('eco_code', 'like', "%{$term}%")
                                ->orWhere('eco_code_item', 'like', "%{$term}%")
                                ->orWhere('activity', 'like', "%{$term}%")
                                ->orWhere('bank_name', 'like', "%{$term}%")
                                ->orWhere('account_number', 'like', "%{$term}%")
                                ->orWhere('classification', 'like', "%{$term}%")
                                ->orWhere('amount', 'like', "%{$term}%")
                                ->orWhere('status', 'like', "%{$term}%")
                                ->orWhere('receipt_date', 'like', "%{$term}%");
                        });
                    }
                });
            }
        }

        // Apply additional filters if needed
        if ($request->has('mda_name') && !empty($request->mda_name)) {
            $query->where('mda_name', 'like', "%{$request->mda_name}%");
        }

        if ($request->has('date_from') && !empty($request->date_from)) {
            $query->whereDate('receipt_date', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
        }

        if ($request->has('date_to') && !empty($request->date_to)) {
            $query->whereDate('receipt_date', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
        }

        // Get per_page from request or use default
        $perPage = $request->input('per_page', 20);

        // Paginate results
        $receipts = $query->latest()->paginate($perPage);

        // Get economy codes and items to map for frontend
        $mdas = Mda::get();
        $mdas = $mdas->map(function ($mda) {
            return [
                'id' => $mda->id,
                'name' => $mda->name . ' (' . $mda->code  . ')',
            ];
        });

        $bank_activities = BankActivity::select('id', 'tag', 'bank_name', 'title', 'account_number')->where('status', 1)->get();
        $receipt_activities = ReceiptActivity::select('id', 'name', 'status')->where('status', 1)->get();

        // Get all economy codes (for mapping)
        $economyCodes = EconomyCode::active()
            // ->where('code', 'REGEXP', '^1[0-9]*$')
            // ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$')
            ->get(['id', 'code', 'name'])
            ->keyBy('code') // Key by code for easy lookup
            ->toArray();

        // Get all economy code items (for mapping)
        $economyCodeItems = EconomyCodeItem::with(['economyCode' => function ($query) {
            // $query->where('code', 'REGEXP', '^1[0-9]*$')
            //     ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
        }])
            ->active()
            ->whereHas('economyCode', function ($query) {
                // $query->where('code', 'REGEXP', '^1[0-9]*$')
                //     ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
            })
            ->get(['id', 'economy_code_id', 'code', 'name'])
            ->keyBy('code') // Key by code for easy lookup
            ->toArray();

        // Transform receipt data with mapped IDs
        $transformedReceipts = $receipts->getCollection()->map(function ($receipt) use ($economyCodes, $economyCodeItems) {
            // Find economy code ID based on eco_code string
            $economyCodeId = null;
            if ($receipt->eco_code && isset($economyCodes[$receipt->eco_code])) {
                $economyCodeId = $economyCodes[$receipt->eco_code]['id'];
            }

            // Find economy code item ID based on eco_code_item string
            $economyCodeItemId = null;
            if ($receipt->eco_code_item && isset($economyCodeItems[$receipt->eco_code_item])) {
                $economyCodeItemId = $economyCodeItems[$receipt->eco_code_item]['id'];
            }

            return [
                'id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'mda_name' => $receipt->mda_name,
                'eco_code' => $receipt->eco_code,
                'eco_code_item' => $receipt->eco_code_item,
                'economy_code_id' => $economyCodeId,
                'economy_code_item_id' => $economyCodeItemId,
                'activity' => $receipt->activity,
                'amount' => $receipt->amount,
                'receipt_date' => $receipt->receipt_date,
                'classification' => $receipt->classification,
                'bank_name' => $receipt->bank_name,
                'status' => $receipt->status,
                'account_number' => $receipt->account_number,
                'account_name' => $receipt->account_name,
                'created_at' => $receipt->created_at,
                'updated_at' => $receipt->updated_at,
            ];
        });

        // Create a new paginator with transformed data
        $transformedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $transformedReceipts,
            $receipts->total(),
            $receipts->perPage(),
            $receipts->currentPage(),
            ['path' => $receipts->path()]
        );

        // Format economy codes for frontend dropdown
        $economyCodesForDropdown = array_values(array_map(function ($code) {
            return [
                'id' => $code['id'],
                'code' => $code['code'],
                'name' => $code['name'],
            ];
        }, $economyCodes));

        // Format economy code items for frontend dropdown
        $formattedEconomyCodeItems = [];
        foreach ($economyCodeItems as $item) {
            $formattedEconomyCodeItems[] = [
                'id' => $item['id'],
                'code' => $item['code'],
                'name' => $item['name'],
                'economy_code_id' => $item['economy_code_id'],
                'parent_code' => $item['economy_code']['code'] ?? null,
                'display_text' => $item['code'] . ' - ' . $item['name'] .
                    ($item['economy_code'] ? ' (' . $item['economy_code']['code'] . ' - ' . $item['economy_code']['name'] . ')' : '')
            ];
        }
// foreach ($formattedEconomyCodeItems as $item) {
//     if ($item['economy_code_id'] == '31010000' || $item['economy_code_id'] == 31010000) { 
//         dd($item);
//     }
// }
        // dd($formattedEconomyCodeItems);

        return Inertia::render('admin/receipts/index', [
            'receipts' => [
                'data' => $transformedPaginator->items(),
                'current_page' => $transformedPaginator->currentPage(),
                'first_page_url' => $transformedPaginator->url(1),
                'from' => $transformedPaginator->firstItem(),
                'last_page' => $transformedPaginator->lastPage(),
                'last_page_url' => $transformedPaginator->url($transformedPaginator->lastPage()),
                'next_page_url' => $transformedPaginator->nextPageUrl(),
                'path' => $transformedPaginator->path(),
                'per_page' => $transformedPaginator->perPage(),
                'prev_page_url' => $transformedPaginator->previousPageUrl(),
                'to' => $transformedPaginator->lastItem(),
                'total' => $transformedPaginator->total(),
            ],
            'mdas' => $mdas,
            'bank_activities' => $bank_activities,
            'receipt_activities' => $receipt_activities,
            'economyCodes' => $economyCodesForDropdown,
            'economyCodeItems' => $formattedEconomyCodeItems,
            'filters' => $request->only(['search', 'mda_name', 'date_from', 'date_to', 'per_page']),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'receipt_number' => 'required|string|max:50',
            'mda_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'receipt_date' => 'required|date',
            'classification' => 'required|string|max:50',
            'eco_code' => 'nullable|string|max:50',
            'eco_code_item' => 'nullable|string|max:50',
            'activity' => 'nullable|string',
            'bank_name' => 'required',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
            'tag' => 'nullable|string|max:255',
        ]);


        // Format the date
        $validated['receipt_date'] = \Carbon\Carbon::parse($request->receipt_date)->format('Y-m-d');
        $validated['status'] = 'Draft';

        // Fetch the Bank Name STRING using the ID from the request
        $bankActivity = BankActivity::find($request->bank_name);
        if ($bankActivity) {
            $validated['bank_name'] = $bankActivity->bank_name;
        }
        $Banktag = BankActivity::find($request->bank_name);
        if ($Banktag) {
            $validated['tag'] = $Banktag->tag;
        }

        // Handle account name
        // if ($request->title) {
        //     $validated['account_name'] = $request->account_name;
        // }

        // Save to database
        $receipt = Receipt::create($validated);

        $this->activityLogger->log(
            "Created receipt {$receipt->receipt_number}",
            [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_type' => $receipt->receipt_type,
                'total_amount' => $receipt->total_amount,
                'mda_id' => $receipt->mda_id,
                'status' => $receipt->status,
                'receipt_date' => $receipt->receipt_date,
                'schedule_id' => $receipt->schedule_id,
                'user_id' => auth()->id()
            ],
            'receipt'
        );

        // Log specific action for audit
        $this->activityLogger->logAction('created', $receipt, [
            'amount' => $receipt->amount,
            'mda_name' => $receipt->mda_name,
            'activity' => $receipt->activity,
            'eco_code' => $receipt->eco_code,
            'eco_code_item' => $receipt->eco_code_item,
            'bank_name' => $receipt->bank_name,
            'account_number' => $receipt->account_number,
            'account_name' => $receipt->account_name,
            'tag' => $receipt->tag,
            'status' => $receipt->status,
            'receipt_date' => $receipt->receipt_date,
        ]);

        return back()->with('message', 'Receipt created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // dd($request->all());
        \Log::info('=== UPDATE RECEIPT CONTROLLER ===');
        \Log::info('Receipt ID: ' . $id);
        \Log::info('Request Method: ' . $request->method());
        \Log::info('All Request Data: ', $request->all());
        \Log::info('Route: ' . $request->fullUrl());

        $receipt = Receipt::findOrFail($id);

        \Log::info('Found receipt: ', [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
        ]);

        $validated = $request->validate([
            'receipt_number' => 'required|string|max:50',
            'mda_name' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'receipt_date' => 'required|date',
            'classification' => 'required|string|max:50',
            'eco_code' => 'nullable|string|max:50',
            'eco_code_item' => 'nullable|string|max:50',
            'activity' => 'nullable|string',
            'bank_name' => 'nullable',
            'account_number' => 'nullable|string|max:50',
            'account_name' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:20',
        ]);

        \Log::info('Validated data: ', $validated);

        $validated['receipt_date'] = Carbon::parse($request->receipt_date)->format('Y-m-d');
        $validated['status'] = $request->status ?? 'Submitted';

        $bankActivity = BankActivity::find($request->bank_name);

        if ($bankActivity) {
            $validated['bank_name'] = $bankActivity->bank_name;
        }

        // dd($validated);
        $receipt->update($validated);

        $this->activityLogger->log(
            "Updated receipt {$receipt->receipt_number}",
            [
                'receipt_id' => $receipt->id,
                'receipt_number' => $receipt->receipt_number,
                'receipt_type' => $receipt->receipt_type,
                'total_amount' => $receipt->total_amount,
                'mda_id' => $receipt->mda_id,
                'status' => $receipt->status,
                'receipt_date' => $receipt->receipt_date,
                'schedule_id' => $receipt->schedule_id,
                'user_id' => auth()->id()
            ],
            'receipt'
        );

        // Log specific action for audit
        $this->activityLogger->logAction('Updated Receipt', $receipt, [
            'amount' => $receipt->amount,
            'mda_name' => $receipt->mda_name,
            'activity' => $receipt->activity,
            'eco_code' => $receipt->eco_code,
            'eco_code_item' => $receipt->eco_code_item,
            'bank_name' => $receipt->bank_name,
            'account_number' => $receipt->account_number,
            'account_name' => $receipt->account_name,
            'receipt_date' => $receipt->receipt_date,
            'tag' => $receipt->tag,
            'status' => $receipt->status,
        ]);

        \Log::info('Receipt updated successfully');

        return back()->with('message', 'Receipt updated successfully.');
    }

    /**
     * Handles the dynamic AJAX request for the DataTable.
     */
    public function data(Request $request): JsonResponse
    {
        $query = Receipt::query();

        // Dynamic Search
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('receipt_number', 'like', "%{$search}%")
                    ->orWhere('mda_name', 'like', "%{$search}%")
                    ->orWhere('account_name', 'like', "%{$search}%");
            });
        }

        // Pagination
        $perPage = $request->input('per_page', 50);
        $receipts = $query->latest()->paginate($perPage);

        return response()->json([
            'receipts' => $receipts,
        ]);
    }

    /**
     * Display a single receipt (The receipt.vue component).
     */
    // public function show(string $id)
    // {
    //     $receipt = Receipt::findOrFail($id);

    //     // Get the bank activity details including tag
    //     $bankActivity = BankActivity::where('bank_name', $receipt->bank_name)->first();

    //     // Get economy codes and items for mapping
    //     $economyCodes = EconomyCode::active()
    //         ->where('code', 'REGEXP', '^1[0-9]*$')
    //         ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$')
    //         ->get(['id', 'code', 'name'])
    //         ->keyBy('code');

    //     $economyCodeItems = EconomyCodeItem::with(['economyCode'])
    //         ->active()
    //         ->whereHas('economyCode', function ($query) {
    //             $query->where('code', 'REGEXP', '^1[0-9]*$')
    //                 ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
    //         })
    //         ->get(['id', 'economy_code_id', 'code', 'name'])
    //         ->keyBy('code');

    //     // Add mapped IDs to receipt
    //     $receiptWithMappedIds = $receipt->toArray();
    //     $receiptWithMappedIds['economy_code_id'] = $economyCodes->get($receipt->eco_code)?->id;
    //     $receiptWithMappedIds['economy_code_item_id'] = $economyCodeItems->get($receipt->eco_code_item)?->id;

    //     return Inertia::render('admin/receipts/show', [
    //         'receipt' => $receiptWithMappedIds,
    //         'bank_tag' => $bankActivity ? $bankActivity->tag : null,
    //     ]);
    // }

    public function show(string $id)
    {
        $receipt = Receipt::findOrFail($id);

        // Get the bank activity details including tag
        $bankActivity = BankActivity::where('bank_name', $receipt->bank_name)->first();

        // Get economy codes and items for mapping
        $economyCodes = EconomyCode::active()
            ->where('code', 'REGEXP', '^1[0-9]*$')
            ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$')
            ->get(['id', 'code', 'name', 'status'])
            ->keyBy('code');

        $economyCodeItems = EconomyCodeItem::with(['economyCode'])
            ->active()
            ->whereHas('economyCode', function ($query) {
                $query->where('code', 'REGEXP', '^1[0-9]*$')
                    ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
            })
            ->get(['id', 'economy_code_id', 'code', 'name', 'status'])
            ->keyBy('code');

        // Add mapped IDs to receipt
        $receiptWithMappedIds = $receipt->toArray();
        $receiptWithMappedIds['economy_code_id'] = $economyCodes->get($receipt->eco_code)?->id;
        $receiptWithMappedIds['economy_code_item_id'] = $economyCodeItems->get($receipt->eco_code_item)?->id;

        // Get all necessary data for the edit modal
        return Inertia::render('admin/receipts/show', [
            'receipt' => $receiptWithMappedIds,
            'bank_tag' => $bankActivity ? $bankActivity->tag : null,

            // Data for edit modal dropdowns
            'mdas' => Mda::orderBy('name')->get(['id', 'name']),
            'receipt_activities' => ReceiptActivity::orderBy('name')->get(['id', 'name']),

            // Economic codes data
            'economy_codes' => EconomyCode::active()
                ->where('code', 'REGEXP', '^1[0-9]*$')
                ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'status']),

            // Economic code items data
            'economy_code_items' => EconomyCodeItem::with(['economyCode'])
                ->active()
                ->whereHas('economyCode', function ($query) {
                    $query->where('code', 'REGEXP', '^1[0-9]*$')
                        ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
                })
                ->orderBy('code')
                ->get(['id', 'economy_code_id', 'code', 'name', 'status']),

            // Bank activities data
            'banks' => BankActivity::orderBy('bank_name')
                ->get(['id', 'bank_name', 'account_number', 'title', 'tag']),
        ]);
    }


    /**
     * Prepare data for the Receipt Print view (Inertia version).
     */
    public function print($id)
    {
        $receipt = Receipt::findOrFail($id);

        // Get the bank activity details including tag
        $bankActivity = BankActivity::where('bank_name', $receipt->bank_name)->first();

        $receiptData = [
            'id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'mda_name' => $receipt->mda_name,
            'eco_code' => $receipt->eco_code,
            'eco_code_item' => $receipt->eco_code_item,
            'activity' => $receipt->activity,
            'amount' => $receipt->amount,
            'receipt_date' => $receipt->receipt_date ? \Carbon\Carbon::parse($receipt->receipt_date)->format('Y-m-d') : null,
            'classification' => $receipt->classification,
            'bank_name' => $receipt->bank_name,
            'account_number' => $receipt->account_number,
            'account_name' => $receipt->account_name,
            // Add bank activity tag to receipt data
            'bank_tag' => $bankActivity ? $bankActivity->tag : null,
        ];

        \Log::info('=== PRINT RECEIPT DEBUG ===', [
            'receipt_id' => $receipt->id,
            'receipt_number' => $receipt->receipt_number,
            'mda' => $receipt->mda_name,
            'bank_tag' => $bankActivity ? $bankActivity->tag : 'No tag found',
        ]);

        return Inertia::render('admin/receipts/print', [
            'receipt' => $receiptData,
        ]);
    }

    public function destroy(string $id)
    {
        $receipt = Receipt::findOrFail($id);
        $receipt->delete();

        return redirect()->route('receipts.index')->with('success', 'Receipt deleted successfully');

        // return back()->with('success', 'Receipt deleted successfully');
    }

    /**
     * Handle CSV Import
     */
    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt|max:2048',
    //     ]);

    //     $file = $request->file('file');
    //     $handle = fopen($file->getRealPath(), 'r');

    //     // Skip header
    //     fgetcsv($handle);

    //     DB::beginTransaction();
    //     try {
    //         $importedCount = 0;
    //         $skippedCount = 0;

    //         while (($row = fgetcsv($handle)) !== false) {
    //             if (count($row) < 11) {
    //                 $skippedCount++;
    //                 continue;
    //             }

    //             // Parse date from DD-MM-YYYY to YYYY-MM-DD
    //             $date = null;
    //             if (!empty($row[6])) {
    //                 $dateParts = explode('-', $row[6]);
    //                 if (count($dateParts) === 3) {
    //                     // Reformat from DD-MM-YYYY to YYYY-MM-DD
    //                     $date = "{$dateParts[2]}-{$dateParts[1]}-{$dateParts[0]}";
    //                 }
    //             }

    //             Receipt::create([
    //                 'receipt_number' => $row[0] ?? null,
    //                 'mda_name' => $row[1] ?? null,
    //                 'eco_code' => $row[2] ?? null,
    //                 'eco_code_item' => $row[3] ?? null,
    //                 'activity' => $row[4] ?? null,
    //                 'amount' => (float) str_replace(',', '', $row[5] ?? 0),
    //                 'receipt_date' => $date,
    //                 'classification' => $row[7] ?? null,
    //                 'bank_name' => $row[8] ?? null,
    //                 'account_number' => $row[9] ?? null,
    //                 'account_name' => $row[10] ?? null,
    //             ]);

    //             $importedCount++;
    //         }

    //         DB::commit();
    //         fclose($handle);

    //         // Return redirect back with flash message (Inertia compatible)
    //         return redirect()->route('receipts.index')
    //             ->with('message', "Successfully imported {$importedCount} receipts. Skipped {$skippedCount} rows.");

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         if (isset($handle)) {
    //             fclose($handle);
    //         }

    //         return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
    //     }
    // }

    //     public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt|max:2048',
    //     ]);

    //     $file = $request->file('file');
    //     $handle = fopen($file->getRealPath(), 'r');

    //     // Read header
    //     $header = fgetcsv($handle);

    //     DB::beginTransaction();
    //     try {
    //         $importedCount = 0;
    //         $skippedCount = 0;
    //         $duplicateCount = 0;
    //         $errors = [];
    //         $lineNumber = 1; // Header is line 1

    //         while (($row = fgetcsv($handle)) !== false) {
    //             $lineNumber++;

    //             if (count($row) < 11) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Insufficient columns (expected 11, got " . count($row) . ")";
    //                 continue;
    //             }

    //             $receiptNumber = trim($row[0] ?? '');

    //             // Skip if receipt number is empty
    //             if (empty($receiptNumber)) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Empty receipt number";
    //                 continue;
    //             }

    //             // Check for duplicate receipt number
    //             if (Receipt::where('receipt_number', $receiptNumber)->exists()) {
    //                 $duplicateCount++;
    //                 $errors[] = "Line {$lineNumber}: Duplicate receipt number '{$receiptNumber}'";
    //                 continue;
    //             }

    //             // Parse date from DD-MM-YYYY to YYYY-MM-DD
    //             $date = null;
    //             if (!empty($row[6])) {
    //                 $dateParts = explode('-', $row[6]);
    //                 if (count($dateParts) === 3) {
    //                     // Validate date
    //                     if (checkdate($dateParts[1], $dateParts[0], $dateParts[2])) {
    //                         $date = "{$dateParts[2]}-{$dateParts[1]}-{$dateParts[0]}";
    //                     } else {
    //                         $skippedCount++;
    //                         $errors[] = "Line {$lineNumber}: Invalid date format '{$row[6]}' (expected DD-MM-YYYY)";
    //                         continue;
    //                     }
    //                 } else {
    //                     $skippedCount++;
    //                     $errors[] = "Line {$lineNumber}: Invalid date format '{$row[6]}' (expected DD-MM-YYYY)";
    //                     continue;
    //                 }
    //             }

    //             // Validate amount
    //             $amount = $row[5] ?? 0;
    //             if (!is_numeric(str_replace(',', '', $amount))) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Invalid amount '{$amount}'";
    //                 continue;
    //             }

    //             try {
    //                 Receipt::create([
    //                     'receipt_number' => $receiptNumber,
    //                     'mda_name' => $row[1] ?? null,
    //                     'eco_code' => $row[2] ?? null,
    //                     'eco_code_item' => $row[3] ?? null,
    //                     'activity' => $row[4] ?? null,
    //                     'amount' => (float) str_replace(',', '', $amount),
    //                     'receipt_date' => $date,
    //                     'classification' => $row[7] ?? null,
    //                     'bank_name' => $row[8] ?? null,
    //                     'account_number' => $row[9] ?? null,
    //                     'account_name' => $row[10] ?? null,
    //                 ]);

    //                 $importedCount++;

    //             } catch (\Exception $e) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Database error - " . $e->getMessage();
    //                 continue;
    //             }
    //         }

    //         DB::commit();
    //         fclose($handle);

    //         $message = "Import completed: {$importedCount} imported, {$skippedCount} skipped, {$duplicateCount} duplicates.";

    //         // If there were errors, store them in session
    //         if (!empty($errors)) {
    //             session()->flash('import_errors', array_slice($errors, 0, 20)); // Show first 20 errors
    //         }

    //         return redirect()->route('receipts.index')
    //             ->with('message', $message);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         if (isset($handle)) {
    //             fclose($handle);
    //         }

    //         return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
    //     }
    // }

    // public function import(Request $request)
    // {
    //     $request->validate([
    //         'file' => 'required|mimes:csv,txt|max:2048',
    //     ]);

    //     $file = $request->file('file');
    //     $handle = fopen($file->getRealPath(), 'r');

    //     // Read header
    //     $header = fgetcsv($handle);

    //     DB::beginTransaction();
    //     try {
    //         $importedCount = 0;
    //         $skippedCount = 0;
    //         $duplicateCount = 0;
    //         $errors = [];
    //         $duplicates = []; // Store duplicate receipt numbers
    //         $lineNumber = 1; // Header is line 1

    //         while (($row = fgetcsv($handle)) !== false) {
    //             $lineNumber++;

    //             if (count($row) < 11) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Insufficient columns (expected 11, got " . count($row) . ")";
    //                 continue;
    //             }

    //             $receiptNumber = trim($row[0] ?? '');

    //             // Skip if receipt number is empty
    //             if (empty($receiptNumber)) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Empty receipt number";
    //                 continue;
    //             }

    //             // Check for duplicate receipt number
    //             if (Receipt::where('receipt_number', $receiptNumber)->exists()) {
    //                 $duplicateCount++;
    //                 $duplicates[] = $receiptNumber; // Store duplicate number
    //                 $errors[] = "Line {$lineNumber}: Duplicate receipt number '{$receiptNumber}'";
    //                 continue;
    //             }

    //             // Parse date from DD-MM-YYYY to YYYY-MM-DD
    //             $date = null;
    //             if (!empty($row[6])) {
    //                 $dateParts = explode('-', $row[6]);
    //                 if (count($dateParts) === 3) {
    //                     // Validate date
    //                     if (checkdate($dateParts[1], $dateParts[0], $dateParts[2])) {
    //                         $date = "{$dateParts[2]}-{$dateParts[1]}-{$dateParts[0]}";
    //                     } else {
    //                         $skippedCount++;
    //                         $errors[] = "Line {$lineNumber}: Invalid date format '{$row[6]}' (expected DD-MM-YYYY)";
    //                         continue;
    //                     }
    //                 } else {
    //                     $skippedCount++;
    //                     $errors[] = "Line {$lineNumber}: Invalid date format '{$row[6]}' (expected DD-MM-YYYY)";
    //                     continue;
    //                 }
    //             }

    //             // Validate amount
    //             $amount = $row[5] ?? 0;
    //             if (!is_numeric(str_replace(',', '', $amount))) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Invalid amount '{$amount}'";
    //                 continue;
    //             }

    //             try {
    //                 Receipt::create([
    //                     'receipt_number' => $receiptNumber,
    //                     'mda_name' => $row[1] ?? null,
    //                     'eco_code' => $row[2] ?? null,
    //                     'eco_code_item' => $row[3] ?? null,
    //                     'activity' => $row[4] ?? null,
    //                     'amount' => (float) str_replace(',', '', $amount),
    //                     'receipt_date' => $date,
    //                     'classification' => $row[7] ?? null,
    //                     'bank_name' => $row[8] ?? null,
    //                     'account_number' => $row[9] ?? null,
    //                     'account_name' => $row[10] ?? null,
    //                 ]);

    //                 $importedCount++;

    //             } catch (\Exception $e) {
    //                 $skippedCount++;
    //                 $errors[] = "Line {$lineNumber}: Database error - " . $e->getMessage();
    //                 continue;
    //             }
    //         }

    //         DB::commit();
    //         fclose($handle);

    //         $message = "Import completed: {$importedCount} imported, {$skippedCount} skipped, {$duplicateCount} duplicates.";

    //         // Store data in session for Vue component
    //         $sessionData = [
    //             'message' => $message,
    //             'import_stats' => [
    //                 'imported' => $importedCount,
    //                 'skipped' => $skippedCount,
    //                 'duplicates' => $duplicateCount,
    //             ]
    //         ];

    //         // If there were errors, store them
    //         if (!empty($errors)) {
    //             $sessionData['import_errors'] = array_slice($errors, 0, 20);
    //         }

    //         // If there were duplicates, store them
    //         if (!empty($duplicates)) {
    //             $sessionData['duplicates'] = array_slice($duplicates, 0, 10);
    //         }

    //         return redirect()->route('receipts.index')
    //             ->with($sessionData);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         if (isset($handle)) {
    //             fclose($handle);
    //         }

    //         \Log::error('Import failed: ' . $e->getMessage());

    //         return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
    //     }
    // }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt|max:2048',
        ]);

        $file = $request->file('file');
        $handle = fopen($file->getRealPath(), 'r');

        // Skip header
        fgetcsv($handle);

        DB::beginTransaction();
        try {
            $importedCount = 0;
            $skippedCount = 0;
            $duplicateCount = 0;
            $duplicates = [];
            $lineNumber = 1; // Start from 1 since we skipped header

            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;

                if (count($row) < 11) {
                    $skippedCount++;
                    continue;
                }

                $receiptNumber = trim($row[0] ?? '');

                // Skip if receipt number is empty
                if (empty($receiptNumber)) {
                    $skippedCount++;
                    continue;
                }

                // Check for duplicate receipt number
                if (Receipt::where('receipt_number', $receiptNumber)->exists()) {
                    $duplicateCount++;
                    $duplicates[] = $receiptNumber;
                    \Log::info("Duplicate receipt number found: {$receiptNumber} at line {$lineNumber}");
                    continue;
                }

                // Parse date from DD-MM-YYYY to YYYY-MM-DD
                $date = null;
                if (!empty($row[6])) {
                    $dateParts = explode('-', $row[6]);
                    if (count($dateParts) === 3) {
                        $date = "{$dateParts[2]}-{$dateParts[1]}-{$dateParts[0]}";
                    }
                }

                Receipt::create([
                    'receipt_number' => $receiptNumber,
                    'mda_name' => $row[1] ?? null,
                    'eco_code' => $row[2] ?? null,
                    'eco_code_item' => $row[3] ?? null,
                    'activity' => $row[4] ?? null,
                    'amount' => (float) str_replace(',', '', $row[5] ?? 0),
                    'receipt_date' => $date,
                    'classification' => $row[7] ?? null,
                    'bank_name' => $row[8] ?? null,
                    'account_number' => $row[9] ?? null,
                    'account_name' => $row[10] ?? null,
                ]);

                $importedCount++;
            }

            DB::commit();
            fclose($handle);

            // Build success message
            $message = "Successfully imported {$importedCount} receipt(s).";

            if ($skippedCount > 0) {
                $message .= " Skipped {$skippedCount} row(s).";
            }

            if ($duplicateCount > 0) {
                $message .= " Found {$duplicateCount} duplicate receipt number(s).";
            }

            // Log for debugging
            \Log::info("Import completed: {$importedCount} imported, {$skippedCount} skipped, {$duplicateCount} duplicates");
            \Log::info("Duplicate numbers: " . implode(', ', $duplicates));

            // Return with ALL data in the session
            return redirect()->route('receipts.index')
                ->with('success', $message)
                ->with('imported_count', $importedCount)
                ->with('skipped_count', $skippedCount)
                ->with('duplicate_count', $duplicateCount)
                ->with('duplicates', $duplicates);
        } catch (\Exception $e) {
            DB::rollBack();
            if (isset($handle)) {
                fclose($handle);
            }

            \Log::error('Import failed: ' . $e->getMessage());

            return back()->withErrors(['file' => 'Import failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Parse amount from string to float
     */
    private function parseAmount($amount)
    {
        if (empty($amount)) {
            return 0.00;
        }

        // Convert to string if it's not already
        $amountString = (string) $amount;

        // Remove any non-numeric characters except decimal point and minus sign
        $cleanAmount = preg_replace('/[^\d\.\-]/', '', $amountString);

        // If empty after cleaning, return 0
        if (empty($cleanAmount)) {
            return 0.00;
        }

        return (float) $cleanAmount;
    }


    public function search(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 100);
            $search = $request->input('search', '');
            $query = Receipt::query();

            if ($search !== '') {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $term) {
                    $query->orWhere(function ($subQuery) use ($term) {
                        $subQuery->where('receipt_number', 'like', "%{$term}%")
                            ->orWhere('mda_name', 'like', "%{$term}%")
                            ->orWhere('account_name', 'like', "%{$term}%")
                            ->orWhere('eco_code', 'like', "%{$term}%")
                            ->orWhere('eco_code_item', 'like', "%{$term}%")
                            ->orWhere('activity', 'like', "%{$term}%")
                            ->orWhere('bank_name', 'like', "%{$term}%")
                            ->orWhere('account_number', 'like', "%{$term}%")
                            ->orWhere('classification', 'like', "%{$term}%")
                            ->orWhere('amount', 'like', "%{$term}%")
                            ->orWhere('status', 'like', "%{$term}%")
                            ->orWhere('receipt_date', 'like', "%{$term}%")
                            ->orWhere('tag', 'like', "%{$term}%");
                    });
                }
            }

            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('receipt_date', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('receipt_date', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
            }

            $perPage = $request->input('per_page', 20);

            // Paginate results
            $receipts = $query->latest()->paginate($perPage);


            // $mdas = Mda::select('name')->get();
            // $bank_activities = BankActivity::select('id', 'tag', 'bank_name', 'title', 'account_number')->get();
            // $receipt_activities = ReceiptActivity::select('id', 'name', 'status')->get();

            // Get all economy codes (for mapping)
            $economyCodes = EconomyCode::active()
                ->where('code', 'REGEXP', '^1[0-9]*$')
                ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$')
                ->get(['id', 'code', 'name'])
                ->keyBy('code') // Key by code for easy lookup
                ->toArray();

            // Get all economy code items (for mapping)
            $economyCodeItems = EconomyCodeItem::with(['economyCode' => function ($query) {
                $query->where('code', 'REGEXP', '^1[0-9]*$')
                    ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
            }])
                ->active()
                ->whereHas('economyCode', function ($query) {
                    $query->where('code', 'REGEXP', '^1[0-9]*$')
                        ->orWhere('code', 'REGEXP', '^1\\.[0-9]*$');
                })
                ->get(['id', 'economy_code_id', 'code', 'name'])
                ->keyBy('code') // Key by code for easy lookup
                ->toArray();

            $transformedReceipts = $receipts->getCollection()->map(function ($receipt) use ($economyCodes, $economyCodeItems) {
                // Find economy code ID based on eco_code string
                $economyCodeId = null;
                if ($receipt->eco_code && isset($economyCodes[$receipt->eco_code])) {
                    $economyCodeId = $economyCodes[$receipt->eco_code]['id'];
                }

                // Find economy code item ID based on eco_code_item string
                $economyCodeItemId = null;
                if ($receipt->eco_code_item && isset($economyCodeItems[$receipt->eco_code_item])) {
                    $economyCodeItemId = $economyCodeItems[$receipt->eco_code_item]['id'];
                }

                return [
                    'id' => $receipt->id,
                    'receipt_number' => $receipt->receipt_number,
                    'mda_name' => $receipt->mda_name,
                    'eco_code' => $receipt->eco_code,
                    'eco_code_item' => $receipt->eco_code_item,
                    'economy_code_id' => $economyCodeId,
                    'economy_code_item_id' => $economyCodeItemId,
                    'activity' => $receipt->activity,
                    'amount' => $receipt->amount,
                    'receipt_date' => $receipt->receipt_date,
                    'classification' => $receipt->classification,
                    'bank_name' => $receipt->bank_name,
                    'status' => $receipt->status,
                    'account_number' => $receipt->account_number,
                    'account_name' => $receipt->account_name,
                    'created_at' => $receipt->created_at,
                    'updated_at' => $receipt->updated_at,
                ];
            });


            // $economyCodesForDropdown = array_values(array_map(function ($code) {
            //     return [
            //         'id' => $code['id'],
            //         'code' => $code['code'],
            //         'name' => $code['name'],
            //     ];
            // }, $economyCodes));

            // Format economy code items for frontend dropdown
            // $formattedEconomyCodeItems = [];
            // foreach ($economyCodeItems as $item) {
            //     $formattedEconomyCodeItems[] = [
            //         'id' => $item['id'],
            //         'code' => $item['code'],
            //         'name' => $item['name'],
            //         'economy_code_id' => $item['economy_code_id'],
            //         'parent_code' => $item['economy_code']['code'] ?? null,
            //         'display_text' => $item['code'] . ' - ' . $item['name'] .
            //             ($item['economy_code'] ? ' (' . $item['economy_code']['code'] . ' - ' . $item['economy_code']['name'] . ')' : '')
            //     ];
            // }

            // $receipts = ['receipts' => [
            //         'data' => $transformedPaginator->items(),
            //         'current_page' => $transformedPaginator->currentPage(),
            //         'first_page_url' => $transformedPaginator->url(1),
            //         'from' => $transformedPaginator->firstItem(),
            //         'last_page' => $transformedPaginator->lastPage(),
            //         'last_page_url' => $transformedPaginator->url($transformedPaginator->lastPage()),
            //         'next_page_url' => $transformedPaginator->nextPageUrl(),
            //         'path' => $transformedPaginator->path(),
            //         'per_page' => $transformedPaginator->perPage(),
            //         'prev_page_url' => $transformedPaginator->previousPageUrl(),
            //         'to' => $transformedPaginator->lastItem(),
            //         'total' => $transformedPaginator->total(),
            //     ],
            // 'mdas' => $mdas,
            // 'bank_activities' => $bank_activities,
            // 'receipt_activities' => $receipt_activities,
            // 'economyCodes' => $economyCodesForDropdown,
            // 'economyCodeItems' => $formattedEconomyCodeItems, ];




            // $receipts = $query->with(['mda'])
            //     ->orderBy('created_at', 'desc')
            //     ->paginate($perPage)
            //     ->through(function ($receipt) {
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
            $transformedPaginator = new \Illuminate\Pagination\LengthAwarePaginator(
                $transformedReceipts,
                $receipts->total(),
                $receipts->perPage(),
                $receipts->currentPage(),
                ['path' => $receipts->path()]
            );
            $paginator = [
                "total" => $receipts->total(),
                "per_page" => $receipts->perPage(),
                "current_page" => $receipts->currentPage(),
                "last_page" => $receipts->lastPage(),
                "first_page_url" => $receipts->url(1),
                "last_page_url" => $receipts->url($receipts->lastPage()),
                "next_page_url" => $receipts->nextPageUrl(),
                "prev_page_url" => $receipts->previousPageUrl(),
                "path" => $receipts->path(),
                "from" => $receipts->currentPage(),
                "to" => $receipts->perPage(),
            ];

            // Log search activity
            $this->activityLogger->log(
                "Searched receipts",
                [
                    'search_term' => $search,
                    'results_count' => $receipts->total(),
                    'per_page' => $perPage,
                    'user_id' => auth()->id()
                ],
                'receipts'
            );


            return response()->json([
                'status' => 'success',
                'receipts' => $transformedPaginator->items(),
                // 'mdas' => $mdas,
                // 'bank_activities' => $bank_activities,
                // 'receipt_activities' => $receipt_activities,
                // 'economyCodes' => $economyCodesForDropdown,
                // 'economyCodeItems' => $formattedEconomyCodeItems,
                'paginator' => $paginator
            ]);
        } catch (\Exception $e) {
            \Log::error('Receipt Search Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Receipt Index Error: ' . $e->getMessage()]);
        }
    }
}
