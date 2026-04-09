<?php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\Schedule;
use App\Models\EconomyCode;
use Illuminate\Http\Request;
use App\Models\FinancialYear;
use App\Models\EconomyCodeItem;
use App\Services\ScheduleService;
use App\Models\AdministrativeCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\AdministrativeSectorCode;
use App\Models\Mda;
use App\Models\Payee;
use Carbon\Carbon;
use App\Models\AdministrativeSectorCode as administrativeCodeItem;
use App\Models\ScheduleItem;
use App\Services\ActivityLogger;


class ScheduleController extends Controller
{
    protected ScheduleService $scheduleService;
    protected $activityLogger;


    public function __construct(ScheduleService $scheduleService, ActivityLogger $activityLogger)
    {
        $this->scheduleService = $scheduleService;
        $this->activityLogger = $activityLogger;
    }

    /**
     * Display a listing of the schedules.
     */
    public function index()
    {
        try {
            $schedules = Schedule::with(['mda'])
                ->withCount('items')
                ->orderBy('created_at', 'desc')
                ->paginate(10)
                ->through(function ($schedule) {
                    return [
                        'id' => $schedule->id,
                        'schedule_number' => $schedule->schedule_number,
                        'schedule_date' => $schedule->created_at->toDateString(),
                        'total_amount' => $schedule->total_amount,
                        'amount_posted' => $schedule->vouchers->sum('total_amount'),
                        'voucher_count' => $schedule->vouchers->count(),
                        'status' => $schedule->status,
                        'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
                        'mda' => $schedule->mda ? [
                            'id' => $schedule->mda->id,
                            'name' => $schedule->mda->name,
                        ] : null,
                        'items_count' => $schedule->items_count,
                        'payee_name' => $schedule->items_count > 0 ? (
                            $schedule->items->first()?->payee_name .
                            ($schedule->items_count > 1 ? ' & Others' : '')
                        ) : '',
                    ];
                });

            // dd($schedules);

            return Inertia::render('admin/schedules/index', [
                'schedules' => $schedules,
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule Index Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to load schedules.');
        }
    }

    /**
     * Show the form for creating a new schedule.
     */
    public function create()
    {
        // 1. Fetch Administrative Codes (Sectors and MDAs)
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

        // 3. Get Financial Years
        $financialYears = FinancialYear::where('is_active', true)
            ->get()
            ->map(function ($year) {
                return [
                    'value' => $year->id,
                    'label' => $year->name,
                ];
            });

        // 4. Predict next schedule number (Placeholder used here since MDA is not selected)
        $nextScheduleNumber = 'SCH/PENDING/000/' . date('Y');

        // 5. Get Economic Codes and Items (for line items)
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
            ->where('status', 'active')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                    'economy_code_id' => $item->economy_code_id,
                ];
            })->toArray();

        $mdas = Mda::select('id', 'name', 'administrative_code_id')
            ->orderBy('name')
            ->get();

        $payees = Payee::select('id', 'name')
            ->orderBy('name')
            ->get()
            ->map(function ($payee) {
                return [
                    'value' => $payee->name,
                    'label' => $payee->name,
                ];
            });

        return Inertia::render('admin/schedules/create', [
            'administrativeCodes' => $administrativeCodes,
            'administrativeSectorCodes' => $administrativeSectorCodes,
            'financialYears' => $financialYears,
            'economyCodes' => $economyCodes,
            'economyCodeItems' => $economyCodeItems,
            'nextScheduleNumber' => $nextScheduleNumber,
            'payees' => $payees,
            'mdas' => $mdas
        ]);
    }

    /**
     * Store a newly created schedule in storage.
     */
    public function store(ScheduleRequest $request)
    {
        $validated = $request->validated();

        try {
            $schedule = $this->scheduleService->createSchedule($validated);

            if ($request->input('status') === 'Submitted' || $request->input('status') === 'Processed') {
                return redirect()
                    ->route('vouchers.create', ['schedule_id' => $schedule->id])
                    ->with('success', "Schedule {$schedule->schedule_number} created and ready for voucher generation.");
            }

            return redirect()
                ->route('schedules.index')
                ->with('success', "Schedule {$schedule->schedule_number} saved as a Draft.");
        } catch (\Exception $e) {
            Log::error('Schedule Store Failed: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create schedule: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified schedule.
     */
    public function show(Schedule $schedule)
    {
        $schedule->load(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode', 'creator']);

        return Inertia::render('admin/schedules/show', [
            'schedule' => [
                'id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'status' => $schedule->status,
                'date' => $schedule->schedule_date->format('Y-m-d'),
                'total_amount' => $schedule->total_amount,
                'narration' => $schedule->narration ?? 'Payment Schedule',
                'mda' => $schedule->budgetCode ? [
                    'name' => $schedule->budgetCode->name,
                    'code' => $schedule->budgetCode->code
                ] : null,
                'financial_year' => $schedule->financialYear?->name,
                'budget_code' => $schedule->budgetCode?->code,
                
                'items' => $schedule->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'date' => $item->item_date->format('d/m/Y'),
                        'serial_no' => $item->serial_number,
                        'economy_code' => $item->economyCode ? $item->economyCode->code . ' - ' . $item->economyCode->name : 'N/A',
                        'economy_code_item' => $item->economyCodeItem ? $item->economyCodeItem->code . ' - ' . $item->economyCodeItem->name : 'N/A',
                        'payee' => $item->payee_name,
                        'amount' => $item->amount,
                    ];
                }),
            ]
        ]);
    }

    /**
     * Show the form for editing the specified schedule.
     */
    public function edit(Schedule $schedule)
    {
        $schedule->load(['items.economyCode', 'items.economyCodeItem', 'mda', 'financialYear', 'budgetCode']);

        // Fetch Dependency Data for Edit Form
        $administrativeCodes = DB::table('administrative_codes')
            ->select('id', 'name', 'code')
            ->where('status', 1)
            ->orderBy('code')
            ->get()
            ->toArray();

        $administrativeSectorCodes = DB::table('administrative_sector_codes')
            ->select('id', 'code', 'name', 'administrative_code_id', 'initials')
            ->orderBy('code')
            ->get()
            ->toArray();

        $economyCodes = EconomyCode::select('id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($code) {
                return [
                    'value' => $code->id,
                    'label' => $code->name . ' - ' . $code->code,
                ];
            })->toArray();

        $economyCodeItems = EconomyCodeItem::with('economyCode:id,code,name')
            ->select('id', 'economy_code_id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->name . ' - ' . $item->code,
                    'economy_code_id' => $item->economy_code_id,
                ];
            })->toArray();

            $mdas = Mda::select('id', 'name', 'administrative_code_id')
            ->orderBy('name')
            ->get();

        // Transform data for Vue with updated field names
        $formattedSchedule = [
            'id' => $schedule->id,
            'schedule_number' => $schedule->schedule_number,
            'year_id' => $schedule->year_id,
            'mda_id' => $schedule->mda_id,
            'budget_code_id' => $schedule->budget_code_id,
            'status' => $schedule->status,
            'total_amount' => $schedule->total_amount,
            'sector' => $schedule->budgetCode?->administrative_code_id,
            'items' => $schedule->items->map(function ($item) {
                // dd($item);
                return [
                    'id' => $item->id,
                    'date' => $item->item_date->format('Y-m-d'),
                    'serial_no' => $item->serial_number,
                    'economy_code_id' => $item->economy_code_id, // Updated field
                    'economy_code_item_id' => $item->economy_code_item_id, // Updated field
                    'payee_name' => $item->payee_name,
                    'amount' => $item->amount,
                ];
            }),
        ];

        $financialYears = FinancialYear::where('is_active', true)
            ->get()
            ->map(function ($year) {
                return [
                    'value' => $year->id,
                    'label' => $year->name,
                ];
            });

        return Inertia::render('admin/schedules/edit', [
            'schedule' => $formattedSchedule,
            'administrativeCodes' => $administrativeCodes,
            'administrativeSectorCodes' => $administrativeSectorCodes,
            'financialYears' => $financialYears,
            'economyCodes' => $economyCodes,
            'economyCodeItems' => $economyCodeItems,
            'mdas' => $mdas,
        ]);
    }

    /**
     * Update the specified schedule.
     */
    public function update(ScheduleRequest $request, Schedule $schedule) // Use ScheduleRequest here too
    {
        $validated = $request->validated();

        try {
            $this->scheduleService->updateSchedule($schedule, $validated);
            return redirect()->route('schedules.index')->with('success', 'Schedule updated successfully');
        } catch (\Exception $e) {
            Log::error('Schedule Update Failed: ' . $e->getMessage());
            return back()->with('error', 'Update failed: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified schedule.
     */
    public function destroy(Schedule $schedule)
    {
        try {
            $this->scheduleService->deleteSchedule($schedule);
            return redirect()->route('schedules.index')->with('success', 'Schedule deleted successfully');
        } catch (\Exception $e) {
            Log::error('Schedule Deletion Failed: ' . $e->getMessage());
            return back()->with('error', 'Deletion failed');
        }
    }

    /**
     * Print the Schedule
     */
    // public function print(Schedule $schedule)
    // {
    //     $schedule->load(['items.economyCode', 'items.economyCodeItem', 'mda', 'budgetCode']);

    //     return Inertia::render('admin/schedules/print', [
    //         'schedule' => $schedule
    //     ]);
    // }
    // In your ScheduleController or similar
    // public function print(Schedule $schedule)
    // {
    //     return inertia('admin/schedules/print', [
    //         'schedule' => $schedule->load(['items', 'financialYear', 'mda', 'budgetCode']),
    //         'administrativeCodes' => AdministrativeCode::all(),
    //         'administrativeSectorCodes' => AdministrativeSectorCode::all(),
    //         'economyCodes' => EconomyCode::all(),
    //         'economyCodeItems' => EconomyCodeItem::all(),
    //     ]);
    // }

    // In your ScheduleController
    public function print(Schedule $schedule)
    {
        // Eager load all necessary relationships
        $schedule->load([
            'items',
            'financialYear',
            'mda', // This should be the relationship to AdministrativeCode
            'budgetCode', // This should be the relationship to AdministrativeSectorCode
            'items.economyCode', // If you have Economic Code relationship in items
            'items.economyCodeItem', // If you have Economic Code item relationship in items
        ]);


        $this->activityLogger->log(
            "Updated receipt {$schedule->schedule_number}",
            [
            
                'schedule_id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'mda_id' => $schedule->mda_id,
                'budget_code_id' => $schedule->budget_code_id,
                'status' => $schedule->status,
                'total_amount' => $schedule->total_amount,
                'schedule_date' => $schedule->schedule_date,
                'year_id' => $schedule->year_id,
                'updated_at' => $schedule->updated_at,
                'user_id' => auth()->id()
            ],
            'Schedule',
        );

        return inertia('admin/schedules/print', [
            'schedule' => $schedule,
            'administrativeCodes' => AdministrativeCode::all(), // Fallback if relationships not loaded
            'administrativeSectorCodes' => AdministrativeSectorCode::all(), // Fallback
            'economyCodes' => EconomyCode::all(),
            'economyCodeItems' => EconomyCodeItem::all(),
        ]);
    }

    public function getNextNumber(Request $request)
    {
        $request->validate([
            'mda_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);

        $data = $this->scheduleService->generateNextScheduleNumber(
            $request->mda_id,
            $request->year_id
        );

        return response()->json($data);
    }

    /**
     * Get all Economic Codes for dropdown
     */
    public function getEconomyCodes()
    {
        $economyCodes = EconomyCode::select('id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($code) {
                return [
                    'value' => $code->id,
                    'label' => $code->code . ' - ' . $code->name,
                ];
            });

        return response()->json($economyCodes);
    }

    /**
     * Get Economic Code items for a specific Economic Code
     */
    public function getEconomyCodeItems($economyCodeId)
    {
        $items = EconomyCodeItem::where('economy_code_id', $economyCodeId)
            ->select('id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                ];
            });

        return response()->json($items);
    }

    /**
     * Get all Economic Code items (for initial page load)
     */
    public function getAllEconomyCodeItems()
    {
        $items = EconomyCodeItem::with('economyCode:id,code,name')
            ->select('id', 'economy_code_id', 'code', 'name')
            ->orderBy('code')
            ->get()
            ->map(function ($item) {
                return [
                    'value' => $item->id,
                    'label' => $item->code . ' - ' . $item->name,
                    'economy_code_id' => $item->economy_code_id,
                ];
            });

        return response()->json($items);
    }


    public function getPayees(Request $request)
    {
        $filter = $request->input('filter', '');
        $items = Payee::when($filter, function ($query, $filter) {
            return $query->where('name', 'like', "%{$filter}%");
        })
            ->paginate(15); // Paginate the results

        return response()->json($items);
    }




    public function search(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 100);
            $search = $request->input('search', '');
            $query = Schedule::query();

            if ($search !== '') {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $term) {
                    $query->orWhere(function ($subQuery) use ($term) {
                        $subQuery->where('schedule_number', 'like', "%{$term}%");
                    })
                        ->orWhere(function ($subQuery) use ($term) {
                            $mda = Mda::where('name', 'like', "%{$term}%")->orWhere('new_name', 'like', "%{$term}%")->orWhere('oracle_name', 'like', "%{$term}%")->orderBy('name')->limit(200)->pluck('id')->toArray();
                            $subQuery->whereIn('mda_id', $mda);
                        })
                        ->orWhere(function ($subQuery) use ($term) {
                            $adminCode = AdministrativeCodeItem::where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")->orderBy('name')->limit(200)->pluck('id')->toArray();
                            $subQuery->whereIn('budget_code_id', $adminCode);
                        })
                        ->orWhere(function ($subQuery) use ($term) {
                            $payeeName = ScheduleItem::where('payee_name', 'like', "%{$term}%")->limit(200)->pluck('schedule_id')->toArray();
                            $subQuery->whereIn('id', $payeeName);
                        })
                        ->orWhere('total_amount', 'like', "%{$term}%")
                        ->orWhere('status', 'like', "%{$term}%")
                        ->orWhere('schedule_date', 'like', "%{$term}%");
                }
            }


            if ($request->has('date_from') && !empty($request->date_from)) {
                $query->whereDate('schedule_date', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
            }

            if ($request->has('date_to') && !empty($request->date_to)) {
                $query->whereDate('schedule_date', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
            }

            $perPage = $request->input('per_page', 20);

            // Paginate results
            $schedules = $query->with('mda', 'budgetCode')->withCount('items')->latest()->paginate($perPage)->through(function ($schedule) {
                return [
                    'id' => $schedule->id,
                    'schedule_number' => $schedule->schedule_number,
                    'schedule_date' => $schedule->created_at->toDateString(),
                    'total_amount' => $schedule->total_amount,
                    'amount_posted' => $schedule->vouchers->sum('total_amount'),
                    'voucher_count' => $schedule->vouchers->count(),
                    'status' => $schedule->status,
                    'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
                    'mda' => $schedule->mda ? [
                        'id' => $schedule->mda->id,
                        'name' => $schedule->mda->name,
                    ] : null,
                    'items_count' => $schedule->items_count,
                    'payee_name' => $schedule->items_count > 0 ? (
                        $schedule->items->first()?->payee_name .
                        ($schedule->items_count > 1 ? ' & Others' : '')
                    ) : '',
                ];
            });


            // dd($schedules);

            // $schedules = $query->latest()->paginate($perPage);

            $paginator = [
                "total" => $schedules->total(),
                "per_page" => $schedules->perPage(),
                "current_page" => $schedules->currentPage(),
                "last_page" => $schedules->lastPage(),
                "first_page_url" => $schedules->url(1),
                "last_page_url" => $schedules->url($schedules->lastPage()),
                "next_page_url" => $schedules->nextPageUrl(),
                "prev_page_url" => $schedules->previousPageUrl(),
                "path" => $schedules->path(),
                "from" => $schedules->currentPage(),
                "to" => $schedules->perPage(),
            ];

            // Log search activity
            $this->activityLogger->log(
                "Searched schedules",
                [
                    'search_term' => $search,
                    'results_count' => $schedules->total(),
                    'per_page' => $perPage,
                    'user_id' => auth()->id()
                ],
                'schedules'
            );


            return response()->json([
                'status' => 'success',
                'schedules' => $schedules,
                // 'mdas' => $mdas,
                // 'bank_activities' => $bank_activities,
                // 'receipt_activities' => $receipt_activities,
                // 'economyCodes' => $economyCodesForDropdown,
                // 'economyCodeItems' => $formattedEconomyCodeItems,
                'paginator' => $paginator
            ]);
        } catch (\Exception $e) {
            \Log::error('Receipt Search Error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Schedule Index Error: ' . $e->getMessage()]);
        }
    }
}
