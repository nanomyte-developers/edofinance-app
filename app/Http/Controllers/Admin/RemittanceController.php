<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Remittance;
use App\Models\BankActivity;
use Illuminate\Http\Request;
use App\Services\ActivityLogger;
use App\Services\RemittanceService;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\RemittanceResource;
use App\Http\Requests\StoreRemittanceRequest;
use App\Http\Requests\UpdateRemittanceRequest;

class RemittanceController extends Controller
{
    protected $remittanceService;
    protected $activityLogger;

    public function __construct(RemittanceService $remittanceService, ActivityLogger $activityLogger)
    {
        $this->remittanceService = $remittanceService;
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display a listing of the remittances.
     */
    public function index(Request $request)
    {
        Log::info('RemittanceController index called');
        Log::info('Request data:', $request->all());

        // Log activity
        $this->activityLogger->log(
            "Viewed remittances list",
            [
                'search' => $request->input('search', ''),
                'per_page' => $request->input('per_page', 20),
                'filters' => $request->except(['search', 'per_page', 'page']),
                'user_id' => auth()->id()
            ],
            'remittance'
        );

        // Get bank activities for dropdowns
        $bankActivities = BankActivity::select([
            'id',
            'tag',
            'bank_name',
            'title',
            'account_number',
            'status'
        ])
            ->where('status', 1)
            ->orderBy('bank_name')
            ->orderBy('title')
            ->get()
            ->map(function ($bank) {
                return [
                    'id' => $bank->id,
                    'tag' => $bank->tag,
                    'bank_name' => $bank->bank_name,
                    'title' => $bank->title,
                    'account_number' => $bank->account_number,
                    'searchLabel' => "{$bank->tag} - {$bank->bank_name} {$bank->title} ({$bank->account_number})",
                ];
            });

        Log::info('Bank activities loaded:', ['count' => $bankActivities->count()]);

        // Get per_page from request or use default
        $perPage = $request->input('per_page', 20);

        // Get filters from request
        $filters = $request->only([
            'search',
            'date_from',
            'date_to',
            'status',
            'source_bank_id',
            'destination_bank_id',
            'min_amount',
            'max_amount'
        ]);

        // Get paginated remittances through service
        $remittances = $this->remittanceService->getAllRemittances($filters, $perPage);

        // Transform remittances with edit/delete permissions
        $transformedRemittances = $remittances->through(function ($remittance) {
            // Handle date conversion safely
            $transferDate = null;
            if ($remittance->transfer_date) {
                try {
                    if (is_string($remittance->transfer_date)) {
                        $transferDate = \Carbon\Carbon::parse($remittance->transfer_date)->toDateString();
                    } elseif (method_exists($remittance->transfer_date, 'toDateString')) {
                        $transferDate = $remittance->transfer_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing transfer_date', [
                        'remittance_id' => $remittance->id,
                        'transfer_date' => $remittance->transfer_date,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return [
                'id' => $remittance->id,
                'receipt_number' => $remittance->receipt_number,
                'transfer_date' => $transferDate,
                'source_bank' => $remittance->source_bank,
                'destination_bank' => $remittance->destination_bank,
                'source_bank_id' => $remittance->source_bank_id,
                'destination_bank_id' => $remittance->destination_bank_id,
                'source_bank_details' => $remittance->sourceBank ? [
                    'id' => $remittance->sourceBank->id,
                    'tag' => $remittance->sourceBank->tag,
                    'bank_name' => $remittance->sourceBank->bank_name,
                    'title' => $remittance->sourceBank->title,
                    'account_number' => $remittance->sourceBank->account_number,
                ] : null,
                'destination_bank_details' => $remittance->destinationBank ? [
                    'id' => $remittance->destinationBank->id,
                    'tag' => $remittance->destinationBank->tag,
                    'bank_name' => $remittance->destinationBank->bank_name,
                    'title' => $remittance->destinationBank->title,
                    'account_number' => $remittance->destinationBank->account_number,
                ] : null,
                'amount' => $remittance->amount,
                'narration' => $remittance->narration,
                'status' => $remittance->status,
                // Add permission flags based on status (same as voucher logic)
                'can_edit' => $this->canEditRemittance($remittance),
                'can_delete' => $this->canDeleteRemittance($remittance),
                'created_at' => $remittance->created_at ?
                    (is_string($remittance->created_at) ?
                        $remittance->created_at :
                        $remittance->created_at->toDateTimeString()) : null,
                'updated_at' => $remittance->updated_at ?
                    (is_string($remittance->updated_at) ?
                        $remittance->updated_at :
                        $remittance->updated_at->toDateTimeString()) : null,
            ];
        });

        // Get statistics and filter options
        $statistics = $this->remittanceService->getStatistics();
        $filterOptions = $this->remittanceService->getFilterOptions();

        // Transform for Inertia
        return Inertia::render('admin/remittances/index', [
            'remittances' => [
                'data' => $transformedRemittances->items(),
                'current_page' => $remittances->currentPage(),
                'first_page_url' => $remittances->url(1),
                'from' => $remittances->firstItem(),
                'last_page' => $remittances->lastPage(),
                'last_page_url' => $remittances->url($remittances->lastPage()),
                'next_page_url' => $remittances->nextPageUrl(),
                'path' => $remittances->path(),
                'per_page' => $remittances->perPage(),
                'prev_page_url' => $remittances->previousPageUrl(),
                'to' => $remittances->lastItem(),
                'total' => $remittances->total(),
            ],
            'filters' => $filters,
            'bank_activities' => $bankActivities,
            'filter_options' => array_merge($filterOptions, [
                'per_page_options' => [10, 20, 50, 100],
            ]),
            'statistics' => $statistics,
        ]);
    }

    /**
     * Determine if a remittance can be edited.
     */
    protected function canEditRemittance($remittance)
    {
        $auth_user = \Auth::user();

       

        if ($auth_user->hasRole('super-admin') || $auth_user->hasRole('admin') || $auth_user->hasRole('Admin')) {
            return true;
        }
        if (!$remittance || !$remittance->status) {
            return false;
        }

        $status = strtolower(trim($remittance->status));

        // Allow editing for these statuses - matches voucher logic
        $editableStatuses = [
            'draft',
            'saved',
            'sent back',
            'returned',
            'declined',
            'rejected',
        ];

        return in_array($status, $editableStatuses);
    }

    /**
     * Determine if a remittance can be deleted.
     */
    protected function canDeleteRemittance($remittance)
    {
        if (!$remittance || !$remittance->status) {
            return false;
        }

        $status = strtolower(trim($remittance->status));

        // Allow deletion only for drafts and saved remittances
        $deletableStatuses = ['draft', 'saved'];

        return in_array($status, $deletableStatuses);
    }

    /**
     * Store a newly created remittance in storage.
     */
    public function store(StoreRemittanceRequest $request)
    {
        Log::info('Creating new remittance', $request->all());

        try {
            $validated = $request->validated();

            // Create through service
            $remittance = $this->remittanceService->createRemittance($validated);

            // Log creation activity
            $this->activityLogger->log(
                "Created remittance {$remittance->receipt_number}",
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittance->receipt_number,
                    'amount' => $remittance->amount,
                    'source_bank_id' => $remittance->source_bank_id,
                    'destination_bank_id' => $remittance->destination_bank_id,
                    'status' => $remittance->status,
                    'created_by' => auth()->id()
                ],
                'remittance'
            );

            // Log specific action for audit
            $this->activityLogger->logAction('created', $remittance, [
                'amount' => $remittance->amount,
                'source_bank' => $remittance->sourceBank->tag ?? null,
                'destination_bank' => $remittance->destinationBank->tag ?? null,
                'narration' => $remittance->narration
            ]);

            Log::info('Remittance created successfully', ['id' => $remittance->id]);

            return redirect()
                ->route('remittances.index')
                ->with('flash', [
                    'message' => 'Remittance created successfully!',
                    'type' => 'success'
                ]);
        } catch (\Exception $e) {
            Log::error('Error creating remittance: ' . $e->getMessage());

            // Log failed creation attempt
            $this->activityLogger->log(
                "Failed to create remittance",
                [
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id(),
                    'data_keys' => array_keys($request->all())
                ],
                'remittance'
            );

            return back()
                ->withErrors(['error' => 'Failed to create remittance. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Update the specified remittance in storage.
     */
    public function update(UpdateRemittanceRequest $request, Remittance $remittance)
    {
        Log::info('Updating remittance', ['id' => $remittance->id, 'data' => $request->all()]);

        // Check if remittance can be edited
        if (!$this->canEditRemittance($remittance)) {
            Log::warning('Attempt to edit non-editable remittance', [
                'remittance_id' => $remittance->id,
                'status' => $remittance->status,
                'user_id' => auth()->id()
            ]);

            // Log unauthorized edit attempt
            $this->activityLogger->log(
                "Unauthorized edit attempt on remittance {$remittance->receipt_number}",
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittance->receipt_number,
                    'status' => $remittance->status,
                    'attempted_by' => auth()->id(),
                    'reason' => 'Status prevents editing'
                ],
                'remittance'
            );

            return back()
                ->withErrors(['error' => 'This remittance cannot be edited because of its current status.'])
                ->withInput();
        }

        // Store original data for logging changes
        $originalData = [
            'status' => $remittance->status,
            'amount' => $remittance->amount,
            'source_bank_id' => $remittance->source_bank_id,
            'destination_bank_id' => $remittance->destination_bank_id,
            'receipt_number' => $remittance->receipt_number,
        ];

        $changes = [];

        try {
            $validated = $request->validated();

            // Update through service - now returns the updated object
            $updatedRemittance = $this->remittanceService->updateRemittance($remittance, $validated);

            // Determine what changed
            foreach ($validated as $key => $value) {
                if (isset($originalData[$key]) && $originalData[$key] != $value && $key !== 'updated_at') {
                    $changes[$key] = [
                        'from' => $originalData[$key],
                        'to' => $value
                    ];
                }
            }

            // Log update activity - use $updatedRemittance which is now the object
            $this->activityLogger->log(
                "Updated remittance {$updatedRemittance->receipt_number}",
                [
                    'remittance_id' => $updatedRemittance->id,
                    'receipt_number' => $updatedRemittance->receipt_number,
                    'changes' => $changes,
                    'updated_by' => auth()->id(),
                    'old_status' => $originalData['status'] ?? null,
                    'new_status' => $updatedRemittance->status
                ],
                'remittance'
            );

            // Log specific action with detailed changes
            $this->activityLogger->logAction('updated', $updatedRemittance, [
                'changes' => $changes,
                'updated_by' => auth()->id(),
                'amount_changed' => isset($validated['amount']) ?
                    ['from' => $originalData['amount'], 'to' => $validated['amount']] : null
            ]);

            Log::info('Remittance updated successfully', ['id' => $updatedRemittance->id]);

            return redirect()
                ->route('remittances.index')
                ->with('flash', [
                    'message' => 'Remittance updated successfully!',
                    'type' => 'success'
                ]);
        } catch (\Exception $e) {
            Log::error('Error updating remittance: ' . $e->getMessage(), [
                'remittance_id' => $remittance->id,
                'error' => $e
            ]);

            // Log failed update attempt
            $this->activityLogger->log(
                "Failed to update remittance {$remittance->receipt_number}",
                [
                    'remittance_id' => $remittance->id,
                    'receipt_number' => $remittance->receipt_number,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id()
                ],
                'remittance'
            );

            return back()
                ->withErrors(['error' => 'Failed to update remittance. Please try again.'])
                ->withInput();
        }
    }

    /**
     * Remove the specified remittance from storage.
     */
    public function destroy($id)
    {
        Log::info('Deleting remittance', ['id' => $id]);

        try {
            $remittance = Remittance::findOrFail($id);

            // Check if remittance can be deleted
            if (!$this->canDeleteRemittance($remittance)) {
                Log::warning('Attempt to delete non-deletable remittance', [
                    'remittance_id' => $remittance->id,
                    'status' => $remittance->status,
                    'user_id' => auth()->id()
                ]);

                // Log unauthorized delete attempt
                $this->activityLogger->log(
                    "Unauthorized delete attempt on remittance {$remittance->receipt_number}",
                    [
                        'remittance_id' => $remittance->id,
                        'remittance_number' => $remittance->receipt_number,
                        'status' => $remittance->status,
                        'attempted_by' => auth()->id(),
                        'reason' => 'Status prevents deletion'
                    ],
                    'remittance'
                );

                return back()
                    ->withErrors(['error' => 'This remittance cannot be deleted because of its current status.']);
            }

            $remittanceNumber = $remittance->receipt_number;
            $remittanceData = $remittance->toArray();

            // Log before deletion
            $this->activityLogger->log(
                "Attempting to delete remittance {$remittanceNumber}",
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittanceNumber,
                    'amount' => $remittance->amount,
                    'status' => $remittance->status,
                    'deleted_by' => auth()->id()
                ],
                'remittance'
            );

            // Delete through service
            $this->remittanceService->deleteRemittance($remittance);

            // Log successful deletion
            $this->activityLogger->log(
                "Deleted remittance {$remittanceNumber}",
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittanceNumber,
                    'amount' => $remittanceData['amount'],
                    'status' => $remittanceData['status'],
                    'source_bank_id' => $remittanceData['source_bank_id'],
                    'destination_bank_id' => $remittanceData['destination_bank_id'],
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now()
                ],
                'remittance'
            );

            // Log specific delete action
            $this->activityLogger->logAction('deleted', $remittance, [
                'remittance_number' => $remittanceNumber,
                'amount' => $remittanceData['amount'],
                'deleted_by' => auth()->id()
            ]);

            Log::info('Remittance deleted successfully');

            return redirect()
                ->route('remittances.index')
                ->with('flash', [
                    'message' => 'Remittance deleted successfully!',
                    'type' => 'info'
                ]);
        } catch (\Exception $e) {
            Log::error('Error deleting remittance: ' . $e->getMessage());

            // Log failed deletion
            $this->activityLogger->log(
                "Failed to delete remittance",
                [
                    'remittance_id' => $id,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id()
                ],
                'remittance'
            );

            return back()
                ->withErrors(['error' => 'Failed to delete remittance. Please try again.']);
        }
    }

    /**
     * Show the specified remittance.
     */
    public function show($id)
    {

        Log::info('Showing remittance', ['id' => $id]);



        try {
            $remittance = Remittance::with(['sourceBank', 'destinationBank'])->findOrFail($id);

            // Format transfer_date safely
            $transferDate = null;
            if ($remittance->transfer_date) {
                try {
                    if (is_string($remittance->transfer_date)) {
                        $transferDate = \Carbon\Carbon::parse($remittance->transfer_date)->toDateString();
                    } elseif (method_exists($remittance->transfer_date, 'toDateString')) {
                        $transferDate = $remittance->transfer_date->toDateString();
                    }
                } catch (\Exception $e) {
                    Log::warning('Error parsing transfer_date in show', [
                        'remittance_id' => $remittance->id,
                        'transfer_date' => $remittance->transfer_date,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Add permission flags for the show view as well
            $remittanceData = [
                'id' => $remittance->id,
                'receipt_number' => $remittance->receipt_number,
                'transfer_date' => $transferDate,
                'source_bank' => $remittance->source_bank,
                'destination_bank' => $remittance->destination_bank,
                'source_bank_id' => $remittance->source_bank_id,
                'destination_bank_id' => $remittance->destination_bank_id,
                'source_bank_details' => $remittance->sourceBank ? [
                    'id' => $remittance->sourceBank->id,
                    'tag' => $remittance->sourceBank->tag,
                    'bank_name' => $remittance->sourceBank->bank_name,
                    'title' => $remittance->sourceBank->title,
                    'account_number' => $remittance->sourceBank->account_number,
                ] : null,
                'destination_bank_details' => $remittance->destinationBank ? [
                    'id' => $remittance->destinationBank->id,
                    'tag' => $remittance->destinationBank->tag,
                    'bank_name' => $remittance->destinationBank->bank_name,
                    'title' => $remittance->destinationBank->title,
                    'account_number' => $remittance->destinationBank->account_number,
                ] : null,
                'amount' => $remittance->amount,
                'narration' => $remittance->narration,
                'status' => $remittance->status,
                'can_edit' => $this->canEditRemittance($remittance),
                'can_delete' => $this->canDeleteRemittance($remittance),
                'created_at' => $remittance->created_at ?
                    (is_string($remittance->created_at) ?
                        $remittance->created_at :
                        $remittance->created_at->toDateTimeString()) : null,
                'updated_at' => $remittance->updated_at ?
                    (is_string($remittance->updated_at) ?
                        $remittance->updated_at :
                        $remittance->updated_at->toDateTimeString()) : null,
            ];

            // Log view activity
            $this->activityLogger->log(
                "Viewed remittance {$remittance->receipt_number}",
                [
                    'remittance_id' => $remittance->id,
                    'remittance_number' => $remittance->receipt_number,
                    'amount' => $remittance->amount,
                    'status' => $remittance->status,
                    'viewed_by' => auth()->id()
                ],
                'remittance'
            );

            $bankActivities = BankActivity::select([
                'id',
                'tag',
                'bank_name',
                'title',
                'account_number',
                'status'
            ])
                ->where('status', 1)
                ->orderBy('bank_name')
                ->orderBy('title')
                ->get()
                ->map(function ($bank) {
                    return [
                        'id' => $bank->id,
                        'tag' => $bank->tag,
                        'bank_name' => $bank->bank_name,
                        'title' => $bank->title,
                        'account_number' => $bank->account_number,
                        'searchLabel' => "{$bank->tag} - {$bank->bank_name} {$bank->title} ({$bank->account_number})",
                    ];
                });

            // dd('Inertia call');

            return Inertia::render('admin/remittances/show', [
                'remittance' => $remittanceData,
                'bank_activities' => $bankActivities,
            ]);
        } catch (\Exception $e) {
            Log::error('Error showing remittance: ' . $e->getMessage());

            // Log failed view attempt
            $this->activityLogger->log(
                "Failed to view remittance",
                [
                    'remittance_id' => $id,
                    'error' => $e->getMessage(),
                    'attempted_by' => auth()->id()
                ],
                'remittance'
            );

            return redirect()
                ->route('remittances.index')
                ->with('flash', [
                    'message' => 'Remittance not found!',
                    'type' => 'error'
                ]);
        }
    }

    /**
     * Export remittances to CSV.
     */
    public function export(Request $request)
    {
        Log::info('Exporting remittances');

        // Log export activity
        $this->activityLogger->log(
            "Exported remittances",
            [
                'filters' => $request->except(['page']),
                'exported_by' => auth()->id()
            ],
            'remittance'
        );

        // This would be handled by a separate export service
        // For now, redirect to index
        return redirect()->route('remittances.index');
    }

    public function print(Remittance $remittance)
    {
        // Load relationships
        $remittance->load(['sourceBank', 'destinationBank']);

        return inertia('admin/remittances/print', [
            'remittance' => $remittance->toArray(),
        ]);
    }
}
