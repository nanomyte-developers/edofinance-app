<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Services\InternalAuditService;
use Illuminate\Support\Facades\Storage;

class InternalAuditController extends Controller
{
    protected InternalAuditService $internalAuditService;

    public function __construct(InternalAuditService $internalAuditService)
    {
        $this->internalAuditService = $internalAuditService;
    }

    // public function index()
    // {
    //     $stats = $this->internalAuditService->getDashboardStats();

    //     $pendingVouchers = Voucher::with(['mda', 'creator'])
    //         ->where('status', 'submitted')
    //         // ->where('current_stage', 'internal_audit')
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(10)
    //         ->through(function ($voucher) {
    //             return [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_type' => strtoupper($voucher->voucher_type),
    //                 'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => $voucher->total_amount,
    //                 'status' => $voucher->status,
    //                 'current_stage' => $voucher->current_stage,
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'initials' => $voucher->mda->initials,
    //                 ] : null,
    //                 'creator' => $voucher->creator ? [
    //                     'id' => $voucher->creator->id,
    //                     'name' => $voucher->creator->name,
    //                 ] : null,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'documents_count' => $voucher->documents->count(),
    //                 'missing_documents' => $this->internalAuditService->checkRequiredDocuments($voucher),
    //             ];
    //         });

    //     return Inertia::render('admin/internalAudit/index', [
    //         'vouchers' => $pendingVouchers,
    //         'stats' => $stats,
    //         'filters' => request()->only(['search']),
    //     ]);
    // }
    // public function index()
    // {
    //     $stats = $this->internalAuditService->getDashboardStats();

    //     $pendingVouchers = Voucher::with(['mda', 'creator', 'documents']) // Added 'documents' here
    //         ->where('status', 'submitted')
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(10)
    //         ->through(function ($voucher) {
    //             return [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_type' => strtoupper($voucher->voucher_type),
    //                 'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => $voucher->total_amount,
    //                 'status' => $voucher->status,
    //                 'current_stage' => $voucher->current_stage,
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'initials' => $voucher->mda->initials,
    //                 ] : null,
    //                 'creator' => $voucher->creator ? [
    //                     'id' => $voucher->creator->id,
    //                     'name' => $voucher->creator->name,
    //                 ] : null,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'documents_count' => $voucher->documents->count(), // This will now work
    //                 'documents' => $voucher->documents->map(function ($document) { // Added documents data
    //                     return [
    //                         'id' => $document->id,
    //                         'file_name' => $document->file_name,
    //                         'document_type' => $document->document_type,
    //                         'document_label' => $document->document_label,
    //                     ];
    //                 }),
    //                 'missing_documents' => $this->internalAuditService->checkRequiredDocuments($voucher),
    //             ];
    //         });

    //     return Inertia::render('admin/internalAudit/index', [
    //         'vouchers' => $pendingVouchers,
    //         'stats' => $stats,
    //         'filters' => request()->only(['search']),
    //     ]);
    // }
    // public function index()
    // {
    //     $stats = $this->internalAuditService->getDashboardStats();

    //     $pendingVouchers = Voucher::with(['mda', 'creator', 'documents'])
    //         ->where('status', 'submitted')
    //         ->orderBy('created_at', 'desc')
    //         ->paginate(10)
    //         ->through(function ($voucher) {
    //             return [
    //                 'id' => $voucher->id,
    //                 'voucher_number' => $voucher->voucher_number,
    //                 'voucher_type' => strtoupper($voucher->voucher_type),
    //                 'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
    //                 'narration' => $voucher->narration,
    //                 'total_amount' => $voucher->total_amount,
    //                 'status' => $voucher->status,
    //                 'current_stage' => $voucher->current_stage,
    //                 'mda' => $voucher->mda ? [
    //                     'id' => $voucher->mda->id,
    //                     'name' => $voucher->mda->name,
    //                     'initials' => $voucher->mda->initials,
    //                 ] : null,
    //                 'creator' => $voucher->creator ? [
    //                     'id' => $voucher->creator->id,
    //                     'name' => $voucher->creator->name,
    //                 ] : null,
    //                 'created_at' => $voucher->created_at?->toDateTimeString(),
    //                 'documents_count' => $voucher->documents->count(),
    //                 'documents' => $voucher->documents->map(function ($document) {
    //                     return [
    //                         'id' => $document->id,
    //                         'file_name' => $document->file_name,
    //                         'document_type' => $document->document_type,
    //                         'document_label' => $document->document_label,
    //                         // Try these common file path properties:
    //                         'file_path' => $document->file_path ?? null,
    //                         'path' => $document->path ?? null,
    //                         'url' => $document->url ?? null,
    //                         // If none work, we'll need to check your Document model
    //                     ];
    //                 }),
    //                 'missing_documents' => $this->internalAuditService->checkRequiredDocuments($voucher),
    //             ];
    //         });

    //     return Inertia::render('admin/internalAudit/index', [
    //         'vouchers' => $pendingVouchers,
    //         'stats' => $stats,
    //         'filters' => request()->only(['search']),
    //     ]);
    // }


    public function index()
    {
        $stats = $this->internalAuditService->getDashboardStats();

        $pendingVouchers = Voucher::with(['mda', 'creator', 'documents'])
            ->where('status', 'submitted')
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(function ($voucher) {
                return [
                    'id' => $voucher->id,
                    'voucher_number' => $voucher->voucher_number,
                    'voucher_type' => strtoupper($voucher->voucher_type),
                    'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
                    'narration' => $voucher->narration,
                    'total_amount' => $voucher->total_amount,
                    'status' => $voucher->status,
                    'current_stage' => $voucher->current_stage,
                    'mda' => $voucher->mda ? [
                        'id' => $voucher->mda->id,
                        'name' => $voucher->mda->name,
                        'initials' => $voucher->mda->initials,
                    ] : null,
                    'creator' => $voucher->creator ? [
                        'id' => $voucher->creator->id,
                        'name' => $voucher->creator->name,
                    ] : null,
                    'created_at' => $voucher->created_at?->toDateTimeString(),
                    'documents_count' => $voucher->documents->count(),
                    'documents' => $voucher->documents->map(function ($document) {
                        return [
                            'id' => $document->id,
                            'file_name' => $document->file_name,
                            'document_type' => $document->document_type,
                            'document_label' => $document->document_label,
                            'file_path' => $document->file_path,
                            'url' => Storage::url($document->file_path), // Generate full URL
                        ];
                    }),
                    'missing_documents' => $this->internalAuditService->checkRequiredDocuments($voucher),
                ];
            });

        // dd($pendingVouchers);

        return Inertia::render('admin/internalAudit/index', [
            'vouchers' => $pendingVouchers,
            'stats' => $stats,
            'filters' => request()->only(['search']),
        ]);
    }

    public function show(Voucher $voucher)
    {
        // Check if user can view this voucher
        // if (!$this->internalAuditService->canProcessVoucher($voucher, auth()->id())) {
        //     abort(403, 'You are not authorized to view this voucher.');
        // }

        $voucher->load(['mda', 'financialYear', 'items', 'documents', 'approvals.user', 'creator']);

        $missingDocuments = $this->internalAuditService->checkRequiredDocuments($voucher);
        $requiredDocuments = $this->internalAuditService->getRequiredDocuments();

        $formattedVoucher = [
            'id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'voucher_type' => $voucher->voucher_type,
            'voucher_date' => $voucher->voucher_date?->format('Y-m-d'),
            'narration' => $voucher->narration,
            'total_amount' => $voucher->total_amount,
            'status' => $voucher->status,
            'current_stage' => $voucher->current_stage,
            'mda' => $voucher->mda ? [
                'id' => $voucher->mda->id,
                'name' => $voucher->mda->name,
                'initials' => $voucher->mda->initials,
            ] : null,
            'financial_year' => $voucher->financialYear ? [
                'id' => $voucher->financialYear->id,
                'name' => $voucher->financialYear->name,
            ] : null,
            'creator' => $voucher->creator ? [
                'id' => $voucher->creator->id,
                'name' => $voucher->creator->name,
                'email' => $voucher->creator->email,
            ] : null,
            'items' => $voucher->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'sub_total' => $item->sub_total,
                ];
            }),
            'documents' => $voucher->documents->map(function ($document) {
                return [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'file_path' => $document->file_path,
                    'file_size' => $document->file_size,
                    'document_type' => $document->document_type,
                    'document_label' => $document->document_label,
                    'uploaded_at' => $document->created_at?->toDateTimeString(),
                ];
            }),
            'approvals' => $voucher->approvals->map(function ($approval) {
                return [
                    'id' => $approval->id,
                    'approval_role' => $approval->approval_role,
                    'action' => $approval->action,
                    'status' => $approval->status,
                    'comment' => $approval->comment,
                    'action_at' => $approval->action_at?->toDateTimeString(),
                    'user' => $approval->user ? [
                        'id' => $approval->user->id,
                        'name' => $approval->user->name,
                    ] : null,
                ];
            }),
        ];

        return Inertia::render('admin/internalAudit/show', [
            'voucher' => $formattedVoucher,
            'missingDocuments' => $missingDocuments,
            'requiredDocuments' => $requiredDocuments,
            // 'canProcess' => $this->internalAuditService->canProcessVoucher($voucher, auth()->id()),
        ]);
    }

    public function approve(Voucher $voucher, Request $request)
    {
        Log::info('Internal Audit Approval Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        try {
            // Check if user can process this voucher
            // if (!$this->internalAuditService->canProcessVoucher($voucher, auth()->id())) {
            //     return back()->withErrors([
            //         'authorization' => 'You are not authorized to approve this voucher or it is not in the correct status.'
            //     ]);
            // }

            $result = $this->internalAuditService->approveVoucher(
                $voucher,
                $request->all(),
                auth()->id()
            );

            return redirect()->route('internal-audits.index')
                ->with('success', "Voucher {$voucher->voucher_number} approved successfully.");
        } catch (\Exception $e) {
            Log::error('Internal Audit Approval Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->with('error', 'Failed to approve voucher: ' . $e->getMessage());
        }
    }

    public function reject(Voucher $voucher, Request $request)
    {
        Log::info('Internal Audit Rejection Request:', [
            'voucher_id' => $voucher->id,
            'voucher_number' => $voucher->voucher_number,
            'user_id' => auth()->id(),
            'request_data' => $request->all()
        ]);

        try {
            // Check if user can process this voucher
            // if (!$this->internalAuditService->canProcessVoucher($voucher, auth()->id())) {
            //     return back()->withErrors([
            //         'authorization' => 'You are not authorized to reject this voucher or it is not in the correct status.'
            //     ]);
            // }

            $request->validate([
                'reason' => 'required|string|min:10|max:500'
            ]);

            $result = $this->internalAuditService->rejectVoucher(
                $voucher,
                $request->all(),
                auth()->id()
            );

            return redirect()->route('internal-audits.index')
                ->with('success', "Voucher {$voucher->voucher_number} rejected and returned to originator.");
        } catch (\Exception $e) {
            Log::error('Internal Audit Rejection Failed:', [
                'voucher_id' => $voucher->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->with('error', 'Failed to reject voucher: ' . $e->getMessage());
        }
    }

    /**
     * Get required documents list (API endpoint for frontend)
     */
    public function getRequiredDocuments()
    {
        $requiredDocuments = $this->internalAuditService->getRequiredDocuments();

        $formattedDocuments = collect($requiredDocuments)->map(function ($docType) {
            return [
                'type' => $docType,
                'label' => $this->internalAuditService->getDocumentTypeLabel($docType),
                'required' => true,
            ];
        });

        return response()->json([
            'required_documents' => $formattedDocuments,
        ]);
    }

    /**
     * Check voucher documents status (API endpoint)
     */
    public function checkDocuments(Voucher $voucher)
    {
        $missingDocuments = $this->internalAuditService->checkRequiredDocuments($voucher);
        $allDocuments = $voucher->documents->map(function ($document) {
            return [
                'type' => $document->document_type,
                'label' => $document->document_label,
                'file_name' => $document->file_name,
                'uploaded' => true,
            ];
        });

        return response()->json([
            'missing_documents' => $missingDocuments,
            'uploaded_documents' => $allDocuments,
            'is_complete' => empty($missingDocuments),
        ]);
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
            $query = $query->where('status', 'submitted');
            $vouchers = $query->with(['mda', 'creator', 'documents'])
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

                        'creator' => $voucher->creator ? [
                            'id' => $voucher->creator->id,
                            'name' => $voucher->creator->name,
                        ] : null,
                        'created_at' => $voucher->created_at?->toDateTimeString(),
                        'documents_count' => $voucher->documents->count(),
                        'documents' => $voucher->documents->map(function ($document) {
                            return [
                                'id' => $document->id,
                                'file_name' => $document->file_name,
                                'document_type' => $document->document_type,
                                'document_label' => $document->document_label,
                                'file_path' => $document->file_path,
                                'url' => Storage::url($document->file_path), // Generate full URL
                            ];
                        }),
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
}
