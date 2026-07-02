<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleRequest;
use App\Models\AdministrativeCode;
use App\Models\AdministrativeSectorCode as administrativeCodeItem;
use App\Models\AdministrativeSectorCode;
use App\Models\EconomyCode;
use App\Models\EconomyCodeItem;
use App\Models\FinancialYear;
use App\Models\Mda;
use App\Models\Payee;
use App\Models\Schedule;
use App\Models\Voucher;
use App\Models\ScheduleItem;
use App\Services\ActivityLogger;
use App\Services\ScheduleService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use PhpOffice\PhpSpreadsheet\Writer\Pdf;

class ScheduleController extends Controller
{
    protected ScheduleService $scheduleService;
    protected $activityLogger;

    public function __construct(ScheduleService $scheduleService, ActivityLogger $activityLogger)
    {
        $this->scheduleService = $scheduleService;
        $this->activityLogger = $activityLogger;
    }

    

    // /**
    //  * Get the MDAs assigned to the current user as array
    //  */
    // private function getUserAssignedMdas(): array
    // {
    //     $user = Auth::user();
        
    //     if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
    //         return [];
    //     }
        
    //     $mdas = $user->mdas()
    //         ->select('mdas.id', 'mdas.name', 'mdas.code')
    //         ->get()
    //         ->map(function($mda) {
    //             return [
    //                 'id' => $mda->id,
    //                 'name' => $mda->name,
    //                 'code' => $mda->code ?? '',
    //             ];
    //         })
    //         ->toArray();
        
    //     return $mdas;
    // }

    // private function getUserAssignedMdaIds(): array
    // {
    //     $mdas = $this->getUserAssignedMdas();
    //     return array_column($mdas, 'id');
    // }

    // private function hasAssignedMdas(): bool
    // {
    //     $user = Auth::user();
    //     if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
    //         return true;
    //     }
    //     return $user->mdas()->exists();
    // }

    // private function isAdmin(): bool
    // {
    //     $user = Auth::user();
    //     return $user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules');
    // }

    // private function applyMdaFilter($query)
    // {
    //     $user = Auth::user();
    //     if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
    //         return $query;
    //     }
        
    //     $mdaIds = $this->getUserAssignedMdaIds();
    //     if (empty($mdaIds)) {
    //         return $query->whereRaw('1 = 0');
    //     }
        
    //     return $query->whereIn('mda_id', $mdaIds);
    // }

    // private function canCreateSchedule(): bool
    // {
    //     $user = Auth::user();
    //     if (!$user->can('schedule.create')) return false;
    //     if (empty($user->signature)) return false;
    //     if ($user->can_be_signatory !== true) return false;
    //     return true;
    // }

    // /**
    //  * Display a listing of the schedules with statistics.
    //  */
    // public function index(Request $request)
    // {
    //     try {
    //         $user = Auth::user();
            
    //         // ✅ Check permissions
    //         $canCreateSchedule = $this->canCreateSchedule();
    //         $hasSignature = !empty($user->signature);
    //         $canBeSignatory = $user->can_be_signatory === true;
            
    //         // ✅ Get user's assigned MDAs
    //         $assignedMdas = $this->getUserAssignedMdas();
    //         $isAdmin = $this->isAdmin();
    //         $hasMdas = $this->hasAssignedMdas();

    //         // ✅ Get statistics
    //         $statsQuery = Schedule::query();
    //         $statsQuery = $this->applyMdaFilter($statsQuery);
            
    //         // Get all schedules with their vouchers and items for stats
    //         $schedulesForStats = (clone $statsQuery)->with(['vouchers', 'items', 'items.voucher'])->get();
            
    //         $stats = [
    //             'total_schedules' => $schedulesForStats->count(),
    //             'draft_count' => $schedulesForStats->filter(function($schedule) {
    //                 return in_array(strtolower($schedule->status), ['draft', 'saved']);
    //             })->count(),
    //             'submitted_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'submitted';
    //             })->count(),
    //             'processed_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'processed';
    //             })->count(),
    //             'approved_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'approved';
    //             })->count(),
    //             'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'voucher raised';
    //             })->count(),
    //             'rejected_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'rejected';
    //             })->count(),
    //             'total_amount' => (float) $schedulesForStats->sum('total_amount'),
    //             'total_amount_posted' => (float) $schedulesForStats->sum(function($schedule) {
    //                 return $schedule->vouchers->sum('total_amount');
    //             }),
    //         ];

    //         // ✅ Build query with filters
    //         $query = Schedule::with(['mda', 'budgetCode', 'items', 'items.voucher', 'vouchers'])
    //             ->withCount('items');
            
    //         // Apply MDA filter
    //         $query = $this->applyMdaFilter($query);

    //         // Apply search filter
    //         $search = $request->input('search', '');
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             foreach ($words as $term) {
    //                 $query->where(function ($q) use ($term) {
    //                     $q->where('schedule_number', 'like', "%{$term}%")
    //                     ->orWhereHas('mda', function ($mdaQuery) use ($term) {
    //                         $mdaQuery->where('name', 'like', "%{$term}%");
    //                     })
    //                     ->orWhereHas('items', function ($itemQuery) use ($term) {
    //                         $itemQuery->where('payee_name', 'like', "%{$term}%");
    //                     })
    //                     ->orWhere('total_amount', 'like', "%{$term}%")
    //                     ->orWhere('status', 'like', "%{$term}%");
    //                 });
    //             }
    //         }

    //         // Apply status filter
    //         if ($request->has('status') && $request->status) {
    //             $query->where('status', $request->status);
    //         }

    //         // Apply date range filter
    //         if ($request->has('date_from') && $request->date_from) {
    //             $query->whereDate('schedule_date', '>=', $request->date_from);
    //         }
    //         if ($request->has('date_to') && $request->date_to) {
    //             $query->whereDate('schedule_date', '<=', $request->date_to);
    //         }

    //         // Apply MDA filter from dropdown
    //         if ($request->has('mda_id') && $request->mda_id) {
    //             $query->where('mda_id', $request->mda_id);
    //         }

    //         $perPage = $request->input('per_page', 15);
    //         $schedules = $query
    //             ->orderBy('created_at', 'desc')
    //             ->paginate($perPage)
    //             ->through(function ($schedule) {
    //                 // Calculate amount posted from vouchers
    //                 $amountPosted = $schedule->vouchers->sum('total_amount');
                    
    //                 // ✅ Count processed and unprocessed items using voucher relationship
    //                 $totalItems = $schedule->items->count();
    //                 $processedItems = $schedule->items->filter(function($item) {
    //                     return !is_null($item->voucher_id);
    //                 })->count();
    //                 $unprocessedItems = $totalItems - $processedItems;
                    
    //                 // ✅ Check if all items are processed
    //                 $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
                    
    //                 return [
    //                     'id' => $schedule->id,
    //                     'schedule_number' => $schedule->schedule_number,
    //                     'schedule_date' => $schedule->created_at->toDateString(),
    //                     'total_amount' => $schedule->total_amount,
    //                     'amount_posted' => $amountPosted,
    //                     'voucher_count' => $schedule->vouchers->count(),
    //                     'voucher_id' => $schedule->vouchers->first()?->id,
    //                     'status' => $schedule->status,
    //                     'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
    //                     'mda' => $schedule->mda ? [
    //                         'id' => $schedule->mda->id,
    //                         'name' => $schedule->mda->name,
    //                         'code' => $schedule->mda->code,
    //                     ] : null,
    //                     'items_count' => $totalItems,
    //                     'processed_items' => $processedItems,
    //                     'unprocessed_items' => $unprocessedItems,
    //                     'all_items_processed' => $allItemsProcessed,
    //                     'payee_name' => $schedule->items_count > 0 ? (
    //                         $schedule->items->first()?->payee_name .
    //                         ($schedule->items_count > 1 ? ' & Others' : '')
    //                     ) : '',
    //                 ];
    //             });

    //         // Get MDAs for filter dropdown
    //         $mdasQuery = Mda::select('id', 'name')->orderBy('name');
    //         if (!$isAdmin) {
    //             $mdaIds = $this->getUserAssignedMdaIds();
    //             if (!empty($mdaIds)) {
    //                 $mdasQuery->whereIn('id', $mdaIds);
    //             }
    //         }
    //         $mdas = $mdasQuery->get();

    //         // Status options for filter
    //         $statusOptions = [
    //             ['label' => 'All Status', 'value' => ''],
    //             ['label' => 'Draft', 'value' => 'draft'],
    //             ['label' => 'Saved', 'value' => 'saved'],
    //             ['label' => 'Submitted', 'value' => 'submitted'],
    //             ['label' => 'Processed', 'value' => 'processed'],
    //             ['label' => 'Approved', 'value' => 'approved'],
    //             ['label' => 'Voucher Raised', 'value' => 'voucher raised'],
    //             ['label' => 'Rejected', 'value' => 'rejected'],
    //         ];

    //         return Inertia::render('admin/schedules/index', [
    //             'schedules' => $schedules,
    //             'stats' => $stats,
    //             'mdas' => $mdas,
    //             'statusOptions' => $statusOptions,
    //             'permissions' => [
    //                 'can_create_schedule' => $canCreateSchedule,
    //                 'has_signature' => $hasSignature,
    //                 'can_be_signatory' => $canBeSignatory,
    //             ],
    //             'userMdas' => $assignedMdas,
    //             'isAdmin' => $isAdmin,
    //             'hasMdas' => $hasMdas,
    //             'filters' => [
    //                 'search' => $request->input('search', ''),
    //                 'status' => $request->input('status', ''),
    //                 'mda_id' => $request->input('mda_id', ''),
    //                 'date_from' => $request->input('date_from', ''),
    //                 'date_to' => $request->input('date_to', ''),
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Schedule Index Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
    //         return Inertia::render('admin/schedules/index', [
    //             'schedules' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'current_page' => 1,
    //                 'per_page' => 15,
    //                 'links' => [],
    //             ],
    //             'stats' => [
    //                 'total_schedules' => 0,
    //                 'draft_count' => 0,
    //                 'submitted_count' => 0,
    //                 'processed_count' => 0,
    //                 'approved_count' => 0,
    //                 'voucher_raised_count' => 0,
    //                 'rejected_count' => 0,
    //                 'total_amount' => 0,
    //                 'total_amount_posted' => 0,
    //             ],
    //             'mdas' => [],
    //             'statusOptions' => [],
    //             'permissions' => [
    //                 'can_create_schedule' => false,
    //                 'has_signature' => false,
    //                 'can_be_signatory' => false,
    //             ],
    //             'userMdas' => [],
    //             'isAdmin' => false,
    //             'hasMdas' => false,
    //             'filters' => [
    //                 'search' => '',
    //                 'status' => '',
    //                 'mda_id' => '',
    //                 'date_from' => '',
    //                 'date_to' => '',
    //             ],
    //         ]);
    //     }
    // }

    // /**
    //  * Search schedules (API endpoint) with filters
    //  */
    // public function search(Request $request)
    // {
    //     try {
    //         $perPage = $request->input('per_page', 20);
    //         $search = $request->input('search', '');
    //         $status = $request->input('status', '');
    //         $mdaId = $request->input('mda_id', '');
    //         $dateFrom = $request->input('date_from', '');
    //         $dateTo = $request->input('date_to', '');
            
    //         $query = Schedule::with(['mda', 'budgetCode', 'items', 'items.voucher', 'vouchers'])
    //             ->withCount('items');
    //         $query = $this->applyMdaFilter($query);

    //         // Apply status filter
    //         if ($status) {
    //             $query->where('status', $status);
    //         }

    //         // Apply MDA filter
    //         if ($mdaId) {
    //             $query->where('mda_id', $mdaId);
    //         }

    //         // Apply date range filter
    //         if ($dateFrom) {
    //             $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
    //         }
    //         if ($dateTo) {
    //             $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
    //         }

    //         // Apply search
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             $query->where(function ($mainQuery) use ($words) {
    //                 foreach ($words as $term) {
    //                     $mainQuery->orWhere('schedule_number', 'like', "%{$term}%")
    //                         ->orWhereHas('mda', function ($mdaQuery) use ($term) {
    //                             $mdaQuery->where('name', 'like', "%{$term}%");
    //                         })
    //                         ->orWhereHas('items', function ($itemQuery) use ($term) {
    //                             $itemQuery->where('payee_name', 'like', "%{$term}%");
    //                         })
    //                         ->orWhere('total_amount', 'like', "%{$term}%")
    //                         ->orWhere('status', 'like', "%{$term}%");
    //                 }
    //             });
    //         }

    //         $schedules = $query->latest()
    //             ->paginate($perPage)
    //             ->through(function ($schedule) {
    //                 $amountPosted = $schedule->vouchers->sum('total_amount');
                    
    //                 // ✅ Count processed and unprocessed items
    //                 $totalItems = $schedule->items->count();
    //                 $processedItems = $schedule->items->filter(function($item) {
    //                     return !is_null($item->voucher_id);
    //                 })->count();
    //                 $unprocessedItems = $totalItems - $processedItems;
    //                 $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
                    
    //                 return [
    //                     'id' => $schedule->id,
    //                     'schedule_number' => $schedule->schedule_number,
    //                     'schedule_date' => $schedule->created_at->toDateString(),
    //                     'total_amount' => $schedule->total_amount,
    //                     'amount_posted' => $amountPosted,
    //                     'voucher_count' => $schedule->vouchers->count(),
    //                     'voucher_id' => $schedule->vouchers->first()?->id,
    //                     'status' => $schedule->status,
    //                     'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
    //                     'mda' => $schedule->mda ? [
    //                         'id' => $schedule->mda->id,
    //                         'name' => $schedule->mda->name,
    //                         'code' => $schedule->mda->code,
    //                     ] : null,
    //                     'items_count' => $totalItems,
    //                     'processed_items' => $processedItems,
    //                     'unprocessed_items' => $unprocessedItems,
    //                     'all_items_processed' => $allItemsProcessed,
    //                     'payee_name' => $schedule->items_count > 0 ? (
    //                         $schedule->items->first()?->payee_name .
    //                         ($schedule->items_count > 1 ? ' & Others' : '')
    //                     ) : '',
    //                 ];
    //             });

    //         // Get stats with filters
    //         $statsQuery = Schedule::with(['items', 'items.voucher']);
    //         $statsQuery = $this->applyMdaFilter($statsQuery);
            
    //         if ($status) {
    //             $statsQuery->where('status', $status);
    //         }
    //         if ($mdaId) {
    //             $statsQuery->where('mda_id', $mdaId);
    //         }
    //         if ($dateFrom) {
    //             $statsQuery->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
    //         }
    //         if ($dateTo) {
    //             $statsQuery->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
    //         }

    //         $schedulesForStats = $statsQuery->get();

    //         $stats = [
    //             'total_schedules' => $schedulesForStats->count(),
    //             'total_amount' => (float) $schedulesForStats->sum('total_amount'),
    //             'draft_count' => $schedulesForStats->filter(function($schedule) {
    //                 return in_array(strtolower($schedule->status), ['draft', 'saved']);
    //             })->count(),
    //             'submitted_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'submitted';
    //             })->count(),
    //             'processed_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'processed';
    //             })->count(),
    //             'approved_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'approved';
    //             })->count(),
    //             'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'voucher raised';
    //             })->count(),
    //             'rejected_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'rejected';
    //             })->count(),
    //         ];

    //         $paginator = [
    //             "total" => $schedules->total(),
    //             "per_page" => $schedules->perPage(),
    //             "current_page" => $schedules->currentPage(),
    //             "last_page" => $schedules->lastPage(),
    //             "first_page_url" => $schedules->url(1),
    //             "last_page_url" => $schedules->url($schedules->lastPage()),
    //             "next_page_url" => $schedules->nextPageUrl(),
    //             "prev_page_url" => $schedules->previousPageUrl(),
    //             "path" => $schedules->path(),
    //             "from" => $schedules->firstItem(),
    //             "to" => $schedules->lastItem(),
    //         ];

    //         return response()->json([
    //             'status' => 'success',
    //             'schedules' => $schedules,
    //             'stats' => $stats,
    //             'paginator' => $paginator
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Schedule Search Error: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'Schedule Search Error: ' . $e->getMessage()
    //         ]);
    //     }
    // }

    // /**
    //  * Export schedules to Excel
    //  */
    // public function exportExcel(Request $request)
    // {
    //     try {
    //         $search = $request->input('search', '');
    //         $status = $request->input('status', '');
    //         $mdaId = $request->input('mda_id', '');
    //         $dateFrom = $request->input('date_from', '');
    //         $dateTo = $request->input('date_to', '');
            
    //         $query = Schedule::with(['mda', 'budgetCode', 'vouchers', 'items']);
    //         $query = $this->applyMdaFilter($query);

    //         if ($status) {
    //             $query->where('status', $status);
    //         }
    //         if ($mdaId) {
    //             $query->where('mda_id', $mdaId);
    //         }
    //         if ($dateFrom) {
    //             $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
    //         }
    //         if ($dateTo) {
    //             $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
    //         }
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             foreach ($words as $term) {
    //                 $query->orWhere('schedule_number', 'like', "%{$term}%")
    //                     ->orWhereHas('mda', function ($mdaQuery) use ($term) {
    //                         $mdaQuery->where('name', 'like', "%{$term}%");
    //                     })
    //                     ->orWhereHas('items', function ($itemQuery) use ($term) {
    //                         $itemQuery->where('payee_name', 'like', "%{$term}%");
    //                     });
    //             }
    //         }

    //         $schedules = $query->orderBy('created_at', 'desc')->get();

    //         $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    //         $sheet = $spreadsheet->getActiveSheet();

    //         // Headers
    //         $headers = [
    //             'S/N', 'Schedule Number', 'Date', 'MDA', 'Admin Code', 
    //             'Total Amount', 'Amount Posted', 'Voucher Count', 'Status', 'Payee'
    //         ];
            
    //         $column = 'A';
    //         foreach ($headers as $header) {
    //             $sheet->setCellValue($column . '1', $header);
    //             $sheet->getStyle($column . '1')->getFont()->setBold(true);
    //             $column++;
    //         }

    //         // Data
    //         $row = 2;
    //         foreach ($schedules as $index => $schedule) {
    //             $amountPosted = $schedule->vouchers->sum('total_amount');
                
    //             $sheet->setCellValue('A' . $row, $index + 1);
    //             $sheet->setCellValue('B' . $row, $schedule->schedule_number);
    //             $sheet->setCellValue('C' . $row, $schedule->created_at->format('Y-m-d'));
    //             $sheet->setCellValue('D' . $row, $schedule->mda?->name ?? 'N/A');
    //             $sheet->setCellValue('E' . $row, $schedule->budgetCode?->code ?? 'N/A');
    //             $sheet->setCellValue('F' . $row, $schedule->total_amount);
    //             $sheet->setCellValue('G' . $row, $amountPosted);
    //             $sheet->setCellValue('H' . $row, $schedule->vouchers->count());
    //             $sheet->setCellValue('I' . $row, $schedule->status);
    //             $sheet->setCellValue('J' . $row, $schedule->items->first()?->payee_name ?? 'N/A');
    //             $row++;
    //         }

    //         // Auto-size columns
    //         foreach (range('A', 'J') as $col) {
    //             $sheet->getColumnDimension($col)->setAutoSize(true);
    //         }

    //         $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    //         $filename = 'schedules_' . date('Y-m-d_H-i-s') . '.xlsx';
            
    //         $tempFile = tempnam(sys_get_temp_dir(), 'schedule_export');
    //         $writer->save($tempFile);

    //         return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
    //     } catch (\Exception $e) {
    //         Log::error('Schedule Export Excel Error: ' . $e->getMessage());
    //         return back()->with('error', 'Failed to export schedules.');
    //     }
    // }

    // /**
    //  * Export schedules to PDF
    //  */
    // public function exportPdf(Request $request)
    // {
    //     try {
    //         $search = $request->input('search', '');
    //         $status = $request->input('status', '');
    //         $mdaId = $request->input('mda_id', '');
    //         $dateFrom = $request->input('date_from', '');
    //         $dateTo = $request->input('date_to', '');
            
    //         $query = Schedule::with(['mda', 'budgetCode', 'vouchers', 'items']);
    //         $query = $this->applyMdaFilter($query);

    //         if ($status) {
    //             $query->where('status', $status);
    //         }
    //         if ($mdaId) {
    //             $query->where('mda_id', $mdaId);
    //         }
    //         if ($dateFrom) {
    //             $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
    //         }
    //         if ($dateTo) {
    //             $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
    //         }
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             foreach ($words as $term) {
    //                 $query->orWhere('schedule_number', 'like', "%{$term}%")
    //                     ->orWhereHas('mda', function ($mdaQuery) use ($term) {
    //                         $mdaQuery->where('name', 'like', "%{$term}%");
    //                     })
    //                     ->orWhereHas('items', function ($itemQuery) use ($term) {
    //                         $itemQuery->where('payee_name', 'like', "%{$term}%");
    //                     });
    //             }
    //         }

    //         $schedules = $query->orderBy('created_at', 'desc')->get();

    //         $data = [
    //             'schedules' => $schedules,
    //             'export_date' => now()->format('Y-m-d H:i:s'),
    //             'total_amount' => $schedules->sum('total_amount'),
    //             'total_count' => $schedules->count(),
    //         ];

    //         $pdf = Pdf::loadView('exports.schedules_pdf', $data);
    //         $pdf->setPaper('A4', 'landscape');
            
    //         return $pdf->download('schedules_' . date('Y-m-d_H-i-s') . '.pdf');
    //     } catch (\Exception $e) {
    //         Log::error('Schedule Export PDF Error: ' . $e->getMessage());
    //         return back()->with('error', 'Failed to export schedules to PDF.');
    //     }
    // }

    private function getUserAssignedMdas(): array
    {
        $user = Auth::user();
        
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
            return [];
        }
        
        $mdas = $user->mdas()
            ->select('mdas.id', 'mdas.name', 'mdas.code')
            ->get()
            ->map(function($mda) {
                return [
                    'id' => $mda->id,
                    'name' => $mda->name,
                    'code' => $mda->code ?? '',
                ];
            })
            ->toArray();
        
        return $mdas;
    }

    private function getUserAssignedMdaIds(): array
    {
        $mdas = $this->getUserAssignedMdas();
        return array_column($mdas, 'id');
    }

    private function hasAssignedMdas(): bool
    {
        $user = Auth::user();
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
            return true;
        }
        return $user->mdas()->exists();
    }

    private function isAdmin(): bool
    {
        $user = Auth::user();
        return $user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules');
    }

    private function applyMdaFilter($query)
    {
        $user = Auth::user();
        if ($user->hasRole('admin') || $user->hasPermissionTo('view_all_schedules')) {
            return $query;
        }
        
        $mdaIds = $this->getUserAssignedMdaIds();
        if (empty($mdaIds)) {
            return $query->whereRaw('1 = 0');
        }
        
        return $query->whereIn('mda_id', $mdaIds);
    }

    private function canCreateSchedule(): bool
    {
        $user = Auth::user();
        if (!$user->can('schedule.create')) return false;
        if (empty($user->signature)) return false;
        if ($user->can_be_signatory !== true) return false;
        return true;
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $user = Auth::user();
            
    //         $canCreateSchedule = $this->canCreateSchedule();
    //         $hasSignature = !empty($user->signature);
    //         $canBeSignatory = $user->can_be_signatory === true;
            
    //         $assignedMdas = $this->getUserAssignedMdas();
    //         $isAdmin = $this->isAdmin();
    //         $hasMdas = $this->hasAssignedMdas();

    //         $statsQuery = Schedule::query();
    //         $statsQuery = $this->applyMdaFilter($statsQuery);
            
    //         $schedulesForStats = (clone $statsQuery)->with(['vouchers', 'items', 'items.voucher'])->get();
            
    //         $stats = [
    //             'total_schedules' => $schedulesForStats->count(),
    //             'draft_count' => $schedulesForStats->filter(function($schedule) {
    //                 return in_array(strtolower($schedule->status), ['draft', 'saved']);
    //             })->count(),
    //             'submitted_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'submitted';
    //             })->count(),
    //             'processed_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'processed';
    //             })->count(),
    //             'approved_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'approved';
    //             })->count(),
    //             'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'voucher raised';
    //             })->count(),
    //             'rejected_count' => $schedulesForStats->filter(function($schedule) {
    //                 return strtolower($schedule->status) === 'rejected';
    //             })->count(),
    //             'total_amount' => (float) $schedulesForStats->sum('total_amount'),
    //             'total_amount_posted' => (float) $schedulesForStats->sum(function($schedule) {
    //                 return $schedule->vouchers->sum('total_amount');
    //             }),
    //         ];

    //         $query = Schedule::with(['mda', 'budgetCode', 'items', 'items.voucher', 'vouchers'])
    //             ->withCount('items');
            
    //         $query = $this->applyMdaFilter($query);

    //         $search = $request->input('search', '');
    //         if ($search) {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             foreach ($words as $term) {
    //                 $query->where(function ($q) use ($term) {
    //                     $q->where('schedule_number', 'like', "%{$term}%")
    //                       ->orWhereHas('mda', function ($mdaQuery) use ($term) {
    //                           $mdaQuery->where('name', 'like', "%{$term}%");
    //                       })
    //                       ->orWhereHas('items', function ($itemQuery) use ($term) {
    //                           $itemQuery->where('payee_name', 'like', "%{$term}%");
    //                       })
    //                       ->orWhere('total_amount', 'like', "%{$term}%")
    //                       ->orWhere('status', 'like', "%{$term}%");
    //                 });
    //             }
    //         }

    //         if ($request->has('status') && $request->status) {
    //             $query->where('status', $request->status);
    //         }

    //         if ($request->has('date_from') && $request->date_from) {
    //             $query->whereDate('schedule_date', '>=', $request->date_from);
    //         }
    //         if ($request->has('date_to') && $request->date_to) {
    //             $query->whereDate('schedule_date', '<=', $request->date_to);
    //         }

    //         if ($request->has('mda_id') && $request->mda_id) {
    //             $query->where('mda_id', $request->mda_id);
    //         }

    //         $perPage = $request->input('per_page', 15);
    //         $schedules = $query
    //             ->orderBy('created_at', 'desc')
    //             ->paginate($perPage)
    //             ->through(function ($schedule) {
    //                 $amountPosted = $schedule->vouchers->sum('total_amount');
                    
    //                 $totalItems = $schedule->items->count();
                    
    //                 // ✅ Check if schedule has a voucher directly (one-to-one)
    //                 $hasDirectVoucher = $schedule->vouchers->count() > 0;
    //                 $directVoucher = $schedule->vouchers->first();
                    
    //                 // ✅ Check individual items for vouchers
    //                 $processedItems = $schedule->items->filter(function($item) {
    //                     return !is_null($item->voucher_id);
    //                 })->count();
                    
    //                 $unprocessedItems = $totalItems - $processedItems;
                    
    //                 // ✅ For one-to-one: if schedule has voucher, all items are processed
    //                 // For multiple: check each item individually
    //                 if ($totalItems === 1) {
    //                     $allItemsProcessed = $hasDirectVoucher;
    //                 } else {
    //                     $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
    //                 }
                    
    //                 return [
    //                     'id' => $schedule->id,
    //                     'schedule_number' => $schedule->schedule_number,
    //                     'schedule_date' => $schedule->created_at->toDateString(),
    //                     'total_amount' => $schedule->total_amount,
    //                     'amount_posted' => $amountPosted,
    //                     'voucher_count' => $schedule->vouchers->count(),
    //                     'voucher_id' => $directVoucher?->id,
    //                     'status' => $schedule->status,
    //                     'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
    //                     'mda' => $schedule->mda ? [
    //                         'id' => $schedule->mda->id,
    //                         'name' => $schedule->mda->name,
    //                         'code' => $schedule->mda->code,
    //                     ] : null,
    //                     'items_count' => $totalItems,
    //                     'processed_items' => $processedItems,
    //                     'unprocessed_items' => $unprocessedItems,
    //                     'all_items_processed' => $allItemsProcessed,
    //                     'has_direct_voucher' => $hasDirectVoucher,
    //                     'payee_name' => $schedule->items_count > 0 ? (
    //                         $schedule->items->first()?->payee_name .
    //                         ($schedule->items_count > 1 ? ' & Others' : '')
    //                     ) : '',
    //                 ];
    //             });

    //         $mdasQuery = Mda::select('id', 'name')->orderBy('name');
    //         if (!$isAdmin) {
    //             $mdaIds = $this->getUserAssignedMdaIds();
    //             if (!empty($mdaIds)) {
    //                 $mdasQuery->whereIn('id', $mdaIds);
    //             }
    //         }
    //         $mdas = $mdasQuery->get();

    //         $statusOptions = [
    //             ['label' => 'All Status', 'value' => ''],
    //             ['label' => 'Draft', 'value' => 'draft'],
    //             ['label' => 'Saved', 'value' => 'saved'],
    //             ['label' => 'Submitted', 'value' => 'submitted'],
    //             ['label' => 'Processed', 'value' => 'processed'],
    //             ['label' => 'Approved', 'value' => 'approved'],
    //             ['label' => 'Voucher Raised', 'value' => 'voucher raised'],
    //             ['label' => 'Rejected', 'value' => 'rejected'],
    //         ];

    //         return Inertia::render('admin/schedules/index', [
    //             'schedules' => $schedules,
    //             'stats' => $stats,
    //             'mdas' => $mdas,
    //             'statusOptions' => $statusOptions,
    //             'permissions' => [
    //                 'can_create_schedule' => $canCreateSchedule,
    //                 'has_signature' => $hasSignature,
    //                 'can_be_signatory' => $canBeSignatory,
    //             ],
    //             'userMdas' => $assignedMdas,
    //             'isAdmin' => $isAdmin,
    //             'hasMdas' => $hasMdas,
    //             'filters' => [
    //                 'search' => $request->input('search', ''),
    //                 'status' => $request->input('status', ''),
    //                 'mda_id' => $request->input('mda_id', ''),
    //                 'date_from' => $request->input('date_from', ''),
    //                 'date_to' => $request->input('date_to', ''),
    //             ],
    //         ]);
    //     } catch (\Exception $e) {
    //         Log::error('Schedule Index Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
            
    //         return Inertia::render('admin/schedules/index', [
    //             'schedules' => [
    //                 'data' => [],
    //                 'total' => 0,
    //                 'current_page' => 1,
    //                 'per_page' => 15,
    //                 'links' => [],
    //             ],
    //             'stats' => [
    //                 'total_schedules' => 0,
    //                 'draft_count' => 0,
    //                 'submitted_count' => 0,
    //                 'processed_count' => 0,
    //                 'approved_count' => 0,
    //                 'voucher_raised_count' => 0,
    //                 'rejected_count' => 0,
    //                 'total_amount' => 0,
    //                 'total_amount_posted' => 0,
    //             ],
    //             'mdas' => [],
    //             'statusOptions' => [],
    //             'permissions' => [
    //                 'can_create_schedule' => false,
    //                 'has_signature' => false,
    //                 'can_be_signatory' => false,
    //             ],
    //             'userMdas' => [],
    //             'isAdmin' => false,
    //             'hasMdas' => false,
    //             'filters' => [
    //                 'search' => '',
    //                 'status' => '',
    //                 'mda_id' => '',
    //                 'date_from' => '',
    //                 'date_to' => '',
    //             ],
    //         ]);
    //     }
    // }

    /**
 * Display a listing of the schedules with statistics.
 */
// public function index(Request $request)
// {
//     try {
//         $user = Auth::user();
        
//         // ✅ Check permissions
//         $canCreateSchedule = $this->canCreateSchedule();
//         $hasSignature = !empty($user->signature);
//         $canBeSignatory = $user->can_be_signatory === true;
        
//         // ✅ Get user's assigned MDAs
//         $assignedMdas = $this->getUserAssignedMdas();
//         $isAdmin = $this->isAdmin();
//         $hasMdas = $this->hasAssignedMdas();

//         // ✅ Get statistics
//         $statsQuery = Schedule::query();
//         $statsQuery = $this->applyMdaFilter($statsQuery);
        
//         // Get all schedules with their vouchers and items for stats
//         $schedulesForStats = (clone $statsQuery)->with(['vouchers', 'items'])->get();
        
//         $stats = [
//             'total_schedules' => $schedulesForStats->count(),
//             'draft_count' => $schedulesForStats->filter(function($schedule) {
//                 return in_array(strtolower($schedule->status), ['draft', 'saved']);
//             })->count(),
//             'submitted_count' => $schedulesForStats->filter(function($schedule) {
//                 return strtolower($schedule->status) === 'submitted';
//             })->count(),
//             'processed_count' => $schedulesForStats->filter(function($schedule) {
//                 return strtolower($schedule->status) === 'processed';
//             })->count(),
//             'approved_count' => $schedulesForStats->filter(function($schedule) {
//                 return strtolower($schedule->status) === 'approved';
//             })->count(),
//             'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
//                 return strtolower($schedule->status) === 'voucher raised';
//             })->count(),
//             'rejected_count' => $schedulesForStats->filter(function($schedule) {
//                 return strtolower($schedule->status) === 'rejected';
//             })->count(),
//             'total_amount' => (float) $schedulesForStats->sum('total_amount'),
//             'total_amount_posted' => (float) $schedulesForStats->sum(function($schedule) {
//                 return $schedule->vouchers->sum('total_amount');
//             }),
//         ];

//         // ✅ Build query with filters
//         $query = Schedule::with(['mda', 'budgetCode', 'items', 'vouchers'])
//             ->withCount('items');
        
//         // Apply MDA filter
//         $query = $this->applyMdaFilter($query);

//         // Apply search filter
//         $search = $request->input('search', '');
//         if ($search) {
//             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
//             foreach ($words as $term) {
//                 $query->where(function ($q) use ($term) {
//                     $q->where('schedule_number', 'like', "%{$term}%")
//                       ->orWhereHas('mda', function ($mdaQuery) use ($term) {
//                           $mdaQuery->where('name', 'like', "%{$term}%");
//                       })
//                       ->orWhereHas('items', function ($itemQuery) use ($term) {
//                           $itemQuery->where('payee_name', 'like', "%{$term}%");
//                       })
//                       ->orWhere('total_amount', 'like', "%{$term}%")
//                       ->orWhere('status', 'like', "%{$term}%");
//                 });
//             }
//         }

//         // Apply status filter
//         if ($request->has('status') && $request->status) {
//             $query->where('status', $request->status);
//         }

//         // Apply date range filter
//         if ($request->has('date_from') && $request->date_from) {
//             $query->whereDate('schedule_date', '>=', $request->date_from);
//         }
//         if ($request->has('date_to') && $request->date_to) {
//             $query->whereDate('schedule_date', '<=', $request->date_to);
//         }

//         // Apply MDA filter from dropdown
//         if ($request->has('mda_id') && $request->mda_id) {
//             $query->where('mda_id', $request->mda_id);
//         }

//         $perPage = $request->input('per_page', 15);
//         $schedules = $query
//             ->orderBy('created_at', 'desc')
//             ->paginate($perPage)
//             ->through(function ($schedule) {
//                 // Calculate amount posted from vouchers
//                 $amountPosted = $schedule->vouchers->sum('total_amount');
                
//                 // ✅ Count processed and unprocessed items
//                 $totalItems = $schedule->items->count();
                
//                 // ✅ Check if schedule has a voucher directly (one-to-one)
//                 $hasDirectVoucher = $schedule->vouchers->count() > 0;
//                 $directVoucher = $schedule->vouchers->first();
                
//                 // ✅ For multiple items, check each item's voucher through schedule_item_id
//                 $processedItems = 0;
//                 if ($totalItems > 0) {
//                     $processedItems = $schedule->items->filter(function($item) {
//                         // Check if this item has a voucher using schedule_item_id in vouchers table
//                         return Voucher::where('schedule_item_id', $item->id)->exists();
//                     })->count();
//                 }
                
//                 $unprocessedItems = $totalItems - $processedItems;
                
//                 // ✅ For one-to-one: if schedule has voucher, all items are processed
//                 // For multiple: check each item individually
//                 if ($totalItems === 1) {
//                     $allItemsProcessed = $hasDirectVoucher;
//                 } else {
//                     $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
//                 }
                
//                 return [
//                     'id' => $schedule->id,
//                     'schedule_number' => $schedule->schedule_number,
//                     'schedule_date' => $schedule->created_at->toDateString(),
//                     'total_amount' => $schedule->total_amount,
//                     'amount_posted' => $amountPosted,
//                     'voucher_count' => $schedule->vouchers->count(),
//                     'voucher_id' => $directVoucher?->id,
//                     'status' => $schedule->status,
//                     'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
//                     'mda' => $schedule->mda ? [
//                         'id' => $schedule->mda->id,
//                         'name' => $schedule->mda->name,
//                         'code' => $schedule->mda->code,
//                     ] : null,
//                     'items_count' => $totalItems,
//                     'processed_items' => $processedItems,
//                     'unprocessed_items' => $unprocessedItems,
//                     'all_items_processed' => $allItemsProcessed,
//                     'has_direct_voucher' => $hasDirectVoucher,
//                     'payee_name' => $schedule->items_count > 0 ? (
//                         $schedule->items->first()?->payee_name .
//                         ($schedule->items_count > 1 ? ' & Others' : '')
//                     ) : '',
//                 ];
//             });

//         // Get MDAs for filter dropdown
//         $mdasQuery = Mda::select('id', 'name')->orderBy('name');
//         if (!$isAdmin) {
//             $mdaIds = $this->getUserAssignedMdaIds();
//             if (!empty($mdaIds)) {
//                 $mdasQuery->whereIn('id', $mdaIds);
//             }
//         }
//         $mdas = $mdasQuery->get();

//         // Status options for filter
//         $statusOptions = [
//             ['label' => 'All Status', 'value' => ''],
//             ['label' => 'Draft', 'value' => 'draft'],
//             ['label' => 'Saved', 'value' => 'saved'],
//             ['label' => 'Submitted', 'value' => 'submitted'],
//             ['label' => 'Processed', 'value' => 'processed'],
//             ['label' => 'Approved', 'value' => 'approved'],
//             ['label' => 'Voucher Raised', 'value' => 'voucher raised'],
//             ['label' => 'Rejected', 'value' => 'rejected'],
//         ];

//         return Inertia::render('admin/schedules/index', [
//             'schedules' => $schedules,
//             'stats' => $stats,
//             'mdas' => $mdas,
//             'statusOptions' => $statusOptions,
//             'permissions' => [
//                 'can_create_schedule' => $canCreateSchedule,
//                 'has_signature' => $hasSignature,
//                 'can_be_signatory' => $canBeSignatory,
//             ],
//             'userMdas' => $assignedMdas,
//             'isAdmin' => $isAdmin,
//             'hasMdas' => $hasMdas,
//             'filters' => [
//                 'search' => $request->input('search', ''),
//                 'status' => $request->input('status', ''),
//                 'mda_id' => $request->input('mda_id', ''),
//                 'date_from' => $request->input('date_from', ''),
//                 'date_to' => $request->input('date_to', ''),
//             ],
//         ]);
//     } catch (\Exception $e) {
//         Log::error('Schedule Index Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        
//         return Inertia::render('admin/schedules/index', [
//             'schedules' => [
//                 'data' => [],
//                 'total' => 0,
//                 'current_page' => 1,
//                 'per_page' => 15,
//                 'links' => [],
//             ],
//             'stats' => [
//                 'total_schedules' => 0,
//                 'draft_count' => 0,
//                 'submitted_count' => 0,
//                 'processed_count' => 0,
//                 'approved_count' => 0,
//                 'voucher_raised_count' => 0,
//                 'rejected_count' => 0,
//                 'total_amount' => 0,
//                 'total_amount_posted' => 0,
//             ],
//             'mdas' => [],
//             'statusOptions' => [],
//             'permissions' => [
//                 'can_create_schedule' => false,
//                 'has_signature' => false,
//                 'can_be_signatory' => false,
//             ],
//             'userMdas' => [],
//             'isAdmin' => false,
//             'hasMdas' => false,
//             'filters' => [
//                 'search' => '',
//                 'status' => '',
//                 'mda_id' => '',
//                 'date_from' => '',
//                 'date_to' => '',
//             ],
//         ]);
//     }
// }

/**
 * Display a listing of the schedules with statistics.
 */
public function index(Request $request)
{
    try {
        $user = Auth::user();
        
        // ✅ Check permissions
        $canCreateSchedule = $this->canCreateSchedule();
        $hasSignature = !empty($user->signature);
        $canBeSignatory = $user->can_be_signatory === true;
        
        // ✅ Get user's assigned MDAs
        $assignedMdas = $this->getUserAssignedMdas();
        $isAdmin = $this->isAdmin();
        $hasMdas = $this->hasAssignedMdas();

        // ✅ Get statistics
        $statsQuery = Schedule::query();
        $statsQuery = $this->applyMdaFilter($statsQuery);
        
        // Get all schedules with their vouchers and items for stats
        $schedulesForStats = (clone $statsQuery)->with(['vouchers', 'items'])->get();
        
        $stats = [
            'total_schedules' => $schedulesForStats->count(),
            'draft_count' => $schedulesForStats->filter(function($schedule) {
                return in_array(strtolower($schedule->status), ['draft', 'saved']);
            })->count(),
            'submitted_count' => $schedulesForStats->filter(function($schedule) {
                return strtolower($schedule->status) === 'submitted';
            })->count(),
            'processed_count' => $schedulesForStats->filter(function($schedule) {
                return strtolower($schedule->status) === 'processed';
            })->count(),
            'approved_count' => $schedulesForStats->filter(function($schedule) {
                return strtolower($schedule->status) === 'approved';
            })->count(),
            'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
                return strtolower($schedule->status) === 'voucher raised';
            })->count(),
            'rejected_count' => $schedulesForStats->filter(function($schedule) {
                return strtolower($schedule->status) === 'rejected';
            })->count(),
            'total_amount' => (float) $schedulesForStats->sum('total_amount'),
            'total_amount_posted' => (float) $schedulesForStats->sum(function($schedule) {
                return $schedule->vouchers->sum('total_amount');
            }),
        ];

        // ✅ Build query with filters
        $query = Schedule::with(['mda', 'budgetCode', 'items', 'vouchers'])
            ->withCount('items');
        
        // Apply MDA filter
        $query = $this->applyMdaFilter($query);

        // Apply search filter
        $search = $request->input('search', '');
        if ($search) {
            $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($words as $term) {
                $query->where(function ($q) use ($term) {
                    $q->where('schedule_number', 'like', "%{$term}%")
                      ->orWhereHas('mda', function ($mdaQuery) use ($term) {
                          $mdaQuery->where('name', 'like', "%{$term}%");
                      })
                      ->orWhereHas('items', function ($itemQuery) use ($term) {
                          $itemQuery->where('payee_name', 'like', "%{$term}%");
                      })
                      ->orWhere('total_amount', 'like', "%{$term}%")
                      ->orWhere('status', 'like', "%{$term}%");
                });
            }
        }

        // Apply status filter
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Apply date range filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('schedule_date', '>=', $request->date_from);
        }
        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('schedule_date', '<=', $request->date_to);
        }

        // Apply MDA filter from dropdown
        if ($request->has('mda_id') && $request->mda_id) {
            $query->where('mda_id', $request->mda_id);
        }

        $perPage = $request->input('per_page', 15);
        $schedules = $query
            ->orderBy('created_at', 'desc')
            ->paginate($perPage)
            ->through(function ($schedule) {
                // Calculate amount posted from vouchers
                $amountPosted = $schedule->vouchers->sum('total_amount');
                
                // ✅ Count processed and unprocessed items
                $totalItems = $schedule->items->count();
                
                // ✅ Check if schedule has a voucher directly (one-to-one)
                $hasDirectVoucher = $schedule->vouchers->count() > 0;
                $directVoucher = $schedule->vouchers->first();
                
                // ✅ For multiple items, check each item's voucher through schedule_item_id
                $processedItems = 0;
                if ($totalItems > 0) {
                    $processedItems = $schedule->items->filter(function($item) {
                        // Check if this item has a voucher using schedule_item_id in vouchers table
                        return \App\Models\Voucher::where('schedule_item_id', $item->id)->exists();
                    })->count();
                }
                
                $unprocessedItems = $totalItems - $processedItems;
                
                // ✅ For one-to-one: if schedule has voucher, all items are processed
                // For multiple: check each item individually
                if ($totalItems === 1) {
                    $allItemsProcessed = $hasDirectVoucher;
                } else {
                    $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
                }
                
                // ✅ Determine the correct status for display
                $displayStatus = $schedule->status;
                if (strtolower($schedule->status) === 'processed' && $totalItems > 0 && $processedItems === 0 && !$hasDirectVoucher) {
                    // If status is 'Processed' but no voucher exists, show as 'Pending Voucher'
                    $displayStatus = 'Pending Voucher';
                }
                
                return [
                    'id' => $schedule->id,
                    'schedule_number' => $schedule->schedule_number,
                    'schedule_date' => $schedule->created_at->toDateString(),
                    'total_amount' => $schedule->total_amount,
                    'amount_posted' => $amountPosted,
                    'voucher_count' => $schedule->vouchers->count(),
                    'voucher_id' => $directVoucher?->id,
                    'status' => $displayStatus,
                    'original_status' => $schedule->status,
                    'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
                    'mda' => $schedule->mda ? [
                        'id' => $schedule->mda->id,
                        'name' => $schedule->mda->name,
                        'code' => $schedule->mda->code,
                    ] : null,
                    'items_count' => $totalItems,
                    'processed_items' => $processedItems,
                    'unprocessed_items' => $unprocessedItems,
                    'all_items_processed' => $allItemsProcessed,
                    'has_direct_voucher' => $hasDirectVoucher,
                    'payee_name' => $schedule->items_count > 0 ? (
                        $schedule->items->first()?->payee_name .
                        ($schedule->items_count > 1 ? ' & Others' : '')
                    ) : '',
                ];
            });

        // Get MDAs for filter dropdown
        $mdasQuery = Mda::select('id', 'name')->orderBy('name');
        if (!$isAdmin) {
            $mdaIds = $this->getUserAssignedMdaIds();
            if (!empty($mdaIds)) {
                $mdasQuery->whereIn('id', $mdaIds);
            }
        }
        $mdas = $mdasQuery->get();

        // Status options for filter
        $statusOptions = [
            ['label' => 'All Status', 'value' => ''],
            ['label' => 'Draft', 'value' => 'draft'],
            ['label' => 'Saved', 'value' => 'saved'],
            ['label' => 'Submitted', 'value' => 'submitted'],
            ['label' => 'Processed', 'value' => 'processed'],
            ['label' => 'Approved', 'value' => 'approved'],
            ['label' => 'Voucher Raised', 'value' => 'voucher raised'],
            ['label' => 'Rejected', 'value' => 'rejected'],
            ['label' => 'Pending Voucher', 'value' => 'pending_voucher'],
        ];

        return Inertia::render('admin/schedules/index', [
            'schedules' => $schedules,
            'stats' => $stats,
            'mdas' => $mdas,
            'statusOptions' => $statusOptions,
            'permissions' => [
                'can_create_schedule' => $canCreateSchedule,
                'has_signature' => $hasSignature,
                'can_be_signatory' => $canBeSignatory,
            ],
            'userMdas' => $assignedMdas,
            'isAdmin' => $isAdmin,
            'hasMdas' => $hasMdas,
            'filters' => [
                'search' => $request->input('search', ''),
                'status' => $request->input('status', ''),
                'mda_id' => $request->input('mda_id', ''),
                'date_from' => $request->input('date_from', ''),
                'date_to' => $request->input('date_to', ''),
            ],
        ]);
    } catch (\Exception $e) {
        Log::error('Schedule Index Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        
        return Inertia::render('admin/schedules/index', [
            'schedules' => [
                'data' => [],
                'total' => 0,
                'current_page' => 1,
                'per_page' => 15,
                'links' => [],
            ],
            'stats' => [
                'total_schedules' => 0,
                'draft_count' => 0,
                'submitted_count' => 0,
                'processed_count' => 0,
                'approved_count' => 0,
                'voucher_raised_count' => 0,
                'rejected_count' => 0,
                'total_amount' => 0,
                'total_amount_posted' => 0,
            ],
            'mdas' => [],
            'statusOptions' => [],
            'permissions' => [
                'can_create_schedule' => false,
                'has_signature' => false,
                'can_be_signatory' => false,
            ],
            'userMdas' => [],
            'isAdmin' => false,
            'hasMdas' => false,
            'filters' => [
                'search' => '',
                'status' => '',
                'mda_id' => '',
                'date_from' => '',
                'date_to' => '',
            ],
        ]);
    }
}

    public function search(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 20);
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $mdaId = $request->input('mda_id', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            
            $query = Schedule::with(['mda', 'budgetCode', 'items', 'items.voucher', 'vouchers'])
                ->withCount('items');
            $query = $this->applyMdaFilter($query);

            if ($status) {
                $query->where('status', $status);
            }

            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }

            if ($dateFrom) {
                $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
            }
            if ($dateTo) {
                $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
            }

            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                $query->where(function ($mainQuery) use ($words) {
                    foreach ($words as $term) {
                        $mainQuery->orWhere('schedule_number', 'like', "%{$term}%")
                            ->orWhereHas('mda', function ($mdaQuery) use ($term) {
                                $mdaQuery->where('name', 'like', "%{$term}%");
                            })
                            ->orWhereHas('items', function ($itemQuery) use ($term) {
                                $itemQuery->where('payee_name', 'like', "%{$term}%");
                            })
                            ->orWhere('total_amount', 'like', "%{$term}%")
                            ->orWhere('status', 'like', "%{$term}%");
                    }
                });
            }

            $schedules = $query->latest()
                ->paginate($perPage)
                ->through(function ($schedule) {
                    $amountPosted = $schedule->vouchers->sum('total_amount');
                    
                    $totalItems = $schedule->items->count();
                    $hasDirectVoucher = $schedule->vouchers->count() > 0;
                    $directVoucher = $schedule->vouchers->first();
                    
                    $processedItems = $schedule->items->filter(function($item) {
                        return !is_null($item->voucher_id);
                    })->count();
                    
                    $unprocessedItems = $totalItems - $processedItems;
                    
                    if ($totalItems === 1) {
                        $allItemsProcessed = $hasDirectVoucher;
                    } else {
                        $allItemsProcessed = $totalItems > 0 && $processedItems === $totalItems;
                    }
                    
                    return [
                        'id' => $schedule->id,
                        'schedule_number' => $schedule->schedule_number,
                        'schedule_date' => $schedule->created_at->toDateString(),
                        'total_amount' => $schedule->total_amount,
                        'amount_posted' => $amountPosted,
                        'voucher_count' => $schedule->vouchers->count(),
                        'voucher_id' => $directVoucher?->id,
                        'status' => $schedule->status,
                        'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
                        'mda' => $schedule->mda ? [
                            'id' => $schedule->mda->id,
                            'name' => $schedule->mda->name,
                            'code' => $schedule->mda->code,
                        ] : null,
                        'items_count' => $totalItems,
                        'processed_items' => $processedItems,
                        'unprocessed_items' => $unprocessedItems,
                        'all_items_processed' => $allItemsProcessed,
                        'has_direct_voucher' => $hasDirectVoucher,
                        'payee_name' => $schedule->items_count > 0 ? (
                            $schedule->items->first()?->payee_name .
                            ($schedule->items_count > 1 ? ' & Others' : '')
                        ) : '',
                    ];
                });

            $statsQuery = Schedule::with(['items', 'items.voucher']);
            $statsQuery = $this->applyMdaFilter($statsQuery);
            
            if ($status) {
                $statsQuery->where('status', $status);
            }
            if ($mdaId) {
                $statsQuery->where('mda_id', $mdaId);
            }
            if ($dateFrom) {
                $statsQuery->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
            }
            if ($dateTo) {
                $statsQuery->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
            }

            $schedulesForStats = $statsQuery->get();

            $stats = [
                'total_schedules' => $schedulesForStats->count(),
                'total_amount' => (float) $schedulesForStats->sum('total_amount'),
                'draft_count' => $schedulesForStats->filter(function($schedule) {
                    return in_array(strtolower($schedule->status), ['draft', 'saved']);
                })->count(),
                'submitted_count' => $schedulesForStats->filter(function($schedule) {
                    return strtolower($schedule->status) === 'submitted';
                })->count(),
                'processed_count' => $schedulesForStats->filter(function($schedule) {
                    return strtolower($schedule->status) === 'processed';
                })->count(),
                'approved_count' => $schedulesForStats->filter(function($schedule) {
                    return strtolower($schedule->status) === 'approved';
                })->count(),
                'voucher_raised_count' => $schedulesForStats->filter(function($schedule) {
                    return strtolower($schedule->status) === 'voucher raised';
                })->count(),
                'rejected_count' => $schedulesForStats->filter(function($schedule) {
                    return strtolower($schedule->status) === 'rejected';
                })->count(),
            ];

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
                "from" => $schedules->firstItem(),
                "to" => $schedules->lastItem(),
            ];

            return response()->json([
                'status' => 'success',
                'schedules' => $schedules,
                'stats' => $stats,
                'paginator' => $paginator
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule Search Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error', 
                'message' => 'Schedule Search Error: ' . $e->getMessage()
            ]);
        }
    }

    public function exportExcel(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $mdaId = $request->input('mda_id', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            
            $query = Schedule::with(['mda', 'budgetCode', 'vouchers', 'items']);
            $query = $this->applyMdaFilter($query);

            if ($status) {
                $query->where('status', $status);
            }
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            if ($dateFrom) {
                $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
            }
            if ($dateTo) {
                $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
            }
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $term) {
                    $query->orWhere('schedule_number', 'like', "%{$term}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($term) {
                            $mdaQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($term) {
                            $itemQuery->where('payee_name', 'like', "%{$term}%");
                        });
                }
            }

            $schedules = $query->orderBy('created_at', 'desc')->get();

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $headers = [
                'S/N', 'Schedule Number', 'Date', 'MDA', 'Admin Code', 
                'Total Amount', 'Amount Posted', 'Voucher Count', 'Status', 'Payee'
            ];
            
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $sheet->getStyle($column . '1')->getFont()->setBold(true);
                $column++;
            }

            $row = 2;
            foreach ($schedules as $index => $schedule) {
                $amountPosted = $schedule->vouchers->sum('total_amount');
                
                $sheet->setCellValue('A' . $row, $index + 1);
                $sheet->setCellValue('B' . $row, $schedule->schedule_number);
                $sheet->setCellValue('C' . $row, $schedule->created_at->format('Y-m-d'));
                $sheet->setCellValue('D' . $row, $schedule->mda?->name ?? 'N/A');
                $sheet->setCellValue('E' . $row, $schedule->budgetCode?->code ?? 'N/A');
                $sheet->setCellValue('F' . $row, $schedule->total_amount);
                $sheet->setCellValue('G' . $row, $amountPosted);
                $sheet->setCellValue('H' . $row, $schedule->vouchers->count());
                $sheet->setCellValue('I' . $row, $schedule->status);
                $sheet->setCellValue('J' . $row, $schedule->items->first()?->payee_name ?? 'N/A');
                $row++;
            }

            foreach (range('A', 'J') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            $writer = new Xlsx($spreadsheet);
            $filename = 'schedules_' . date('Y-m-d_H-i-s') . '.xlsx';
            
            $tempFile = tempnam(sys_get_temp_dir(), 'schedule_export');
            $writer->save($tempFile);

            return response()->download($tempFile, $filename)->deleteFileAfterSend(true);
        } catch (\Exception $e) {
            Log::error('Schedule Export Excel Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export schedules.');
        }
    }

    public function exportPdf(Request $request)
    {
        try {
            $search = $request->input('search', '');
            $status = $request->input('status', '');
            $mdaId = $request->input('mda_id', '');
            $dateFrom = $request->input('date_from', '');
            $dateTo = $request->input('date_to', '');
            
            $query = Schedule::with(['mda', 'budgetCode', 'vouchers', 'items']);
            $query = $this->applyMdaFilter($query);

            if ($status) {
                $query->where('status', $status);
            }
            if ($mdaId) {
                $query->where('mda_id', $mdaId);
            }
            if ($dateFrom) {
                $query->whereDate('schedule_date', '>=', Carbon::parse($dateFrom)->format('Y-m-d'));
            }
            if ($dateTo) {
                $query->whereDate('schedule_date', '<=', Carbon::parse($dateTo)->format('Y-m-d'));
            }
            if ($search) {
                $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
                foreach ($words as $term) {
                    $query->orWhere('schedule_number', 'like', "%{$term}%")
                        ->orWhereHas('mda', function ($mdaQuery) use ($term) {
                            $mdaQuery->where('name', 'like', "%{$term}%");
                        })
                        ->orWhereHas('items', function ($itemQuery) use ($term) {
                            $itemQuery->where('payee_name', 'like', "%{$term}%");
                        });
                }
            }

            $schedules = $query->orderBy('created_at', 'desc')->get();

            $data = [
                'schedules' => $schedules,
                'export_date' => now()->format('Y-m-d H:i:s'),
                'total_amount' => $schedules->sum('total_amount'),
                'total_count' => $schedules->count(),
            ];

            $pdf = Pdf::loadView('exports.schedules_pdf', $data);
            $pdf->setPaper('A4', 'landscape');
            
            return $pdf->download('schedules_' . date('Y-m-d_H-i-s') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Schedule Export PDF Error: ' . $e->getMessage());
            return back()->with('error', 'Failed to export schedules to PDF.');
        }
    }

    // public function getItems(Schedule $schedule)
    // {
    //     try {
    //         $mdaIds = $this->getUserAssignedMdaIds();
    //         if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
    //             return response()->json(['error' => 'You do not have access to this schedule.'], 403);
    //         }

    //         $items = $schedule->items()
    //             ->with(['economyCode', 'economyCodeItem', 'voucher'])
    //             ->get()
    //             ->map(function ($item) {
    //                 $hasVoucher = !is_null($item->voucher_id);
    //                 $voucher = $item->voucher;
                    
    //                 return [
    //                     'id' => $item->id,
    //                     'serial_number' => $item->serial_number,
    //                     'payee_name' => $item->payee_name,
    //                     'amount' => (float) $item->amount,
    //                     'description' => $item->description ?? '',
    //                     'economy_code' => $item->economyCode?->code,
    //                     'economy_code_name' => $item->economyCode?->name,
    //                     'economy_code_item' => $item->economyCodeItem?->code,
    //                     'economy_code_item_name' => $item->economyCodeItem?->name,
    //                     'voucher_id' => $item->voucher_id,
    //                     'voucher_number' => $voucher?->voucher_number,
    //                     'has_voucher' => $hasVoucher,
    //                     'voucher_status' => $voucher?->status ?? null,
    //                 ];
    //             });

    //         $totalItems = $items->count();
    //         $vouchersCreated = $items->filter(function($item) {
    //             return $item['has_voucher'];
    //         })->count();
    //         $pendingItems = $totalItems - $vouchersCreated;
    //         $allItemsProcessed = $totalItems > 0 && $vouchersCreated === $totalItems;

    //         return response()->json([
    //             'items' => $items,
    //             'total_items' => $totalItems,
    //             'vouchers_created' => $vouchersCreated,
    //             'pending_items' => $pendingItems,
    //             'all_items_processed' => $allItemsProcessed,
    //             'progress_percentage' => $totalItems > 0 ? round(($vouchersCreated / $totalItems) * 100) : 0,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching schedule items: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to load schedule items.',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Show the form for creating a new schedule.
     */
    public function create()
    {
        try {
            $user = Auth::user();
            
            // ✅ Check if user can create schedule
            if (!$this->canCreateSchedule()) {
                if (!$user->can('schedule.create')) {
                    return redirect()->back()->with('error', 'You do not have permission to create schedules.');
                }
                if (!$user->signature) {
                    return redirect()->back()->with('error', 'You must upload your signature before creating a schedule.');
                }
                if ($user->can_be_signatory !== true) {
                    return redirect()->back()->with('error', 'You must be designated as a signatory to create schedules.');
                }
            }

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

            // 4. Predict next schedule number
            $nextScheduleNumber = 'SCH/PENDING/000/' . date('Y');

            // 5. Get Economic Codes and Items
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

            // ✅ Get MDAs filtered by user's assigned MDAs
            $mdaIds = $this->getUserAssignedMdaIds();
            $mdasQuery = Mda::select('id', 'name', 'administrative_code_id')
                ->orderBy('name');
            
            // If user has MDAs assigned, filter by them
            if (!empty($mdaIds)) {
                $mdasQuery->whereIn('id', $mdaIds);
            }
            
            $mdas = $mdasQuery->get();

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
                'mdas' => $mdas,
                'user' => [
                    'has_signature' => !empty($user->signature),
                    'can_be_signatory' => $user->can_be_signatory === true,
                    'assigned_mdas' => $this->getUserAssignedMdas(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Schedule Create Error: ' . $e->getMessage());
            return back()->with('error', 'Unable to load schedule creation form.');
        }
    }

    /**
     * Store a newly created schedule in storage.
     */
    public function store(ScheduleRequest $request)
    {
        $validated = $request->validated();

        try {
            // ✅ Verify user has access to the MDA
            $mdaIds = $this->getUserAssignedMdaIds();
            if (!empty($mdaIds) && !in_array($validated['mda_id'], $mdaIds)) {
                return back()->withInput()->with('error', 'You do not have access to this MDA.');
            }

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
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            abort(403, 'You do not have access to this schedule.');
        }

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
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            abort(403, 'You do not have access to this schedule.');
        }

        // Check if schedule can be edited
        $editableStatuses = ['draft', 'saved', 'returned', 'needs attention'];
        if (!in_array(strtolower($schedule->status), $editableStatuses)) {
            return redirect()->route('schedules.index')->with('error', 'This schedule cannot be edited.');
        }

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

        // ✅ Get MDAs filtered by user's assigned MDAs
        $mdaIds = $this->getUserAssignedMdaIds();
        $mdasQuery = Mda::select('id', 'name', 'administrative_code_id')
            ->orderBy('name');
        
        if (!empty($mdaIds)) {
            $mdasQuery->whereIn('id', $mdaIds);
        }
        
        $mdas = $mdasQuery->get();

        // Transform data for Vue
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
                return [
                    'id' => $item->id,
                    'date' => $item->item_date->format('Y-m-d'),
                    'serial_no' => $item->serial_number,
                    'economy_code_id' => $item->economy_code_id,
                    'economy_code_item_id' => $item->economy_code_item_id,
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
    public function update(ScheduleRequest $request, Schedule $schedule)
    {
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            return back()->with('error', 'You do not have access to this schedule.');
        }

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
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            return back()->with('error', 'You do not have access to this schedule.');
        }

        // Check if schedule can be deleted
        $deletableStatuses = ['draft', 'saved'];
        if (!in_array(strtolower($schedule->status), $deletableStatuses)) {
            return back()->with('error', 'This schedule cannot be deleted.');
        }

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
    public function print(Schedule $schedule)
    {
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            abort(403, 'You do not have access to this schedule.');
        }

        $schedule->load([
            'items',
            'financialYear',
            'mda',
            'budgetCode',
            'items.economyCode',
            'items.economyCodeItem',
        ]);

        $this->activityLogger->log(
            "Printed schedule {$schedule->schedule_number}",
            [
                'schedule_id' => $schedule->id,
                'schedule_number' => $schedule->schedule_number,
                'mda_id' => $schedule->mda_id,
                'budget_code_id' => $schedule->budget_code_id,
                'status' => $schedule->status,
                'total_amount' => $schedule->total_amount,
                'printed_by' => auth()->id()
            ],
            'Schedule',
        );

        return inertia('admin/schedules/print', [
            'schedule' => $schedule,
            'administrativeCodes' => AdministrativeCode::all(),
            'administrativeSectorCodes' => AdministrativeSectorCode::all(),
            'economyCodes' => EconomyCode::all(),
            'economyCodeItems' => EconomyCodeItem::all(),
        ]);
    }

    /**
     * Get next schedule number
     */
    // public function getNextNumber(Request $request)
    // {
    //     $request->validate([
    //         'mda_id' => 'required|integer',
    //         'year_id' => 'required|integer',
    //     ]);

    //     // ✅ Check if user has access to this MDA
    //     $mdaIds = $this->getUserAssignedMdaIds();
    //     if (!empty($mdaIds) && !in_array($request->mda_id, $mdaIds)) {
    //         return response()->json(['error' => 'You do not have access to this MDA.'], 403);
    //     }

    //     $data = $this->scheduleService->generateNextScheduleNumber(
    //         $request->mda_id,
    //         $request->year_id
    //     );

    //     return response()->json($data);
    // }
    /**
     * Get next schedule number (API endpoint)
     */
    public function getNextNumber(Request $request)
    {
        $request->validate([
            'mda_id' => 'required|integer',
            'year_id' => 'required|integer',
        ]);

        // ✅ Check if user has access to this MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($request->mda_id, $mdaIds)) {
            return response()->json(['error' => 'You do not have access to this MDA.'], 403);
        }

        // ✅ Get the total number of line items for this MDA and Year
        $year = FinancialYear::find($request->year_id);
        $yearName = $year ? $year->name : date('Y');
        
        $totalItems = ScheduleItem::whereHas('schedule', function ($query) use ($request, $yearName) {
            $query->where('mda_id', $request->mda_id)
                ->where('schedule_number', 'LIKE', "%/{$yearName}");
        })->count();

        // ✅ Next serial is total items + 1
        $nextSerial = $totalItems + 1;

        // ✅ Get MDA initials
        $mda = Mda::find($request->mda_id);
        $mdaInitials = $mda ? ($mda->initials ?? $this->deriveInitials($mda->name)) : 'GEN';
        $mdaInitials = strtoupper($mdaInitials);

        // ✅ Generate the schedule number
        $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";

        // ✅ Ensure uniqueness
        while (Schedule::where('schedule_number', $scheduleNumber)->exists()) {
            $nextSerial++;
            $scheduleNumber = "SCH/{$mdaInitials}/{$nextSerial}/{$yearName}";
        }

        return response()->json([
            'schedule_number' => $scheduleNumber,
            'serial_no' => (string)$nextSerial,
            'total_items' => $totalItems,
        ]);
    }

    /**
     * Helper to generate initials if column is empty (for controller)
     */
    private function deriveInitials($name) 
    {
        $stopwords = ['of', 'and', 'the', '&', 'for']; 
        $words = preg_split('/[ -]/', $name, -1, PREG_SPLIT_NO_EMPTY);
        $acronym = "";
        foreach ($words as $w) {
            if (!empty($w) && !in_array(strtolower($w), $stopwords)) {
                $acronym .= $w[0];
            }
        }
        return substr($acronym, 0, 3);
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

    /**
     * Get payees with search
     */
    public function getPayees(Request $request)
    {
        $filter = $request->input('filter', '');
        $items = Payee::when($filter, function ($query, $filter) {
            return $query->where('name', 'like', "%{$filter}%");
        })
        ->paginate(15);

        return response()->json($items);
    }

    /**
     * Search schedules (API endpoint) - ✅ WITH MDA FILTERING
     */
    // public function search(Request $request)
    // {
    //     try {
    //         $perPage = $request->input('per_page', 100);
    //         $search = $request->input('search', '');
            
    //         // ✅ Build query with MDA filter FIRST
    //         $query = Schedule::query();
    //         $query = $this->applyMdaFilter($query);

    //         // ✅ If user has no MDAs assigned, the applyMdaFilter already added 1=0
    //         // So we don't need to do anything else for that case

    //         if ($search !== '') {
    //             $words = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY);
    //             $query->where(function ($mainQuery) use ($words) {
    //                 foreach ($words as $term) {
    //                     $mainQuery->orWhere(function ($subQuery) use ($term) {
    //                         $subQuery->where('schedule_number', 'like', "%{$term}%");
    //                     })
    //                     ->orWhere(function ($subQuery) use ($term) {
    //                         // ✅ Search in MDA names - but only if user has access to those MDAs
    //                         // The applyMdaFilter already restricts to assigned MDAs
    //                         $mda = Mda::where('name', 'like', "%{$term}%")
    //                             ->orWhere('new_name', 'like', "%{$term}%")
    //                             ->orWhere('oracle_name', 'like', "%{$term}%")
    //                             ->orderBy('name')
    //                             ->limit(200)
    //                             ->pluck('id')
    //                             ->toArray();
    //                         $subQuery->whereIn('mda_id', $mda);
    //                     })
    //                     ->orWhere(function ($subQuery) use ($term) {
    //                         $adminCode = administrativeCodeItem::where('name', 'like', "%{$term}%")
    //                             ->orWhere('code', 'like', "%{$term}%")
    //                             ->orderBy('name')
    //                             ->limit(200)
    //                             ->pluck('id')
    //                             ->toArray();
    //                         $subQuery->whereIn('budget_code_id', $adminCode);
    //                     })
    //                     ->orWhere(function ($subQuery) use ($term) {
    //                         $payeeName = ScheduleItem::where('payee_name', 'like', "%{$term}%")
    //                             ->limit(200)
    //                             ->pluck('schedule_id')
    //                             ->toArray();
    //                         $subQuery->whereIn('id', $payeeName);
    //                     })
    //                     ->orWhere('total_amount', 'like', "%{$term}%")
    //                     ->orWhere('status', 'like', "%{$term}%")
    //                     ->orWhere('schedule_date', 'like', "%{$term}%");
    //                 }
    //             });
    //         }

    //         if ($request->has('date_from') && !empty($request->date_from)) {
    //             $query->whereDate('schedule_date', '>=', Carbon::parse($request->date_from)->format('Y-m-d'));
    //         }

    //         if ($request->has('date_to') && !empty($request->date_to)) {
    //             $query->whereDate('schedule_date', '<=', Carbon::parse($request->date_to)->format('Y-m-d'));
    //         }

    //         $perPage = $request->input('per_page', 20);

    //         $schedules = $query->with('mda', 'budgetCode')
    //             ->withCount('items')
    //             ->latest()
    //             ->paginate($perPage)
    //             ->through(function ($schedule) {
    //                 return [
    //                     'id' => $schedule->id,
    //                     'schedule_number' => $schedule->schedule_number,
    //                     'schedule_date' => $schedule->created_at->toDateString(),
    //                     'total_amount' => $schedule->total_amount,
    //                     'amount_posted' => $schedule->vouchers->sum('total_amount'),
    //                     'voucher_count' => $schedule->vouchers->count(),
    //                     'voucher_id' => $schedule->vouchers->first()?->id,
    //                     'status' => $schedule->status,
    //                     'budget_code' => $schedule->budgetCode?->code ?? 'N/A',
    //                     'mda' => $schedule->mda ? [
    //                         'id' => $schedule->mda->id,
    //                         'name' => $schedule->mda->name,
    //                     ] : null,
    //                     'items_count' => $schedule->items_count,
    //                     'payee_name' => $schedule->items_count > 0 ? (
    //                         $schedule->items->first()?->payee_name .
    //                         ($schedule->items_count > 1 ? ' & Others' : '')
    //                     ) : '',
    //                 ];
    //             });

    //         $paginator = [
    //             "total" => $schedules->total(),
    //             "per_page" => $schedules->perPage(),
    //             "current_page" => $schedules->currentPage(),
    //             "last_page" => $schedules->lastPage(),
    //             "first_page_url" => $schedules->url(1),
    //             "last_page_url" => $schedules->url($schedules->lastPage()),
    //             "next_page_url" => $schedules->nextPageUrl(),
    //             "prev_page_url" => $schedules->previousPageUrl(),
    //             "path" => $schedules->path(),
    //             "from" => $schedules->currentPage(),
    //             "to" => $schedules->perPage(),
    //         ];

    //         $this->activityLogger->log(
    //             "Searched schedules",
    //             [
    //                 'search_term' => $search,
    //                 'results_count' => $schedules->total(),
    //                 'per_page' => $perPage,
    //                 'user_id' => auth()->id()
    //             ],
    //             'schedules'
    //         );

    //         return response()->json([
    //             'status' => 'success',
    //             'schedules' => $schedules,
    //             'paginator' => $paginator
    //         ]);
    //     } catch (\Exception $e) {
    //         \Log::error('Schedule Search Error: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'error', 
    //             'message' => 'Schedule Search Error: ' . $e->getMessage()
    //         ]);
    //     }
    // }

    /**
     * Get items for a specific schedule
     */
    // public function getItems(Schedule $schedule)
    // {
    //     try {
    //         // ✅ Check if user has access to this schedule's MDA
    //         $mdaIds = $this->getUserAssignedMdaIds();
    //         if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
    //             return response()->json(['error' => 'You do not have access to this schedule.'], 403);
    //         }

    //         // ✅ Load items with their relationships
    //         $items = $schedule->items()
    //             ->with(['economyCode', 'economyCodeItem'])
    //             ->get()
    //             ->map(function ($item) {
    //                 // ✅ Check if this item has a voucher (via the voucher_id column)
    //                 $hasVoucher = !is_null($item->voucher_id);
    //                 $voucherId = $item->voucher_id;
                    
    //                 // If you want to load the actual voucher relationship
    //                 // $voucher = $item->voucher;
                    
    //                 return [
    //                     'id' => $item->id,
    //                     'serial_number' => $item->serial_number,
    //                     'payee_name' => $item->payee_name,
    //                     'amount' => (float) $item->amount,
    //                     'description' => $item->description ?? '',
    //                     'economy_code' => $item->economyCode?->code,
    //                     'economy_code_name' => $item->economyCode?->name,
    //                     'economy_code_item' => $item->economyCodeItem?->code,
    //                     'economy_code_item_name' => $item->economyCodeItem?->name,
    //                     'voucher_id' => $voucherId,
    //                     'has_voucher' => $hasVoucher,
    //                 ];
    //             });

    //         // ✅ Calculate statistics
    //         $totalItems = $items->count();
    //         $vouchersCreated = $items->filter(function($item) {
    //             return $item['has_voucher'];
    //         })->count();

    //         return response()->json([
    //             'items' => $items,
    //             'total_items' => $totalItems,
    //             'vouchers_created' => $vouchersCreated,
    //             'pending' => $totalItems - $vouchersCreated,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching schedule items: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to load schedule items.',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Get items for a specific schedule with voucher status
     */
    // public function getItems(Schedule $schedule)
    // {
    //     try {
    //         // ✅ Check if user has access to this schedule's MDA
    //         $mdaIds = $this->getUserAssignedMdaIds();
    //         if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
    //             return response()->json(['error' => 'You do not have access to this schedule.'], 403);
    //         }

    //         // ✅ Load items with their relationships
    //         $items = $schedule->items()
    //             ->with(['economyCode', 'economyCodeItem', 'voucher'])
    //             ->get()
    //             ->map(function ($item) {
    //                 // ✅ Check if this item has a voucher
    //                 $hasVoucher = !is_null($item->voucher_id);
    //                 $voucher = $item->voucher;
                    
    //                 return [
    //                     'id' => $item->id,
    //                     'serial_number' => $item->serial_number,
    //                     'payee_name' => $item->payee_name,
    //                     'amount' => (float) $item->amount,
    //                     'description' => $item->description ?? '',
    //                     'economy_code' => $item->economyCode?->code,
    //                     'economy_code_name' => $item->economyCode?->name,
    //                     'economy_code_item' => $item->economyCodeItem?->code,
    //                     'economy_code_item_name' => $item->economyCodeItem?->name,
    //                     'voucher_id' => $item->voucher_id,
    //                     'voucher_number' => $voucher?->voucher_number,
    //                     'has_voucher' => $hasVoucher,
    //                     'voucher_status' => $voucher?->status ?? null,
    //                 ];
    //             });

    //         // ✅ Calculate statistics
    //         $totalItems = $items->count();
    //         $vouchersCreated = $items->filter(function($item) {
    //             return $item['has_voucher'];
    //         })->count();
    //         $pendingItems = $totalItems - $vouchersCreated;
    //         $allItemsProcessed = $totalItems > 0 && $vouchersCreated === $totalItems;

    //         return response()->json([
    //             'items' => $items,
    //             'total_items' => $totalItems,
    //             'vouchers_created' => $vouchersCreated,
    //             'pending_items' => $pendingItems,
    //             'all_items_processed' => $allItemsProcessed,
    //             'progress_percentage' => $totalItems > 0 ? round(($vouchersCreated / $totalItems) * 100) : 0,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching schedule items: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to load schedule items.',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    /**
     * Get items for a specific schedule with voucher status
     */
    // public function getItems(Schedule $schedule)
    // {
    //     try {
    //         // ✅ Check if user has access to this schedule's MDA
    //         $mdaIds = $this->getUserAssignedMdaIds();
    //         if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
    //             return response()->json(['error' => 'You do not have access to this schedule.'], 403);
    //         }

    //         // ✅ Load items with their relationships including voucher approvals
    //         $items = $schedule->items()
    //             ->with(['economyCode', 'economyCodeItem', 'voucher', 'voucher.approvals', 'voucher.creator'])
    //             ->get()
    //             ->map(function ($item) {
    //                 // ✅ Check if this item has a voucher (using the relationship)
    //                 $hasVoucher = $item->voucher()->exists();
    //                 $voucher = $item->voucher;
                    
    //                 // ✅ Get voucher status and workflow info
    //                 $voucherStatus = null;
    //                 $voucherStatusDisplay = null;
    //                 $voucherStatusSeverity = 'info';
    //                 $voucherWorkflowStage = null;
    //                 $isVoucherSubmitted = false;
    //                 $isVoucherApproved = false;
    //                 $isVoucherRejected = false;
                    
    //                 if ($voucher) {
    //                     $voucherStatus = $voucher->status;
    //                     $voucherStatusDisplay = $this->getVoucherStatusDisplay($voucher->status);
    //                     $voucherStatusSeverity = $this->getVoucherStatusSeverity($voucher->status);
                        
    //                     // ✅ Get current workflow stage from approvals
    //                     $latestApproval = $voucher->approvals()->latest('approval_step')->first();
    //                     if ($latestApproval) {
    //                         $voucherWorkflowStage = $latestApproval->approval_role ?? 'Draft';
    //                     } else {
    //                         $voucherWorkflowStage = 'Draft';
    //                     }
                        
    //                     // ✅ Check if voucher is submitted
    //                     $isVoucherSubmitted = in_array(strtolower($voucher->status), ['submitted', 'pending']);
                        
    //                     // ✅ Check if voucher is approved
    //                     $isVoucherApproved = in_array(strtolower($voucher->status), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
                        
    //                     // ✅ Check if voucher is rejected
    //                     $isVoucherRejected = in_array(strtolower($voucher->status), ['rejected', 'declined', 'sent_back']);
    //                 }
                    
    //                 return [
    //                     'id' => $item->id,
    //                     'serial_number' => $item->serial_number,
    //                     'payee_name' => $item->payee_name,
    //                     'amount' => (float) $item->amount,
    //                     'description' => $item->description ?? '',
    //                     'economy_code' => $item->economyCode?->code,
    //                     'economy_code_name' => $item->economyCode?->name,
    //                     'economy_code_item' => $item->economyCodeItem?->code,
    //                     'economy_code_item_name' => $item->economyCodeItem?->name,
    //                     'voucher_id' => $voucher?->id,
    //                     'voucher_number' => $voucher?->voucher_number,
    //                     'has_voucher' => $hasVoucher,
    //                     'voucher_status' => $voucherStatus,
    //                     'voucher_status_display' => $voucherStatusDisplay,
    //                     'voucher_status_severity' => $voucherStatusSeverity,
    //                     'voucher_workflow_stage' => $voucherWorkflowStage,
    //                     'voucher_created_by' => $voucher?->creator?->name,
    //                     'voucher_created_at' => $voucher?->created_at?->toDateTimeString(),
    //                     'is_voucher_submitted' => $isVoucherSubmitted,
    //                     'is_voucher_approved' => $isVoucherApproved,
    //                     'is_voucher_rejected' => $isVoucherRejected,
    //                     'can_edit_voucher' => $hasVoucher && !$isVoucherSubmitted && !$isVoucherApproved && !$isVoucherRejected,
    //                     'can_view_voucher' => $hasVoucher,
    //                     'can_create_voucher' => !$hasVoucher,
    //                 ];
    //             });

    //         // ✅ Calculate statistics
    //         $totalItems = $items->count();
    //         $vouchersCreated = $items->filter(function($item) {
    //             return $item['has_voucher'];
    //         })->count();
    //         $pendingItems = $totalItems - $vouchersCreated;
    //         $allItemsProcessed = $totalItems > 0 && $vouchersCreated === $totalItems;
            
    //         // ✅ Count vouchers in different stages
    //         $vouchersInDraft = $items->filter(function($item) {
    //             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['draft']);
    //         })->count();
    //         $vouchersSubmitted = $items->filter(function($item) {
    //             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['submitted', 'pending']);
    //         })->count();
    //         $vouchersApproved = $items->filter(function($item) {
    //             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
    //         })->count();
    //         $vouchersRejected = $items->filter(function($item) {
    //             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['rejected', 'declined', 'sent_back']);
    //         })->count();

    //         return response()->json([
    //             'items' => $items,
    //             'total_items' => $totalItems,
    //             'vouchers_created' => $vouchersCreated,
    //             'pending_items' => $pendingItems,
    //             'all_items_processed' => $allItemsProcessed,
    //             'progress_percentage' => $totalItems > 0 ? round(($vouchersCreated / $totalItems) * 100) : 0,
    //             'vouchers_in_draft' => $vouchersInDraft,
    //             'vouchers_submitted' => $vouchersSubmitted,
    //             'vouchers_approved' => $vouchersApproved,
    //             'vouchers_rejected' => $vouchersRejected,
    //         ]);
            
    //     } catch (\Exception $e) {
    //         Log::error('Error fetching schedule items: ' . $e->getMessage());
    //         return response()->json([
    //             'error' => 'Failed to load schedule items.',
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    // /**
    //  * Get voucher status display name
    //  */
    // private function getVoucherStatusDisplay($status): string
    // {
    //     $statuses = [
    //         'draft' => 'Draft',
    //         'submitted' => 'Submitted',
    //         'pending' => 'Pending Approval',
    //         'approved' => 'Approved',
    //         'audit_approved' => 'Audit Approved',
    //         'fa_approved' => 'FA Approved',
    //         'ec_approved' => 'EC Approved',
    //         'ag_approved' => 'AG Approved',
    //         'mas_approved' => 'MAS Approved',
    //         'closed' => 'Closed',
    //         'rejected' => 'Rejected',
    //         'declined' => 'Declined',
    //         'sent_back' => 'Sent Back',
    //         'paid' => 'Paid',
    //         'retired' => 'Retired',
    //     ];
    //     return $statuses[strtolower($status)] ?? ucfirst($status);
    // }

    // /**
    //  * Get voucher status severity for badge
    //  */
    // private function getVoucherStatusSeverity($status): string
    // {
    //     $severities = [
    //         'draft' => 'info',
    //         'submitted' => 'secondary',
    //         'pending' => 'warning',
    //         'approved' => 'success',
    //         'audit_approved' => 'success',
    //         'fa_approved' => 'success',
    //         'ec_approved' => 'success',
    //         'ag_approved' => 'success',
    //         'mas_approved' => 'success',
    //         'closed' => 'success',
    //         'rejected' => 'danger',
    //         'declined' => 'danger',
    //         'sent_back' => 'warning',
    //         'paid' => 'success',
    //         'retired' => 'info',
    //     ];
    //     return $severities[strtolower($status)] ?? 'info';
    // }

    /**
 * Get items for a specific schedule with voucher status
 */
// public function getItems(Schedule $schedule)
// {
//     try {
//         // ✅ Check if user has access to this schedule's MDA
//         $mdaIds = $this->getUserAssignedMdaIds();
//         if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
//             return response()->json(['error' => 'You do not have access to this schedule.'], 403);
//         }

//         // ✅ Load items with their relationships including voucher approvals
//         $items = $schedule->items()
//             ->with(['economyCode', 'economyCodeItem'])
//             ->get()
//             ->map(function ($item) {
//                 // ✅ Check if this item has a voucher (using the relationship through schedule_item_id)
//                 $voucher = Voucher::where('schedule_item_id', $item->id)->first();
//                 $hasVoucher = !is_null($voucher);
                
//                 // ✅ Get voucher status and workflow info
//                 $voucherStatus = null;
//                 $voucherStatusDisplay = null;
//                 $voucherStatusSeverity = 'info';
//                 $voucherWorkflowStage = null;
//                 $isVoucherSubmitted = false;
//                 $isVoucherApproved = false;
//                 $isVoucherRejected = false;
//                 $voucherCreatedBy = null;
//                 $voucherCreatedAt = null;
//                 $voucherNumber = null;
//                 $voucherId = null;
                
//                 if ($voucher) {
//                     $voucherId = $voucher->id;
//                     $voucherNumber = $voucher->voucher_number;
//                     $voucherStatus = $voucher->status;
//                     $voucherStatusDisplay = $this->getVoucherStatusDisplay($voucher->status);
//                     $voucherStatusSeverity = $this->getVoucherStatusSeverity($voucher->status);
//                     $voucherCreatedBy = $voucher->creator?->name;
//                     $voucherCreatedAt = $voucher->created_at?->toDateTimeString();
                    
//                     // ✅ Get current workflow stage from approvals
//                     $latestApproval = $voucher->approvals()->latest('approval_step')->first();
//                     if ($latestApproval) {
//                         $voucherWorkflowStage = $latestApproval->approval_role ?? 'Draft';
//                     } else {
//                         $voucherWorkflowStage = 'Draft';
//                     }
                    
//                     // ✅ Check if voucher is submitted
//                     $isVoucherSubmitted = in_array(strtolower($voucher->status), ['submitted', 'pending']);
                    
//                     // ✅ Check if voucher is approved
//                     $isVoucherApproved = in_array(strtolower($voucher->status), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
                    
//                     // ✅ Check if voucher is rejected
//                     $isVoucherRejected = in_array(strtolower($voucher->status), ['rejected', 'declined', 'sent_back']);
//                 }
                
//                 return [
//                     'id' => $item->id,
//                     'serial_number' => $item->serial_number,
//                     'payee_name' => $item->payee_name,
//                     'amount' => (float) $item->amount,
//                     'description' => $item->description ?? '',
//                     'economy_code' => $item->economyCode?->code,
//                     'economy_code_name' => $item->economyCode?->name,
//                     'economy_code_item' => $item->economyCodeItem?->code,
//                     'economy_code_item_name' => $item->economyCodeItem?->name,
//                     'voucher_id' => $voucherId,
//                     'voucher_number' => $voucherNumber,
//                     'has_voucher' => $hasVoucher,
//                     'voucher_status' => $voucherStatus,
//                     'voucher_status_display' => $voucherStatusDisplay,
//                     'voucher_status_severity' => $voucherStatusSeverity,
//                     'voucher_workflow_stage' => $voucherWorkflowStage,
//                     'voucher_created_by' => $voucherCreatedBy,
//                     'voucher_created_at' => $voucherCreatedAt,
//                     'is_voucher_submitted' => $isVoucherSubmitted,
//                     'is_voucher_approved' => $isVoucherApproved,
//                     'is_voucher_rejected' => $isVoucherRejected,
//                     'can_edit_voucher' => $hasVoucher && !$isVoucherSubmitted && !$isVoucherApproved && !$isVoucherRejected,
//                     'can_view_voucher' => $hasVoucher,
//                     'can_create_voucher' => !$hasVoucher,
//                 ];
//             });

//         // ✅ Calculate statistics correctly
//         $totalItems = $items->count();
//         $vouchersCreated = $items->filter(function($item) {
//             return $item['has_voucher'] === true;
//         })->count();
//         $pendingItems = $totalItems - $vouchersCreated;
//         $allItemsProcessed = $totalItems > 0 && $vouchersCreated === $totalItems;
        
//         // ✅ Count vouchers in different stages
//         $vouchersInDraft = $items->filter(function($item) {
//             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['draft']);
//         })->count();
//         $vouchersSubmitted = $items->filter(function($item) {
//             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['submitted', 'pending']);
//         })->count();
//         $vouchersApproved = $items->filter(function($item) {
//             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
//         })->count();
//         $vouchersRejected = $items->filter(function($item) {
//             return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['rejected', 'declined', 'sent_back']);
//         })->count();

//         return response()->json([
//             'items' => $items,
//             'total_items' => $totalItems,
//             'vouchers_created' => $vouchersCreated,
//             'pending_items' => $pendingItems,
//             'all_items_processed' => $allItemsProcessed,
//             'progress_percentage' => $totalItems > 0 ? round(($vouchersCreated / $totalItems) * 100) : 0,
//             'vouchers_in_draft' => $vouchersInDraft,
//             'vouchers_submitted' => $vouchersSubmitted,
//             'vouchers_approved' => $vouchersApproved,
//             'vouchers_rejected' => $vouchersRejected,
//         ]);
        
//     } catch (\Exception $e) {
//         Log::error('Error fetching schedule items: ' . $e->getMessage());
//         return response()->json([
//             'error' => 'Failed to load schedule items.',
//             'message' => $e->getMessage()
//         ], 500);
//     }
// }

/**
 * Get items for a specific schedule with voucher status
 */
public function getItems(Schedule $schedule)
{
    try {
        // ✅ Check if user has access to this schedule's MDA
        $mdaIds = $this->getUserAssignedMdaIds();
        if (!empty($mdaIds) && !in_array($schedule->mda_id, $mdaIds)) {
            return response()->json(['error' => 'You do not have access to this schedule.'], 403);
        }

        // ✅ Load items with their relationships including voucher approvals
        $items = $schedule->items()
            ->with(['economyCode', 'economyCodeItem'])
            ->get()
            ->map(function ($item) {
                // ✅ Check if this item has a voucher (using the relationship through schedule_item_id)
                $voucher = Voucher::where('schedule_item_id', $item->id)->first();
                $hasVoucher = !is_null($voucher);
                
                // ✅ Get voucher status and workflow info
                $voucherStatus = null;
                $voucherStatusDisplay = null;
                $voucherStatusSeverity = 'info';
                $voucherWorkflowStage = null;
                $isVoucherSubmitted = false;
                $isVoucherApproved = false;
                $isVoucherRejected = false;
                $voucherCreatedBy = null;
                $voucherCreatedAt = null;
                $voucherNumber = null;
                $voucherId = null;
                
                if ($voucher) {
                    $voucherId = $voucher->id;
                    $voucherNumber = $voucher->voucher_number;
                    $voucherStatus = $voucher->status;
                    $voucherStatusDisplay = $this->getVoucherStatusDisplay($voucher->status);
                    $voucherStatusSeverity = $this->getVoucherStatusSeverity($voucher->status);
                    $voucherCreatedBy = $voucher->creator?->name;
                    $voucherCreatedAt = $voucher->created_at?->toDateTimeString();
                    
                    // ✅ Get current workflow stage from approvals
                    $latestApproval = $voucher->approvals()->latest('approval_step')->first();
                    if ($latestApproval) {
                        $voucherWorkflowStage = $latestApproval->approval_role ?? 'Draft';
                    } else {
                        $voucherWorkflowStage = 'Draft';
                    }
                    
                    // ✅ Check if voucher is submitted
                    $isVoucherSubmitted = in_array(strtolower($voucher->status), ['submitted', 'pending']);
                    
                    // ✅ Check if voucher is approved
                    $isVoucherApproved = in_array(strtolower($voucher->status), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
                    
                    // ✅ Check if voucher is rejected
                    $isVoucherRejected = in_array(strtolower($voucher->status), ['rejected', 'declined', 'sent_back']);
                }
                
                return [
                    'id' => $item->id,
                    'serial_number' => $item->serial_number,
                    'payee_name' => $item->payee_name,
                    'amount' => (float) $item->amount,
                    'description' => $item->description ?? '',
                    'economy_code' => $item->economyCode?->code,
                    'economy_code_name' => $item->economyCode?->name,
                    'economy_code_item' => $item->economyCodeItem?->code,
                    'economy_code_item_name' => $item->economyCodeItem?->name,
                    'voucher_id' => $voucherId,
                    'voucher_number' => $voucherNumber,
                    'has_voucher' => $hasVoucher,
                    'voucher_status' => $voucherStatus,
                    'voucher_status_display' => $voucherStatusDisplay,
                    'voucher_status_severity' => $voucherStatusSeverity,
                    'voucher_workflow_stage' => $voucherWorkflowStage,
                    'voucher_created_by' => $voucherCreatedBy,
                    'voucher_created_at' => $voucherCreatedAt,
                    'is_voucher_submitted' => $isVoucherSubmitted,
                    'is_voucher_approved' => $isVoucherApproved,
                    'is_voucher_rejected' => $isVoucherRejected,
                    'can_edit_voucher' => $hasVoucher && !$isVoucherSubmitted && !$isVoucherApproved && !$isVoucherRejected,
                    'can_view_voucher' => $hasVoucher,
                    'can_create_voucher' => !$hasVoucher,
                ];
            });

        // ✅ Calculate statistics correctly
        $totalItems = $items->count();
        $vouchersCreated = $items->filter(function($item) {
            return $item['has_voucher'] === true;
        })->count();
        $pendingItems = $totalItems - $vouchersCreated;
        $allItemsProcessed = $totalItems > 0 && $vouchersCreated === $totalItems;
        
        // ✅ Count vouchers in different stages
        $vouchersInDraft = $items->filter(function($item) {
            return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['draft']);
        })->count();
        $vouchersSubmitted = $items->filter(function($item) {
            return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['submitted', 'pending']);
        })->count();
        $vouchersApproved = $items->filter(function($item) {
            return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['approved', 'audit_approved', 'fa_approved', 'ec_approved', 'ag_approved', 'mas_approved', 'closed']);
        })->count();
        $vouchersRejected = $items->filter(function($item) {
            return $item['has_voucher'] && in_array(strtolower($item['voucher_status']), ['rejected', 'declined', 'sent_back']);
        })->count();

        return response()->json([
            'items' => $items,
            'total_items' => $totalItems,
            'vouchers_created' => $vouchersCreated,
            'pending_items' => $pendingItems,
            'all_items_processed' => $allItemsProcessed,
            'progress_percentage' => $totalItems > 0 ? round(($vouchersCreated / $totalItems) * 100) : 0,
            'vouchers_in_draft' => $vouchersInDraft,
            'vouchers_submitted' => $vouchersSubmitted,
            'vouchers_approved' => $vouchersApproved,
            'vouchers_rejected' => $vouchersRejected,
        ]);
        
    } catch (\Exception $e) {
        Log::error('Error fetching schedule items: ' . $e->getMessage());
        return response()->json([
            'error' => 'Failed to load schedule items.',
            'message' => $e->getMessage()
        ], 500);
    }
}

/**
 * Get voucher status display name
 */
private function getVoucherStatusDisplay($status): string
{
    $statuses = [
        'draft' => 'Draft',
        'submitted' => 'Submitted',
        'pending' => 'Pending Approval',
        'approved' => 'Approved',
        'audit_approved' => 'Audit Approved',
        'fa_approved' => 'FA Approved',
        'ec_approved' => 'EC Approved',
        'ag_approved' => 'AG Approved',
        'mas_approved' => 'MAS Approved',
        'closed' => 'Closed',
        'rejected' => 'Rejected',
        'declined' => 'Declined',
        'sent_back' => 'Sent Back',
        'paid' => 'Paid',
        'retired' => 'Retired',
    ];
    return $statuses[strtolower($status)] ?? ucfirst($status);
}

/**
 * Get voucher status severity for badge
 */
private function getVoucherStatusSeverity($status): string
{
    $severities = [
        'draft' => 'info',
        'submitted' => 'secondary',
        'pending' => 'warning',
        'approved' => 'success',
        'audit_approved' => 'success',
        'fa_approved' => 'success',
        'ec_approved' => 'success',
        'ag_approved' => 'success',
        'mas_approved' => 'success',
        'closed' => 'success',
        'rejected' => 'danger',
        'declined' => 'danger',
        'sent_back' => 'warning',
        'paid' => 'success',
        'retired' => 'info',
    ];
    return $severities[strtolower($status)] ?? 'info';
}
}