<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'log_name' => 'nullable|string',
            'event' => 'nullable|string',
            'causer_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort_by' => 'nullable|in:created_at,log_name,event',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);

        $query = ActivityLog::with(['causer:id,name,email']);
        //     // ->whereRaw("LENGTH(description) - LENGTH(REPLACE(description, '/', '')) > 1")
        //     ->whereRaw("description LIKE '%Updated voucher%' AND description REGEXP '[^/]*(/[^/]*){2,}'")
        //     ->orWhereRaw("description LIKE '%Updated receipt B%' ")
        //     ->orWhereRaw("description LIKE '%Updated remittance B%'")
        //     ->orWhereRaw("description LIKE '%Created remittance B%' ")
        //     ->orWhereRaw("description LIKE '%Created receipt B%' ")
        //     // ->where('description', 'like' '%Updated voucher%')
        //     ->latest();

        $query->where(function ($q) {
            $q->whereRaw("description LIKE '%Updated voucher%' AND description REGEXP '[^/]*(/[^/]*){2,}'")
                ->orWhereRaw("description LIKE '%Updated receipt B%'")
                ->orWhereRaw("description LIKE '%Updated remittance B%'")
                ->orWhereRaw("description LIKE '%Created remittance B%'")
                ->orWhereRaw("description LIKE '%Created receipt B%'")
                ->orWhereRaw("description LIKE '%Created schedule%'");
        })->latest();

        // dd('here');
        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('log_name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhereHas('causer', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get paginated results
        $perPage = $request->per_page ?? 25;
        $logs = $query->paginate($perPage);

        // Get available filters with user roles
        $userIds = ActivityLog::distinct()->pluck('causer_id')->filter()->values()->toArray();

        $users = User::whereIn('id', $userIds)
            ->with('roles:name') // Load roles relationship
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role', // Get first role name
                    'roles' => $user->roles->pluck('name')->toArray() // All roles
                ];
            });

        $filters = [
            'log_names' => ActivityLog::distinct()->pluck('log_name')->filter()->values(),
            'events' => ActivityLog::distinct()->pluck('event')->filter()->values(),
            'users' => $users
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs->items(),
                'pagination' => [
                    'current_page' => $logs->currentPage(),
                    'last_page' => $logs->lastPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                ],
                'filters' => $filters,
            ],
        ]);
    }

    public function show($id)
    {
        $log = ActivityLog::with(['causer' => function ($query) {
            $query->with('roles:name')->select('id', 'name', 'email');
        }])->findOrFail($id);

        // Format user with roles
        if ($log->causer) {
            $log->causer->role = $log->causer->roles->first()?->name ?? 'No Role';
            $log->causer->roles_list = $log->causer->roles->pluck('name')->toArray();
        }

        return response()->json([
            'success' => true,
            'data' => $log,
        ]);
    }

    public function stats(Request $request)
    {
        $dateRange = $request->date_range ?? 'today';

        $query = ActivityLog::query();

        switch ($dateRange) {
            case 'today':
                $query->whereDate('created_at', today());
                break;
            case 'week':
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                break;
            case 'month':
                $query->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                break;
        }

        $stats = [
            'total_activities' => $query->count(),
            'activities_by_log_name' => $query->select('log_name', DB::raw('count(*) as count'))
                ->groupBy('log_name')
                ->get(),
            'activities_by_event' => $query->select('event', DB::raw('count(*) as count'))
                ->groupBy('event')
                ->get(),
            'top_users' => $query->select('causer_id', DB::raw('count(*) as count'))
                ->with(['causer:id,name'])
                ->groupBy('causer_id')
                ->orderByDesc('count')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'user_id' => $item->causer_id,
                        'user_name' => $item->causer?->name ?? 'Unknown',
                        'count' => $item->count
                    ];
                }),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }

    public function dashboardMetrics()
    {
        $today = now()->startOfDay();

        // Get users with their roles for today's activities
        $todayLogs = ActivityLog::whereDate('created_at', $today)
            ->with(['causer' => function ($query) {
                $query->with('roles:name')->select('id', 'name');
            }])
            ->get();

        $metrics = [
            'today_activities' => $todayLogs->count(),
            'unique_users_today' => $todayLogs->pluck('causer_id')->unique()->count(),
            'most_active_user' => $todayLogs->groupBy('causer_id')
                ->map(function ($logs) {
                    return [
                        'user_id' => $logs->first()->causer_id,
                        'user_name' => $logs->first()->causer?->name ?? 'Unknown',
                        'count' => $logs->count()
                    ];
                })
                ->sortByDesc('count')
                ->first(),
            'activity_trend' => ActivityLog::select(
                DB::raw('DATE(created_at) as date'),
                DB::raw('count(*) as count')
            )
                ->whereDate('created_at', '>=', now()->subDays(7))
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
        ];

        return response()->json([
            'success' => true,
            'data' => $metrics,
        ]);
    }


    public function dashboardStats(Request $request)
    {
        // dd($request->all());
        $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1',
            'search' => 'nullable|string',
            'log_name' => 'nullable|string',
            'event' => 'nullable|string',
            'causer_id' => 'nullable|integer',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
            'sort_by' => 'nullable|in:created_at,log_name,event',
            'sort_dir' => 'nullable|in:asc,desc',
        ]);


        $query = ActivityLog::with(['causer:id,name,email'])
            // ->whereRaw("LENGTH(description) - LENGTH(REPLACE(description, '/', '')) > 1")
            // ->whereRaw("description LIKE '%Updated voucher%' AND description REGEXP '[^/]*(/[^/]*){2,}'")
            // ->orWhereRaw("description LIKE '%Updated receipt B%' ")
            // ->orWhereRaw("description LIKE '%Updated remittance B%'")
            // ->orWhereRaw("description LIKE '%Created remittance B%' ")
            // ->orWhereRaw("description LIKE '%Created receipt B%' ")
            // ->where('description', 'like' '%Updated voucher%')
            ->latest();

            $query->where(function ($q) {
            $q->whereRaw("description LIKE '%Updated voucher%' AND description REGEXP '[^/]*(/[^/]*){2,}'")
                ->orWhereRaw("description LIKE '%Updated receipt B%'")
                ->orWhereRaw("description LIKE '%Updated remittance B%'")
                ->orWhereRaw("description LIKE '%Created remittance B%'")
                ->orWhereRaw("description LIKE '%Created receipt B%'")
                ->orWhereRaw("description LIKE '%Created schedule%'");
        });

        // Apply filters
        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('log_name', 'like', "%{$request->search}%")
                    ->orWhere('description', 'like', "%{$request->search}%")
                    ->orWhereHas('causer', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")
                            ->orWhere('email', 'like', "%{$request->search}%");
                    });
            });
        }

        if ($request->filled('log_name')) {
            $query->where('log_name', $request->log_name);
        }

        if ($request->filled('event')) {
            $query->where('event', $request->event);
        }

        if ($request->filled('causer_id')) {
            $query->where('causer_id', $request->causer_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortBy = $request->sort_by ?? 'created_at';
        $sortDir = $request->sort_dir ?? 'desc';
        $query->orderBy($sortBy, $sortDir);

        // Get paginated results
        $perPage = $request->per_page ?? 25;
        $logs = $query->paginate($perPage);
        // dd($logs[0]->created_at->setTimezone('Africa/Lagos')->format('Y-m-d H:i:s'));

        $paginator = [
            "total" => $logs->total(),
            "per_page" => $logs->perPage(),
            "current_page" => $logs->currentPage(),
            "last_page" => $logs->lastPage(),
            "first_page_url" => $logs->url(1),
            "last_page_url" => $logs->url($logs->lastPage()),
            "next_page_url" => $logs->nextPageUrl(),
            "prev_page_url" => $logs->previousPageUrl(),
            "path" => $logs->path(),
            "from" => $logs->currentPage(),
            "to" => $logs->perPage(),
        ];

        // Get available filters with user roles
        $userIds = ActivityLog::distinct()->pluck('causer_id')->filter()->values()->toArray();

        $users = User::whereIn('id', $userIds)
            ->with('roles:name') // Load roles relationship
            ->get(['id', 'name', 'email'])
            ->map(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->roles->first()?->name ?? 'No Role', // Get first role name
                    'roles' => $user->roles->pluck('name')->toArray() // All roles
                ];
            });

        $filters = [
            'log_names' => ActivityLog::distinct()->pluck('log_name')->filter()->values(),
            'events' => ActivityLog::distinct()->pluck('event')->filter()->values(),
            'users' => $users
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'logs' => $logs->items(),
                // 'pagination' => [
                //     'current_page' => $logs->currentPage(),
                //     'last_page' => $logs->lastPage(),
                //     'per_page' => $logs->perPage(),
                //     'total' => $logs->total(),
                // ],
                'paginator' => $paginator,
                'filters' => $filters,
            ],
        ]);
    }
}
