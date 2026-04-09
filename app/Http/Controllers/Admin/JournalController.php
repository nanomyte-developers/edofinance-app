<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJournalRequest;
use App\Http\Requests\UpdateJournalRequest;
use App\Models\Journal;
use App\Services\ActivityLogger;
use App\Services\JournalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use App\Models\Mda;
use Illuminate\Support\Facades\Auth;

class JournalController extends Controller
{
    protected $journalService;

    protected $activityLogger;

    public function __construct(JournalService $journalService, ActivityLogger $activityLogger)
    {
        $this->journalService = $journalService;
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display a listing of the journals.
     */
    public function index(Request $request)
    {
        Log::info('JournalController index called');
        Log::info('Request data:', $request->all());

        // Log activity
        $this->activityLogger->log(
            'Viewed journals list',
            [
                'search' => $request->input('search', ''),
                'per_page' => $request->input('per_page', 20),
                'filters' => $request->except(['search', 'per_page', 'page']),
                'user_id' => auth()->id(),
            ],
            'journal'
        );

        // Get GL accounts for dropdowns
        // $glAccounts = $this->journalService->getGlAccounts();

        // Log::info('GL accounts loaded:', ['count' => $glAccounts->count()]);

        // Get per_page from request or use default
        $perPage = $request->input('per_page', 20);

        // Get filters from request
        $filters = $request->only([
            'search',
            'date_from',
            'date_to',
            'status',
            'department_id',
            'account_code',
            'financial_year',
            'min_amount',
            'max_amount',
            'sort_by',
            'sort_order',
        ]);

        // Get paginated journals through service
        $journals = $this->journalService->getAllJournals($filters, $perPage);

        // Transform journals with edit/delete permissions
        $transformedJournals = $journals->through(function ($journal) {
            // Handle date conversion safely
            $journalDate = null;
            $postingDate = null;

            if ($journal->journal_date) {
                try {
                    if (is_string($journal->journal_date)) {
                        $journalDate = \Carbon\Carbon::parse($journal->journal_date)->toDateString();
                    } elseif (method_exists($journal->journal_date, 'toDateString')) {
                        $journalDate = $journal->journal_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing journal_date', [
                        'journal_id' => $journal->id,
                        'journal_date' => $journal->journal_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($journal->posting_date) {
                try {
                    if (is_string($journal->posting_date)) {
                        $postingDate = \Carbon\Carbon::parse($journal->posting_date)->toDateString();
                    } elseif (method_exists($journal->posting_date, 'toDateString')) {
                        $postingDate = $journal->posting_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing posting_date', [
                        'journal_id' => $journal->id,
                        'posting_date' => $journal->posting_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return [
                'id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'journal_date' => $journalDate,
                'posting_date' => $postingDate,
                'description' => $journal->description,
                'total_amount' => $journal->total_amount,
                'total_debit' => $journal->total_debit,
                'total_credit' => $journal->total_credit,
                'status' => $journal->status,
                'journal_type' => $journal->journal_type,
                'reference_number' => $journal->reference_number,
                'batch_number' => $journal->batch_number,
                'financial_year' => $journal->financial_year,
                'remarks' => $journal->remarks,
                'department_id' => $journal->department_id,
                'department' => $journal->department ? [
                    'id' => $journal->department->id,
                    'name' => $journal->department->name,
                    'code' => $journal->department->code,
                ] : null,
                'creator' => $journal->creator ? [
                    'id' => $journal->creator->id,
                    'name' => $journal->creator->name,
                    'email' => $journal->creator->email,
                ] : null,
                'approver' => $journal->approver ? [
                    'id' => $journal->approver->id,
                    'name' => $journal->approver->name,
                    'email' => $journal->approver->email,
                ] : null,
                // Add permission flags based on status
                'can_edit' => $journal->canEdit(),
                'can_delete' => $journal->canDelete(),
                'is_balanced' => $journal->isBalanced(),
                'balance_difference' => abs($journal->total_debit - $journal->total_credit),
                'entry_count' => $journal->entries_count ?? 0,
                'created_at' => $journal->created_at ?
                    (is_string($journal->created_at) ?
                        $journal->created_at :
                        $journal->created_at->toDateTimeString()) : null,
                'updated_at' => $journal->updated_at ?
                    (is_string($journal->updated_at) ?
                        $journal->updated_at :
                        $journal->updated_at->toDateTimeString()) : null,
                'approved_at' => $journal->approved_at ?
                    (is_string($journal->approved_at) ?
                        $journal->approved_at :
                        $journal->approved_at->toDateTimeString()) : null,

                'mda' => $journal->mda ? [
                    'id' => $journal->mda->id,
                    'name' => $journal->mda->name,
                    'code' => $journal->mda->code,
                ] : null,
                'administrative_sector_code' => $journal->administrativeSectorCode ? [
                    'id' => $journal->administrativeSectorCode->id,
                    'code' => $journal->administrativeSectorCode->code,
                    'name' => $journal->administrativeSectorCode->name,
                ] : null,

                'administrative_code' => $journal->administrativeCode ? [
                    'id' => $journal->administrativeCode->id,
                    'code' => $journal->administrativeCode->code,
                    'name' => $journal->administrativeCode->name,
                ] : null,

            ];
        });


        // 1. Fetch Administrative Codes
        $administrativeCodes = DB::table('administrative_codes')
            ->select('id', 'name', 'code')
            ->where('status', 1)
            ->orderBy('code')
            ->get()
            ->toArray();

        // 2. Fetch Administrative Sector Codes (Budget Head Codes)
        $administrativeSectorCodes = DB::table('administrative_sector_codes')
            ->select('id', 'code', 'name', 'administrative_code_id', 'initials')
            ->where('status', 1)
            ->orderBy('code')
            ->get()
            ->toArray();

        // 3. Fetch MDAs
        $mdas = Mda::select('id', 'name', 'administrative_code_id')
            ->orderBy('name')
            ->get();

        // Get statistics and filter options
        $statistics = $this->journalService->getStatistics();
        $filterOptions = $this->journalService->getFilterOptions();

        // Transform for Inertia
        return Inertia::render('admin/journals/index', [
            'journals' => [
                'data' => $transformedJournals->items(),
                'current_page' => $journals->currentPage(),
                'first_page_url' => $journals->url(1),
                'from' => $journals->firstItem(),
                'last_page' => $journals->lastPage(),
                'last_page_url' => $journals->url($journals->lastPage()),
                'next_page_url' => $journals->nextPageUrl(),
                'path' => $journals->path(),
                'per_page' => $journals->perPage(),
                'prev_page_url' => $journals->previousPageUrl(),
                'to' => $journals->lastItem(),
                'total' => $journals->total(),
            ],
            'filters' => $filters,
            // 'gl_accounts' => $glAccounts,
            'filter_options' => array_merge($filterOptions, [
                'per_page_options' => [10, 20, 50, 100],
                'sort_options' => [
                    'journal_date' => 'Journal Date',
                    'posting_date' => 'Posting Date',
                    'journal_number' => 'Journal Number',
                    'total_amount' => 'Amount',
                    'created_at' => 'Created Date',
                ],
            ]),
            'statistics' => $statistics,
            'administrativeCodes' => $administrativeCodes,
            'administrativeSectorCodes' => $administrativeSectorCodes,
            'mdas' => $mdas,
        ]);
    }


    public function store(StoreJournalRequest $request)
    {
        Log::info('=== START Journal Creation ===');
        Log::info('Request data:', $request->all());
        Log::info('Validated data:', $request->validated());
        Log::info('Entries data:', $request->input('entries', []));

        try {
            $validated = $request->validated();
            Log::info('Validated data for service:', $validated);
            // dd($validated);
            // Create through service
            $journal = $this->journalService->createJournal($validated);
            Log::info('Journal created successfully', ['id' => $journal->id]);

            // Log creation activity
            $this->activityLogger->log(
                "Created journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'total_amount' => $journal->total_amount,
                    'total_debit' => $journal->total_debit,
                    'total_credit' => $journal->total_credit,
                    'status' => $journal->status,
                    'entry_count' => $journal->entries->count(),
                    'created_by' => auth()->id(),
                ],
                'journal'
            );

            Log::info('=== END Journal Creation (Success) ===');



            // return redirect()
            //     ->route('journals.index')
            //     ->with('flash', [
            //         'message' => 'Journal created successfully!',
            //         'type' => 'success',
            //         'success' => true
            //     ]);

            return response()->json([
                'success' => true,
                'data' => $journal,
                'type' => 'success'
            ]);
        } catch (\Exception $e) {
            Log::error('=== Journal Creation Failed ===');
            Log::error('Error message: ' . $e->getMessage());
            Log::error('Error trace: ' . $e->getTraceAsString());
            Log::error('Error data: ', $request->all());
            Log::error('=== END Journal Creation (Failed) ===');

            // Log failed creation attempt
            $this->activityLogger->log(
                'Failed to create journal',
                [
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                    'data_keys' => array_keys($request->all()),
                ],
                'journal'
            );

            // return back()
            //     ->withErrors(['error' => 'Failed to create journal. ' . $e->getMessage()])
            //     ->withInput();

            return response()->json([
                'success' => true,
                'data' => $request->all(),
                'type' => 'success'
            ]);
        }
    }

    /**
     * Get journal data for editing
     */
    // public function edit($id)
    // {
    //     try {
    //         Log::info('Fetching journal for edit', ['journal_id' => $id]);

    //         // Load journal with all necessary relationships
    //         $journal = Journal::with([
    //             'entries' => function ($query) {
    //                 $query->select([
    //                     'id',
    //                     'journal_id',
    //                     'economic_code_id',
    //                     'account_code',
    //                     'description',
    //                     'debit_amount',
    //                     'credit_amount',
    //                     'cost_center',
    //                     'project_code',
    //                     'reference',
    //                     'tax_code',
    //                     'tax_amount',
    //                 ])->orderBy('id');
    //             },
    //             'creator:id,name,email',
    //             'approver:id,name,email',
    //             'mda:id,name,code',
    //             'economicCode:id,name,code',
    //             'administrativeCode:id,name,code',
    //             'administrativeSectorCode:id,name,code,type',
    //         ])->findOrFail($id);

    //         // Check if journal can be edited
    //         if (! $journal->canEdit()) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => "Journal cannot be edited because its status is '{$journal->status}'",
    //             ], 403);
    //         }

    //         // Format dates safely
    //         $journalDate = null;
    //         $postingDate = null;

    //         if ($journal->journal_date) {
    //             try {
    //                 if (is_string($journal->journal_date)) {
    //                     $journalDate = \Carbon\Carbon::parse($journal->journal_date)->toDateString();
    //                 } elseif (method_exists($journal->journal_date, 'toDateString')) {
    //                     $journalDate = $journal->journal_date->toDateString();
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::warning('Error parsing journal_date in edit', [
    //                     'journal_id' => $journal->id,
    //                     'journal_date' => $journal->journal_date,
    //                     'error' => $e->getMessage(),
    //                 ]);
    //             }
    //         }

    //         if ($journal->posting_date) {
    //             try {
    //                 if (is_string($journal->posting_date)) {
    //                     $postingDate = \Carbon\Carbon::parse($journal->posting_date)->toDateString();
    //                 } elseif (method_exists($journal->posting_date, 'toDateString')) {
    //                     $postingDate = $journal->posting_date->toDateString();
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::warning('Error parsing posting_date in edit', [
    //                     'journal_id' => $journal->id,
    //                     'posting_date' => $journal->posting_date,
    //                     'error' => $e->getMessage(),
    //                 ]);
    //             }
    //         }

    //         // Prepare entries data
    //         $entries = [];
    //         if ($journal->entries && $journal->entries->isNotEmpty()) {
    //             $entries = $journal->entries->map(function ($entry) {
    //                 return [
    //                     'id' => $entry->id,
    //                     'economic_code_id' => $entry->economic_code_id,
    //                     'account_code' => $entry->account_code,
    //                     'description' => $entry->description ?? '',
    //                     'debit_amount' => (float) $entry->debit_amount,
    //                     'credit_amount' => (float) $entry->credit_amount,
    //                     'cost_center' => $entry->cost_center,
    //                     'project_code' => $entry->project_code,
    //                     'reference' => $entry->reference,
    //                     'tax_code' => $entry->tax_code,
    //                     'tax_amount' => (float) $entry->tax_amount,
    //                 ];
    //             })->toArray();
    //         }

    //         // Prepare the response data structure
    //         $journalData = [
    //             'id' => $journal->id,
    //             'journal_number' => $journal->journal_number,
    //             'journal_date' => $journalDate,
    //             'posting_date' => $postingDate,
    //             'description' => $journal->description,
    //             'remarks' => $journal->remarks,
    //             'reference_number' => $journal->reference_number,
    //             'batch_number' => $journal->batch_number,
    //             'financial_year' => $journal->financial_year,
    //             'mda_id' => $journal->mda_id,
    //             'economic_code_id' => $journal->economic_code_id,
    //             'administrative_code_id' => $journal->administrative_code_id,
    //             'administrative_sector_code_id' => $journal->administrative_sector_code_id,
    //             'status' => $journal->status,
    //             'is_recurring' => $journal->is_recurring,
    //             'recurring_frequency' => $journal->recurring_frequency,
    //             'next_recurring_date' => $journal->next_recurring_date,
    //             'total_amount' => (float) $journal->total_amount,
    //             'total_debit' => (float) $journal->total_debit,
    //             'total_credit' => (float) $journal->total_credit,
    //             'entries' => $entries,
    //             'mda' => $journal->mda ? [
    //                 'id' => $journal->mda->id,
    //                 'name' => $journal->mda->name,
    //                 'code' => $journal->mda->code,
    //             ] : null,
    //             'economic_code' => $journal->economicCode ? [
    //                 'id' => $journal->economicCode->id,
    //                 'name' => $journal->economicCode->name,
    //                 'code' => $journal->economicCode->code,
    //             ] : null,
    //             'administrative_code' => $journal->administrativeCode ? [
    //                 'id' => $journal->administrativeCode->id,
    //                 'name' => $journal->administrativeCode->name,
    //                 'code' => $journal->administrativeCode->code,
    //             ] : null,
    //             'administrative_sector_code' => $journal->administrativeSectorCode ? [
    //                 'id' => $journal->administrativeSectorCode->id,
    //                 'name' => $journal->administrativeSectorCode->name,
    //                 'code' => $journal->administrativeSectorCode->code,
    //                 'type' => $journal->administrativeSectorCode->type,
    //             ] : null,
    //             'creator' => $journal->creator ? [
    //                 'id' => $journal->creator->id,
    //                 'name' => $journal->creator->name,
    //                 'email' => $journal->creator->email,
    //             ] : null,
    //             'approver' => $journal->approver ? [
    //                 'id' => $journal->approver->id,
    //                 'name' => $journal->approver->name,
    //                 'email' => $journal->approver->email,
    //             ] : null,
    //             'can_edit' => $journal->canEdit(),
    //             'can_delete' => $journal->canDelete(),
    //             'is_balanced' => $journal->isBalanced(),
    //             'balance_difference' => abs($journal->total_debit - $journal->total_credit),
    //             'entry_count' => $journal->entries->count(),
    //             'created_at' => $journal->created_at ? $journal->created_at->toDateTimeString() : null,
    //             'updated_at' => $journal->updated_at ? $journal->updated_at->toDateTimeString() : null,
    //             'approved_at' => $journal->approved_at ? $journal->approved_at->toDateTimeString() : null,
    //         ];

    //         // Log successful fetch
    //         $this->activityLogger->log(
    //             "Fetched journal {$journal->journal_number} for editing",
    //             [
    //                 'journal_id' => $journal->id,
    //                 'journal_number' => $journal->journal_number,
    //                 'status' => $journal->status,
    //                 'entry_count' => $journal->entries->count(),
    //                 'fetched_by' => auth()->id(),
    //             ],
    //             'journal'
    //         );

    //         Log::info('Journal data fetched successfully for edit', ['journal_id' => $journal->id]);

    //         return response()->json([
    //             'success' => true,
    //             'data' => $journalData,
    //         ]);

    //     } catch (\Exception $e) {
    //         Log::error('Error fetching journal for edit: '.$e->getMessage(), [
    //             'journal_id' => $id,
    //             'error' => $e->getTraceAsString(),
    //         ]);

    //         // Log failed edit attempt
    //         $this->activityLogger->log(
    //             'Failed to fetch journal for editing',
    //             [
    //                 'journal_id' => $id,
    //                 'error' => $e->getMessage(),
    //                 'attempted_by' => auth()->id(),
    //             ],
    //             'journal'
    //         );

    //         return response()->json([
    //             'success' => false,
    //             'message' => $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException
    //                 ? 'Journal not found.'
    //                 : 'Failed to load journal for editing. Please try again.',
    //         ], 404);
    //     }
    // }

    /**
     * Get journal data for editing (JSON response for Vue modal)
     */
    public function editData(Journal $journal)
    {
        try {
            // Check if journal can be edited
            if (!$journal->canEdit() &&  !Auth::user()->hasRole('admin')) {
                return response()->json([
                    'success' => false,
                    'message' => "Journal cannot be edited because its status is '{$journal->status}'",
                ], 403);
            }

            // Load all necessary relationships
            $journal->load([
                'entries' => function ($query) {
                    $query->orderBy('id');
                },
                'mda',
                'economicCode',
                'administrativeCode',
                'administrativeSectorCode',
            ]);

            // Format the response
            $data = [
                'id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'journal_date' => $journal->journal_date ? $journal->journal_date->format('Y-m-d') : null,
                'posting_date' => $journal->posting_date ? $journal->posting_date->format('Y-m-d') : null,
                'description' => $journal->description,
                'remarks' => $journal->remarks,
                'reference_number' => $journal->reference_number,
                'batch_number' => $journal->batch_number,
                'financial_year' => $journal->financial_year,
                'journal_type' => $journal->journal_type,
                'mda_id' => $journal->mda_id,
                'economic_code_id' => $journal->economic_code_id,
                'administrative_code_id' => $journal->administrative_code_id,
                'administrative_sector_code_id' => $journal->administrative_sector_code_id,
                'status' => $journal->status,
                'entries' => $journal->entries->map(function ($entry) {
                    $entry->load(['EconomicCodeItem']);

                    // dd($entry->EconomicCodeItem->economy_code_id);

                    return [
                        'id' => $entry->id,
                        'economic_code_id' =>  $entry->EconomicCodeItem->economy_code_id,
                        'account_code' => $entry->account_code,
                        'description' => $entry->description,
                        'debit_amount' => (float) $entry->debit_amount,
                        'credit_amount' => (float) $entry->credit_amount,
                        'cost_center' => $entry->cost_center,
                        'project_code' => $entry->project_code,
                        'reference' => $entry->reference,
                        'tax_code' => $entry->tax_code,
                        'tax_amount' => (float) $entry->tax_amount,
                    ];
                })->toArray(),
                'total_amount' => (float) $journal->total_amount,
                'total_debit' => (float) $journal->total_debit,
                'total_credit' => (float) $journal->total_credit,
            ];

            // Log the activity
            $this->activityLogger->log(
                "Fetched journal {$journal->journal_number} for editing",
                [
                    'journal_id' => $journal->id,
                    'status' => $journal->status,
                    'entry_count' => $journal->entries->count(),
                ],
                'journal'
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching journal for edit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load journal data for editing.',
            ], 500);
        }
    }

    /**
     * Update the specified journal in storage.
     */
    public function update(UpdateJournalRequest $request, Journal $journal)
    {
        Log::info('Updating journal', ['id' => $journal->id, 'data' => $request->all()]);

        // Check if journal can be edited
        if (! $journal->canEdit() && !Auth::user()->hasRole('admin')) {
            Log::warning('Attempt to edit non-editable journal', [
                'journal_id' => $journal->id,
                'status' => $journal->status,
                'user_id' => auth()->id(),
            ]);

            // Log unauthorized edit attempt
            $this->activityLogger->log(
                "Unauthorized edit attempt on journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'status' => $journal->status,
                    'attempted_by' => auth()->id(),
                    'reason' => 'Status prevents editing',
                ],
                'journal'
            );

            return back()
                ->withErrors(['error' => 'This journal cannot be edited because of its current status.'])
                ->withInput();
        }

        // Store original data for logging changes
        $originalData = [
            'status' => $journal->status,
            'total_amount' => $journal->total_amount,
            'total_debit' => $journal->total_debit,
            'total_credit' => $journal->total_credit,
            'journal_number' => $journal->journal_number,
            'description' => $journal->description,
            'journal_date' => $journal->journal_date,
            'posting_date' => $journal->posting_date,
        ];

        $changes = [];

        try {
            $validated = $request->validated();

            // Update through service
            $updatedJournal = $this->journalService->updateJournal($journal, $validated);

            // Determine what changed
            foreach ($validated as $key => $value) {
                if (isset($originalData[$key]) && $originalData[$key] != $value && $key !== 'updated_at') {
                    $changes[$key] = [
                        'from' => $originalData[$key],
                        'to' => $value,
                    ];
                }
            }

            // Log update activity
            $this->activityLogger->log(
                "Updated journal {$updatedJournal->journal_number}",
                [
                    'journal_id' => $updatedJournal->id,
                    'journal_number' => $updatedJournal->journal_number,
                    'changes' => $changes,
                    'updated_by' => auth()->id(),
                    'old_status' => $originalData['status'] ?? null,
                    'new_status' => $updatedJournal->status,
                    'entry_count' => $updatedJournal->entries->count(),
                ],
                'journal'
            );

            // Log specific action with detailed changes
            $this->activityLogger->logAction('updated', $updatedJournal, [
                'changes' => $changes,
                'updated_by' => auth()->id(),
                'amount_changed' => isset($validated['total_amount']) ?
                    ['from' => $originalData['total_amount'], 'to' => $validated['total_amount']] : null,
                'entry_count' => $updatedJournal->entries->count(),
            ]);

            Log::info('Journal updated successfully', ['id' => $updatedJournal->id]);

            // return redirect()
            //     ->route('journals.index')
            //     ->with('flash', [
            //         'message' => 'Journal updated successfully!',
            //         'type' => 'success',
            //     ]);

            return response()->json([
                'success' => true,
                'data' => $updatedJournal,
            ]);
        } catch (\Exception $e) {
            Log::error('Error updating journal: ' . $e->getMessage(), [
                'journal_id' => $journal->id,
                'error' => $e->getTraceAsString(),
            ]);

            // Log failed update attempt
            $this->activityLogger->log(
                "Failed to update journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                ],
                'journal'
            );

            return back()
                ->withErrors(['error' => 'Failed to update journal. ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Remove the specified journal from storage.
     */
    public function destroy($id)
    {
        Log::info('Deleting journal', ['id' => $id]);

        try {
            $journal = Journal::with('entries')->findOrFail($id);

            // Check if journal can be deleted
            if (! $journal->canDelete()) {
                Log::warning('Attempt to delete non-deletable journal', [
                    'journal_id' => $journal->id,
                    'status' => $journal->status,
                    'user_id' => auth()->id(),
                ]);

                // Log unauthorized delete attempt
                $this->activityLogger->log(
                    "Unauthorized delete attempt on journal {$journal->journal_number}",
                    [
                        'journal_id' => $journal->id,
                        'journal_number' => $journal->journal_number,
                        'status' => $journal->status,
                        'attempted_by' => auth()->id(),
                        'reason' => 'Status prevents deletion',
                    ],
                    'journal'
                );

                return back()
                    ->withErrors(['error' => 'This journal cannot be deleted because of its current status.']);
            }

            $journalNumber = $journal->journal_number;
            $journalData = $journal->toArray();

            // Log before deletion
            $this->activityLogger->log(
                "Attempting to delete journal {$journalNumber}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journalNumber,
                    'total_amount' => $journal->total_amount,
                    'status' => $journal->status,
                    'entry_count' => $journal->entries->count(),
                    'deleted_by' => auth()->id(),
                ],
                'journal'
            );

            // Delete through service
            $this->journalService->deleteJournal($journal);

            // Log successful deletion
            $this->activityLogger->log(
                "Deleted journal {$journalNumber}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journalNumber,
                    'total_amount' => $journalData['total_amount'],
                    'status' => $journalData['status'],
                    'department_id' => $journalData['department_id'],
                    'financial_year' => $journalData['financial_year'],
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now(),
                ],
                'journal'
            );

            // Log specific delete action
            $this->activityLogger->logAction('deleted', $journal, [
                'journal_number' => $journalNumber,
                'total_amount' => $journalData['total_amount'],
                'entry_count' => $journal->entries->count(),
                'deleted_by' => auth()->id(),
            ]);

            Log::info('Journal deleted successfully');

            return redirect()
                ->route('journals.index')
                ->with('flash', [
                    'message' => 'Journal deleted successfully!',
                    'type' => 'info',
                ]);
        } catch (\Exception $e) {
            Log::error('Error deleting journal: ' . $e->getMessage(), [
                'journal_id' => $id,
                'error' => $e->getTraceAsString(),
            ]);

            // Log failed deletion
            $this->activityLogger->log(
                'Failed to delete journal',
                [
                    'journal_id' => $id,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                ],
                'journal'
            );

            return back()
                ->withErrors(['error' => 'Failed to delete journal. ' . $e->getMessage()]);
        }
    }

    /**
     * Show the specified journal.
     */
    public function show($id)
    {
        Log::info('Showing journal', ['id' => $id]);

        try {
            $journal = Journal::with([
                // 'entries.glAccount',
                'creator',
                'approver',
                // 'department',
                // 'glCategory',
            ])->findOrFail($id);

            // Format dates safely
            $journalDate = null;
            $postingDate = null;

            if ($journal->journal_date) {
                try {
                    if (is_string($journal->journal_date)) {
                        $journalDate = \Carbon\Carbon::parse($journal->journal_date)->toDateString();
                    } elseif (method_exists($journal->journal_date, 'toDateString')) {
                        $journalDate = $journal->journal_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing journal_date in show', [
                        'journal_id' => $journal->id,
                        'journal_date' => $journal->journal_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($journal->posting_date) {
                try {
                    if (is_string($journal->posting_date)) {
                        $postingDate = \Carbon\Carbon::parse($journal->posting_date)->toDateString();
                    } elseif (method_exists($journal->posting_date, 'toDateString')) {
                        $postingDate = $journal->posting_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing posting_date in show', [
                        'journal_id' => $journal->id,
                        'posting_date' => $journal->posting_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Prepare journal entries data
            $entries = $journal->entries->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'account_code' => $entry->account_code,
                    'account_name' => $entry->account_name,
                    'description' => $entry->description,
                    'debit_amount' => (float) $entry->debit_amount,
                    'credit_amount' => (float) $entry->credit_amount,
                    'line_number' => $entry->line_number,
                    'cost_center' => $entry->cost_center,
                    'project_code' => $entry->project_code,
                    'reference' => $entry->reference,
                    'tax_code' => $entry->tax_code,
                    'tax_amount' => (float) $entry->tax_amount,
                    'net_amount' => (float) $entry->net_amount,
                    'entry_type' => $entry->entry_type,
                    'created_at' => $entry->created_at ?
                        (is_string($entry->created_at) ?
                            $entry->created_at :
                            $entry->created_at->toDateTimeString()) : null,
                    // 'gl_account' => $entry->glAccount ? [
                    //     'id' => $entry->glAccount->id,
                    //     'account_code' => $entry->glAccount->account_code,
                    //     'account_name' => $entry->glAccount->account_name,
                    //     'account_type' => $entry->glAccount->account_type,
                    //     'normal_balance' => $entry->glAccount->normal_balance,
                    //     'current_balance' => (float) $entry->glAccount->current_balance,
                    //     'formatted_type' => $entry->glAccount->formatted_account_type,
                    // ] : null,
                ];
            });

            // Prepare journal data
            $journalData = [
                'id' => $journal->id,
                'journal_number' => $journal->journal_number,
                'journal_date' => $journalDate,
                'posting_date' => $postingDate,
                'description' => $journal->description,
                'total_amount' => $journal->total_amount,
                'total_debit' => $journal->total_debit,
                'total_credit' => $journal->total_credit,
                'status' => $journal->status,
                'reference_number' => $journal->reference_number,
                'batch_number' => $journal->batch_number,
                'financial_year' => $journal->financial_year,
                'remarks' => $journal->remarks,
                'source_document' => $journal->source_document,
                'is_recurring' => $journal->is_recurring,
                'recurring_frequency' => $journal->recurring_frequency,
                'next_recurring_date' => $journal->next_recurring_date,
                'department_id' => $journal->department_id,
                'department' => $journal->department ? [
                    'id' => $journal->department->id,
                    'name' => $journal->department->name,
                    'code' => $journal->department->code,
                ] : null,
                // 'gl_category' => $journal->glCategory ? [
                //     'id' => $journal->glCategory->id,
                //     'category_code' => $journal->glCategory->category_code,
                //     'category_name' => $journal->glCategory->category_name,
                // ] : null,
                'creator' => $journal->creator ? [
                    'id' => $journal->creator->id,
                    'name' => $journal->creator->name,
                    'email' => $journal->creator->email,
                ] : null,
                'approver' => $journal->approver ? [
                    'id' => $journal->approver->id,
                    'name' => $journal->approver->name,
                    'email' => $journal->approver->email,
                ] : null,
                'entries' => $entries,
                'can_edit' => $journal->canEdit(),
                'can_delete' => $journal->canDelete(),
                'is_balanced' => $journal->isBalanced(),
                'balance_difference' => abs($journal->total_debit - $journal->total_credit),
                'entry_count' => $journal->entries->count(),
                'created_at' => $journal->created_at ?
                    (is_string($journal->created_at) ?
                        $journal->created_at :
                        $journal->created_at->toDateTimeString()) : null,
                'updated_at' => $journal->updated_at ?
                    (is_string($journal->updated_at) ?
                        $journal->updated_at :
                        $journal->updated_at->toDateTimeString()) : null,
                'approved_at' => $journal->approved_at ?
                    (is_string($journal->approved_at) ?
                        $journal->approved_at :
                        $journal->approved_at->toDateTimeString()) : null,
            ];

            // Get GL accounts for dropdowns
            // $glAccounts = $this->journalService->getGlAccounts();

            // Get departments for dropdowns
            // $departments = \App\Models\Department::select(['id', 'name', 'code'])
            //     ->where('is_active', true)
            //     ->orderBy('name')
            //     ->get()
            //     ->map(function ($dept) {
            //         return [
            //             'id' => $dept->id,
            //             'name' => $dept->name,
            //             'code' => $dept->code,
            //             'searchLabel' => "{$dept->code} - {$dept->name}",
            //         ];
            //     });

            // // Get GL categories for dropdowns
            // $glCategories = \App\Models\GlCategory::select(['id', 'category_code', 'category_name'])
            //     ->where('is_active', true)
            //     ->orderBy('category_code')
            //     ->get()
            //     ->map(function ($category) {
            //         return [
            //             'id' => $category->id,
            //             'category_code' => $category->category_code,
            //             'category_name' => $category->category_name,
            //             'searchLabel' => "{$category->category_code} - {$category->category_name}",
            //         ];
            //     });

            // Log view activity
            $this->activityLogger->log(
                "Viewed journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'total_amount' => $journal->total_amount,
                    'status' => $journal->status,
                    'viewed_by' => auth()->id(),
                ],
                'journal'
            );

            return Inertia::render('admin/journals/show', [
                'journal' => $journalData,
                // 'gl_accounts' => $glAccounts,
                // 'departments' => $departments,
                // 'gl_categories' => $glCategories,
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing journal: ' . $e->getMessage());

            // Log failed view attempt
            $this->activityLogger->log(
                'Failed to view journal',
                [
                    'journal_id' => $id,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                ],
                'journal'
            );

            return redirect()
                ->route('journals.index')
                ->with('flash', [
                    'message' => 'Journal not found!',
                    'type' => 'error',
                ]);
        }
    }
    /**
     * Show the specified journal.
     */
    // public function show(Request $request, $id)
    // {
    //     Log::info('Showing journal', ['id' => $id]);

    //     try {
    //         $journal = Journal::with([
    //             'entries' => function ($query) {
    //                 $query->select([
    //                     'id',
    //                     'journal_id',
    //                     'economic_code_id',
    //                     'account_code',
    //                     'description',
    //                     'debit_amount',
    //                     'credit_amount',
    //                     'cost_center',
    //                     'project_code',
    //                     'reference',
    //                     'tax_code',
    //                     'tax_amount',
    //                 ]);
    //             },
    //             'creator',
    //             'approver',
    //         ])->findOrFail($id);

    //         // If it's an API request (for editing), return JSON
    //         if ($request->expectsJson() || $request->is('api/*')) {
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [
    //                     'id' => $journal->id,
    //                     'journal_number' => $journal->journal_number,
    //                     'journal_date' => $journal->journal_date,
    //                     'posting_date' => $journal->posting_date,
    //                     'description' => $journal->description,
    //                     'remarks' => $journal->remarks,
    //                     'reference_number' => $journal->reference_number,
    //                     'batch_number' => $journal->batch_number,
    //                     'financial_year' => $journal->financial_year,
    //                     'mda_id' => $journal->mda_id,
    //                     'economic_code_id' => $journal->economic_code_id,
    //                     'administrative_code_id' => $journal->administrative_code_id,
    //                     'administrative_sector_code_id' => $journal->administrative_sector_code_id,
    //                     'status' => $journal->status,
    //                     'entries' => $journal->entries,
    //                 ],
    //             ]);
    //         }

    //         // Rest of your existing show method for web...
    //         // Format dates safely
    //         $journalDate = null;
    //         $postingDate = null;

    //         if ($journal->journal_date) {
    //             try {
    //                 if (is_string($journal->journal_date)) {
    //                     $journalDate = \Carbon\Carbon::parse($journal->journal_date)->toDateString();
    //                 } elseif (method_exists($journal->journal_date, 'toDateString')) {
    //                     $journalDate = $journal->journal_date->toDateString();
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::warning('Error parsing journal_date in show', [
    //                     'journal_id' => $journal->id,
    //                     'journal_date' => $journal->journal_date,
    //                     'error' => $e->getMessage(),
    //                 ]);
    //             }
    //         }

    //         if ($journal->posting_date) {
    //             try {
    //                 if (is_string($journal->posting_date)) {
    //                     $postingDate = \Carbon\Carbon::parse($journal->posting_date)->toDateString();
    //                 } elseif (method_exists($journal->posting_date, 'toDateString')) {
    //                     $postingDate = $journal->posting_date->toDateString();
    //                 }
    //             } catch (\Exception $e) {
    //                 Log::warning('Error parsing posting_date in show', [
    //                     'journal_id' => $journal->id,
    //                     'posting_date' => $journal->posting_date,
    //                     'error' => $e->getMessage(),
    //                 ]);
    //             }
    //         }

    //         // Prepare journal entries data
    //         $entries = $journal->entries->map(function ($entry) {
    //             return [
    //                 'id' => $entry->id,
    //                 'account_code' => $entry->account_code,
    //                 'account_name' => $entry->account_name,
    //                 'description' => $entry->description,
    //                 'debit_amount' => (float) $entry->debit_amount,
    //                 'credit_amount' => (float) $entry->credit_amount,
    //                 'line_number' => $entry->line_number,
    //                 'cost_center' => $entry->cost_center,
    //                 'project_code' => $entry->project_code,
    //                 'reference' => $entry->reference,
    //                 'tax_code' => $entry->tax_code,
    //                 'tax_amount' => (float) $entry->tax_amount,
    //                 'net_amount' => (float) $entry->net_amount,
    //                 'entry_type' => $entry->entry_type,
    //                 'created_at' => $entry->created_at ?
    //                     (is_string($entry->created_at) ?
    //                         $entry->created_at :
    //                         $entry->created_at->toDateTimeString()) : null,
    //             ];
    //         });

    //         // Prepare journal data
    //         $journalData = [
    //             'id' => $journal->id,
    //             'journal_number' => $journal->journal_number,
    //             'journal_date' => $journalDate,
    //             'posting_date' => $postingDate,
    //             'description' => $journal->description,
    //             'total_amount' => $journal->total_amount,
    //             'total_debit' => $journal->total_debit,
    //             'total_credit' => $journal->total_credit,
    //             'status' => $journal->status,
    //             'reference_number' => $journal->reference_number,
    //             'batch_number' => $journal->batch_number,
    //             'financial_year' => $journal->financial_year,
    //             'remarks' => $journal->remarks,
    //             'source_document' => $journal->source_document,
    //             'is_recurring' => $journal->is_recurring,
    //             'recurring_frequency' => $journal->recurring_frequency,
    //             'next_recurring_date' => $journal->next_recurring_date,
    //             'mda_id' => $journal->mda_id,
    //             'economic_code_id' => $journal->economic_code_id,
    //             'administrative_code_id' => $journal->administrative_code_id,
    //             'administrative_sector_code_id' => $journal->administrative_sector_code_id,
    //             'creator' => $journal->creator ? [
    //                 'id' => $journal->creator->id,
    //                 'name' => $journal->creator->name,
    //                 'email' => $journal->creator->email,
    //             ] : null,
    //             'approver' => $journal->approver ? [
    //                 'id' => $journal->approver->id,
    //                 'name' => $journal->approver->name,
    //                 'email' => $journal->approver->email,
    //             ] : null,
    //             'entries' => $entries,
    //             'can_edit' => $journal->canEdit(),
    //             'can_delete' => $journal->canDelete(),
    //             'is_balanced' => $journal->isBalanced(),
    //             'balance_difference' => abs($journal->total_debit - $journal->total_credit),
    //             'entry_count' => $journal->entries->count(),
    //             'created_at' => $journal->created_at ?
    //                 (is_string($journal->created_at) ?
    //                     $journal->created_at :
    //                     $journal->created_at->toDateTimeString()) : null,
    //             'updated_at' => $journal->updated_at ?
    //                 (is_string($journal->updated_at) ?
    //                     $journal->updated_at :
    //                     $journal->updated_at->toDateTimeString()) : null,
    //             'approved_at' => $journal->approved_at ?
    //                 (is_string($journal->approved_at) ?
    //                     $journal->approved_at :
    //                     $journal->approved_at->toDateTimeString()) : null,
    //         ];

    //         // Get GL accounts for dropdowns
    //         $glAccounts = $this->journalService->getGlAccounts();

    //         // Get departments for dropdowns
    //         // $departments = \App\Models\Department::select(['id', 'name', 'code'])
    //         //     ->where('is_active', true)
    //         //     ->orderBy('name')
    //         //     ->get()
    //         //     ->map(function ($dept) {
    //         //         return [
    //         //             'id' => $dept->id,
    //         //             'name' => $dept->name,
    //         //             'code' => $dept->code,
    //         //             'searchLabel' => "{$dept->code} - {$dept->name}",
    //         //         ];
    //         //     });

    //         // Get GL categories for dropdowns
    //         // $glCategories = \App\Models\GlCategory::select(['id', 'category_code', 'category_name'])
    //         //     ->where('is_active', true)
    //         //     ->orderBy('category_code')
    //         //     ->get()
    //         //     ->map(function ($category) {
    //         //         return [
    //         //             'id' => $category->id,
    //         //             'category_code' => $category->category_code,
    //         //             'category_name' => $category->category_name,
    //         //             'searchLabel' => "{$category->category_code} - {$category->category_name}",
    //         //         ];
    //         //     });

    //         // Log view activity
    //         $this->activityLogger->log(
    //             "Viewed journal {$journal->journal_number}",
    //             [
    //                 'journal_id' => $journal->id,
    //                 'journal_number' => $journal->journal_number,
    //                 'total_amount' => $journal->total_amount,
    //                 'status' => $journal->status,
    //                 'viewed_by' => auth()->id(),
    //             ],
    //             'journal'
    //         );

    //         return Inertia::render('admin/journals/show', [
    //             'journal' => $journalData,
    //             'gl_accounts' => $glAccounts,
    //             // 'departments' => $departments,
    //             // 'gl_categories' => $glCategories,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error showing journal: '.$e->getMessage());

    //         // If it's an API request
    //         if ($request->expectsJson() || $request->is('api/*')) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Journal not found.',
    //             ], 404);
    //         }

    //         // Log failed view attempt
    //         $this->activityLogger->log(
    //             'Failed to view journal',
    //             [
    //                 'journal_id' => $id,
    //                 'error' => $e->getMessage(),
    //                 'attempted_by' => auth()->id(),
    //             ],
    //             'journal'
    //         );

    //         return redirect()
    //             ->route('journals.index')
    //             ->with('flash', [
    //                 'message' => 'Journal not found!',
    //                 'type' => 'error',
    //             ]);
    //     }
    // }

    /**
     * Export journals to CSV.
     */
    public function export(Request $request)
    {
        Log::info('Exporting journals');

        // Log export activity
        $this->activityLogger->log(
            'Exported journals',
            [
                'filters' => $request->except(['page']),
                'exported_by' => auth()->id(),
            ],
            'journal'
        );

        // This would be handled by a separate export service
        // For now, redirect to index
        return redirect()->route('journals.index');
    }

    /**
     * Print journal.
     */
    public function print(Journal $journal)
    {
        // Load relationships
        $journal->load([
            // 'entries.glAccount',
            'creator',
            'approver',
            // 'department',
            // 'glCategory',
        ]);

        return inertia('admin/journals/print', [
            'journal' => $journal->toArray(),
        ]);
    }

    /**
     * Approve journal.
     */
    public function approve(Request $request, Journal $journal)
    {
        Log::info('Approving journal', ['id' => $journal->id]);

        try {
            // Check if user can approve
            if (! auth()->user()->can('approve', $journal)) {
                return back()->withErrors(['error' => 'You are not authorized to approve journals.']);
            }

            // Check if journal can be approved
            if ($journal->status === 'approved') {
                return back()->withErrors(['error' => 'Journal is already approved.']);
            }

            if (! in_array($journal->status, ['draft', 'saved', 'pending', 'submitted'])) {
                return back()->withErrors(['error' => 'Journal cannot be approved in its current status.']);
            }

            // Approve through service
            $approvedJournal = $this->journalService->approveJournal($journal, [
                'remarks' => $request->input('remarks', ''),
            ]);

            // Log approval activity
            $this->activityLogger->log(
                "Approved journal {$approvedJournal->journal_number}",
                [
                    'journal_id' => $approvedJournal->id,
                    'journal_number' => $approvedJournal->journal_number,
                    'total_amount' => $approvedJournal->total_amount,
                    'approved_by' => auth()->id(),
                    'approved_at' => $approvedJournal->approved_at,
                    'remarks' => $request->input('remarks', ''),
                ],
                'journal'
            );

            Log::info('Journal approved successfully', ['id' => $approvedJournal->id]);

            return redirect()
                ->route('journals.index')
                ->with('flash', [
                    'message' => 'Journal approved successfully!',
                    'type' => 'success',
                ]);
        } catch (\Exception $e) {
            Log::error('Error approving journal: ' . $e->getMessage());

            // Log failed approval
            $this->activityLogger->log(
                "Failed to approve journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                ],
                'journal'
            );

            return back()
                ->withErrors(['error' => 'Failed to approve journal. ' . $e->getMessage()]);
        }
    }

    /**
     * Reject journal.
     */
    public function reject(Request $request, Journal $journal)
    {
        Log::info('Rejecting journal', ['id' => $journal->id]);

        try {
            $request->validate([
                'reason' => 'required|string|max:500',
            ]);

            // Check if user can reject
            if (! auth()->user()->can('reject', $journal)) {
                return back()->withErrors(['error' => 'You are not authorized to reject journals.']);
            }

            // Check if journal can be rejected
            if ($journal->status === 'approved') {
                return back()->withErrors(['error' => 'Cannot reject an approved journal.']);
            }

            // Reject through service
            $rejectedJournal = $this->journalService->rejectJournal($journal, $request->input('reason'));

            // Log rejection activity
            $this->activityLogger->log(
                "Rejected journal {$rejectedJournal->journal_number}",
                [
                    'journal_id' => $rejectedJournal->id,
                    'journal_number' => $rejectedJournal->journal_number,
                    'reason' => $request->input('reason'),
                    'rejected_by' => auth()->id(),
                    'rejected_at' => now(),
                ],
                'journal'
            );

            Log::info('Journal rejected successfully', ['id' => $rejectedJournal->id]);

            return redirect()
                ->route('journals.index')
                ->with('flash', [
                    'message' => 'Journal rejected successfully!',
                    'type' => 'warning',
                ]);
        } catch (\Exception $e) {
            Log::error('Error rejecting journal: ' . $e->getMessage());

            // Log failed rejection
            $this->activityLogger->log(
                "Failed to reject journal {$journal->journal_number}",
                [
                    'journal_id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                ],
                'journal'
            );

            return back()
                ->withErrors(['error' => 'Failed to reject journal. ' . $e->getMessage()]);
        }
    }

    /**
     * Get journal summary.
     */
    public function summary($id)
    {
        try {
            $summary = $this->journalService->getJournalSummary($id);

            return response()->json([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting journal summary: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Validate journal entries.
     */
    public function validateEntries(Request $request)
    {
        try {
            $request->validate([
                'entries' => 'required|array|min:2',
                'entries.*.account_code' => 'required|string|max:50',
                'entries.*.debit_amount' => 'nullable|numeric|min:0',
                'entries.*.credit_amount' => 'nullable|numeric|min:0',
            ]);

            $entries = $request->input('entries');
            $errors = $this->journalService->validateJournalEntries($entries);

            return response()->json([
                'success' => true,
                'valid' => empty($errors),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            Log::error('Error validating journal entries: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get GL account details.
     */
    // public function getGlAccount($accountCode)
    // {
    //     try {
    //         $account = \App\Models\GlAccount::where('account_code', $accountCode)
    //             ->where('is_active', true)
    //             ->firstOrFail();

    //         return response()->json([
    //             'success' => true,
    //             'data' => [
    //                 'id' => $account->id,
    //                 'account_code' => $account->account_code,
    //                 'account_name' => $account->account_name,
    //                 'account_type' => $account->account_type,
    //                 'normal_balance' => $account->normal_balance,
    //                 'current_balance' => (float) $account->current_balance,
    //                 'formatted_type' => $account->formatted_account_type,
    //                 'full_name' => $account->full_name,
    //                 'is_parent' => $account->isParent(),
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error getting GL account: ' . $e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'GL account not found.',
    //         ], 404);
    //     }
    // }

    // /**
    //  * Get account types for filter.
    //  */
    // public function getAccountTypes()
    // {
    //     $accountTypes = \App\Models\GlAccount::select('account_type')
    //         ->distinct()
    //         ->whereNotNull('account_type')
    //         ->where('is_active', true)
    //         ->orderBy('account_type')
    //         ->pluck('account_type');

    //     $formattedTypes = $accountTypes->map(function ($type) {
    //         $types = [
    //             'asset' => 'Asset',
    //             'liability' => 'Liability',
    //             'equity' => 'Equity',
    //             'income' => 'Income',
    //             'expense' => 'Expense',
    //             'cost' => 'Cost of Goods Sold',
    //             'revenue' => 'Revenue',
    //         ];

    //         return [
    //             'value' => $type,
    //             'label' => $types[$type] ?? ucfirst($type),
    //         ];
    //     });

    //     return response()->json([
    //         'success' => true,
    //         'data' => $formattedTypes,
    //     ]);
    // }

    /**
     * Get MDAs for dropdown
     */
    public function getMdas()
    {
        try {
            $mdas = \App\Models\Mda::select(['id', 'name', 'code', 'administrative_code_id'])
                ->where('status', 1)
                ->orderBy('name')
                ->get()
                ->map(function ($mda) {
                    return [
                        'id' => $mda->id,
                        'name' => $mda->name,
                        'code' => $mda->code,
                        'administrative_code_id' => $mda->administrative_code_id,
                        'searchLabel' => "{$mda->code} - {$mda->name}",
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $mdas,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting MDAs: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load MDAs',
            ], 500);
        }
    }

    /**
     * Get administrative codes
     */
    public function getAdministrativeCodes()
    {
        try {
            $codes = \App\Models\AdministrativeCode::select(['id', 'name', 'code'])
                ->where('status', 1)
                ->orderBy('code')
                ->get()
                ->map(function ($code) {
                    return [
                        'id' => $code->id,
                        'name' => $code->name,
                        'code' => $code->code,
                        'searchLabel' => "{$code->code} - {$code->name}",
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $codes,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting administrative codes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load administrative codes',
            ], 500);
        }
    }

    /**
     * Get administrative sector codes by administrative code ID
     */
    public function getAdministrativeSectorCodes($administrativeCodeId)
    {
        try {
            $sectors = \App\Models\AdministrativeSectorCode::select(['id', 'name', 'code', 'type'])
                ->where('administrative_code_id', $administrativeCodeId)
                ->where('status', 1)
                ->orderBy('code')
                ->get()
                ->map(function ($sector) {
                    return [
                        'id' => $sector->id,
                        'name' => $sector->name,
                        'code' => $sector->code,
                        'type' => $sector->type,
                        'searchLabel' => "{$sector->code} - {$sector->name}",
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $sectors,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting administrative sector codes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load administrative sector codes',
            ], 500);
        }
    }

    /**
     * Get economic codes
     */
    public function getEconomicCodes()
    {
        try {
            $codes = \App\Models\EconomyCode::select(['id', 'name', 'code'])
                ->where('status', 'active')
                ->orderBy('code')
                ->get()
                ->map(function ($code) {
                    // Determine series based on code prefix
                    $series = $this->determineSeriesFromCode($code->code);

                    return [
                        'id' => $code->id,
                        'name' => $code->name,
                        'code' => $code->code,
                        'series' => $series,
                        'searchLabel' => "{$code->code} - {$code->name} (Series {$series})",
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $codes,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting economic codes: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load economic codes',
            ], 500);
        }
    }

    /**
     * Get economic code items by economic code ID
     */
    // public function getEconomicCodeItems($economyCodeId)
    // {
    //     try {
    //         $items = \App\Models\EconomyCodeItem::select(['id', 'name', 'code'])
    //             ->where('economy_code_id', $economyCodeId)
    //             ->where('status', 'active')
    //             ->orderBy('code')
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'name' => $item->name,
    //                     'code' => $item->code,
    //                     'searchLabel' => "{$item->code} - {$item->name}",
    //                 ];
    //             });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $items,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error getting economic code items: '.$e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load economic code items',
    //         ], 500);
    //     }
    // }

    /**
     * Get economic code items by series (1-4)
     */
    // public function getEconomicCodeItemsBySeries($series)
    // {
    //     try {
    //         // First get economic codes in the specified series
    //         $economyCodes = \App\Models\EconomyCode::select('id')
    //             ->where('status', 'active')
    //             ->get()
    //             ->filter(function ($code) use ($series) {
    //                 // Simple series determination based on code pattern
    //                 $codeStr = (string) $code->code;

    //                 // Series determination logic
    //                 if ($series == 2 && preg_match('/^2/', $codeStr)) {
    //                     return true;
    //                 } elseif ($series == 3 && preg_match('/^3/', $codeStr)) {
    //                     return true;
    //                 } elseif ($series == 4 && preg_match('/^4/', $codeStr)) {
    //                     return true;
    //                 } elseif ($series == 1 && ! preg_match('/^[234]/', $codeStr)) {
    //                     return true;
    //                 }

    //                 return false;
    //             })
    //             ->pluck('id')
    //             ->toArray();

    //         if (empty($economyCodes)) {
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => [],
    //                 'message' => "No economic codes found for series {$series}",
    //             ]);
    //         }

    //         $items = \App\Models\EconomyCodeItem::select(['id', 'name', 'code', 'economy_code_id'])
    //             ->whereIn('economy_code_id', $economyCodes)
    //             ->where('status', 'active')
    //             ->orderBy('code')
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'name' => $item->name,
    //                     'code' => $item->code,
    //                     'economy_code_id' => $item->economy_code_id,
    //                     'searchLabel' => "{$item->code} - {$item->name}",
    //                 ];
    //             });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $items,
    //             'count' => $items->count(),
    //             'series' => $series,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error getting economic code items by series: '.$e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load economic code items',
    //             'error' => $e->getMessage(),
    //         ], 500);
    //     }
    // }

    /**
     * Get economic code items by series (1-4)
     */
    // public function getEconomicCodeItemsBySeries($series)
    // {
    //     try {
    //         // First get economic codes in the specified series
    //         $economyCodes = \App\Models\EconomyCode::select('id')
    //             ->where('status', 'active')
    //             ->get()
    //             ->filter(function ($code) use ($series) {
    //                 return $this->determineSeriesFromCode($code->code) == $series;
    //             })
    //             ->pluck('id');

    //         $items = \App\Models\EconomyCodeItem::select(['id', 'name', 'code', 'economy_code_id'])
    //             ->whereIn('economy_code_id', $economyCodes)
    //             ->where('status', 'active')
    //             ->orderBy('code')
    //             ->get()
    //             ->map(function ($item) {
    //                 return [
    //                     'id' => $item->id,
    //                     'name' => $item->name,
    //                     'code' => $item->code,
    //                     'economy_code_id' => $item->economy_code_id,
    //                     'searchLabel' => "{$item->code} - {$item->name}",
    //                 ];
    //             });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $items,
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Error getting economic code items by series: '.$e->getMessage());

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to load economic code items',
    //         ], 500);
    //     }
    // }

    /**
     * Determine series from economic code (1-4 based on prefix)
     */
    // private function determineSeriesFromCode($code)
    // {
    //     // Extract first character or digits to determine series
    //     $firstChar = substr($code, 0, 1);
    //     $firstTwo = substr($code, 0, 2);

    //     // Series 2 typically starts with 2000 or similar
    //     if (preg_match('/^2/', $code)) {
    //         return 2;
    //     }
    //     // Series 3 typically starts with 3000 or similar
    //     elseif (preg_match('/^3/', $code)) {
    //         return 3;
    //     }
    //     // Series 4 typically starts with 4000 or similar
    //     elseif (preg_match('/^4/', $code)) {
    //         return 4;
    //     }
    //     // Series 1 is everything else
    //     else {
    //         return 1;
    //     }
    // }

    /**
     * Get economic code items by series (1-4)
     */
    public function getEconomicCodeItemsBySeries($series)
    {
        try {
            // First get economic codes in the specified series
            $economyCodes = \App\Models\EconomyCode::with('items')
                ->where('status', 'active')
                ->get()
                ->filter(function ($code) use ($series) {
                    // Simple series determination based on code pattern
                    $codeStr = (string) $code->code;

                    // Series determination logic - make it more flexible
                    if ($series == 2 && (preg_match('/^2/', $codeStr) || str_starts_with($codeStr, '2'))) {
                        return true;
                    } elseif ($series == 3 && (preg_match('/^3/', $codeStr) || str_starts_with($codeStr, '3'))) {
                        return true;
                    } elseif ($series == 4 && (preg_match('/^4/', $codeStr) || str_starts_with($codeStr, '4'))) {
                        return true;
                    } elseif ($series == 1 && ! preg_match('/^[234]/', $codeStr)) {
                        return true;
                    }

                    return false;
                });

            if ($economyCodes->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'message' => "No economic codes found for series {$series}",
                ]);
            }

            // Collect all items from the filtered economic codes
            $items = collect();
            foreach ($economyCodes as $economyCode) {
                if ($economyCode->items && $economyCode->items->isNotEmpty()) {
                    $items = $items->merge($economyCode->items->map(function ($item) use ($economyCode) {
                        return [
                            'id' => $item->id,
                            'name' => $item->name,
                            'code' => $item->code,
                            'economy_code_id' => $economyCode->id,
                            'economy_code' => $economyCode->code,
                            'economy_code_name' => $economyCode->name,
                            'searchLabel' => "{$item->code} - {$item->name} (Parent: {$economyCode->code})",
                        ];
                    }));
                }
            }

            // Sort by code
            $items = $items->sortBy('code')->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'count' => $items->count(),
                'series' => $series,
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting economic code items by series: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load economic code items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get economic code items by economic code ID
     */
    public function getEconomicCodeItems($economyCodeId)
    {
        try {
            $economyCode = \App\Models\EconomyCode::with('items')
                ->where('id', $economyCodeId)
                ->where('status', 'active')
                ->firstOrFail();

            $items = $economyCode->items
                ->where('status', 'active')
                ->map(function ($item) use ($economyCode) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'code' => $item->code,
                        'economy_code_id' => $economyCode->id,
                        'economy_code' => $economyCode->code,
                        'economy_code_name' => $economyCode->name,
                        'searchLabel' => "{$item->code} - {$item->name} (Parent: {$economyCode->code})",
                    ];
                })
                ->sortBy('code')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $items,
                'count' => $items->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting economic code items: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to load economic code items',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Determine series from economic code (1-4 based on prefix)
     */
    private function determineSeriesFromCode($code)
    {
        if (empty($code)) {
            return 1;
        }

        $codeStr = (string) $code;

        // Check if it starts with 2, 3, or 4
        if (str_starts_with($codeStr, '2')) {
            return 2;
        } elseif (str_starts_with($codeStr, '3')) {
            return 3;
        } elseif (str_starts_with($codeStr, '4')) {
            return 4;
        }

        // Default to series 1
        return 1;
    }

    /**
     * Generate auto journal number
     */
    public function generateJournalNumber(Request $request)
    {
        try {
            $prefix = 'JRN';
            $year = date('Y');
            $month = date('m');

            // Get last journal number for this month
            $lastJournal = \App\Models\Journal::whereYear('created_at', $year)
                ->whereMonth('created_at', $month)
                ->orderBy('id', 'desc')
                ->first();

            if ($lastJournal && preg_match('/' . $prefix . '-' . $year . $month . '-(\d+)/', $lastJournal->journal_number, $matches)) {
                $lastNumber = (int) $matches[1];
                $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
            } else {
                $newNumber = '0001';
            }

            $journalNumber = $prefix . '-' . $year . $month . '-' . $newNumber;

            return response()->json([
                'success' => true,
                'data' => [
                    'journal_number' => $journalNumber,
                    'prefix' => $prefix,
                    'year' => $year,
                    'month' => $month,
                    'sequence' => $newNumber,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating journal number: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate journal number',
            ], 500);
        }
    }

    /**
     * Get journal data for editing (API endpoint)
     */
    public function getJournalForEdit($id)
    {
        try {
            $journal = Journal::with([
                'entries' => function ($query) {
                    $query->select([
                        'id',
                        'journal_id',
                        'economic_code_id',
                        'account_code',
                        'description',
                        'debit_amount',
                        'credit_amount',
                        'cost_center',
                        'project_code',
                        'reference',
                        'tax_code',
                        'tax_amount',
                    ]);
                },
                'creator',
                'approver',
            ])->findOrFail($id);

            // Format dates safely
            $journalDate = null;
            $postingDate = null;

            if ($journal->journal_date) {
                try {
                    $journalDate = is_string($journal->journal_date)
                        ? $journal->journal_date
                        : $journal->journal_date->toDateString();
                } catch (\Exception $e) {
                    Log::warning('Error parsing journal_date', [
                        'journal_id' => $journal->id,
                        'journal_date' => $journal->journal_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if ($journal->posting_date) {
                try {
                    $postingDate = is_string($journal->posting_date)
                        ? $journal->posting_date
                        : $journal->posting_date->toDateString();
                } catch (\Exception $e) {
                    Log::warning('Error parsing posting_date', [
                        'journal_id' => $journal->id,
                        'posting_date' => $journal->posting_date,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $journal->id,
                    'journal_number' => $journal->journal_number,
                    'journal_date' => $journalDate,
                    'posting_date' => $postingDate,
                    'description' => $journal->description,
                    'remarks' => $journal->remarks,
                    'reference_number' => $journal->reference_number,
                    'batch_number' => $journal->batch_number,
                    'financial_year' => $journal->financial_year,
                    'mda_id' => $journal->mda_id,
                    'economic_code_id' => $journal->economic_code_id,
                    'administrative_code_id' => $journal->administrative_code_id,
                    'administrative_sector_code_id' => $journal->administrative_sector_code_id,
                    'status' => $journal->status,
                    'entries' => $journal->entries,
                    'total_amount' => $journal->total_amount,
                    'total_debit' => $journal->total_debit,
                    'total_credit' => $journal->total_credit,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting journal for edit: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Journal not found.',
            ], 404);
        }
    }
}
