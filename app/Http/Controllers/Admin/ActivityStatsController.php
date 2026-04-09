<?php
// app/Http/Controllers/Api/ActivityStatsController.php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class ActivityStatsController extends Controller
{
    public function index(Request $request)
    {
        $query = ActivityLog::with('causer')
            ->orderBy('created_at', 'desc');

        // Search by description or properties
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                    ->orWhere('log_name', 'like', "%{$search}%")
                    ->orWhere('properties', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->has('date_from') && $request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // User filter
        if ($request->has('user_id') && $request->user_id) {
            $query->where('causer_id', $request->user_id);
        }

        // Log name filter
        if ($request->has('log_name') && $request->log_name) {
            $query->where('log_name', $request->log_name);
        }

        // Event filter
        if ($request->has('event') && $request->event) {
            $query->where('event', $request->event);
        }

        $logs = $query->paginate($request->per_page ?? 25);

        // Get filters for dropdowns
        $logNames = ActivityLog::distinct()->pluck('log_name')->filter();
        $events = ActivityLog::distinct()->pluck('event')->filter();
        $users = ActivityLog::with('causer')
            ->whereNotNull('causer_id')
            ->select('causer_id')
            ->distinct()
            ->get()
            ->pluck('causer')
            ->filter()
            ->unique('id');

        return Inertia::render('activities/Index', [
            'logs' => $logs,
            'filters' => [
                'log_names' => $logNames,
                'events' => $events,
                'users' => $users,
            ],
            'search' => $request->search ?? '',
            'date_from' => $request->date_from ?? null,
            'date_to' => $request->date_to ?? null,
            'user_id' => $request->user_id ?? null,
            'log_name' => $request->log_name ?? null,
            'event' => $request->event ?? null,
        ]);
    }

    public function stats(Request $request)
    {
        $query = ActivityLog::query();

        // Apply time range filter
        if ($request->timeRange && $request->timeRange !== 'all') {
            $date = match ($request->timeRange) {
                '24hours' => now()->subHours(24),
                '7days' => now()->subDays(7),
                '30days' => now()->subDays(30),
                default => now()->subDays(7),
            };
            $query->where('created_at', '>=', $date);
        }

        // Apply custom date range
        if ($request->date_from && $request->date_to) {
            $query->whereBetween('created_at', [
                $request->date_from . ' 00:00:00',
                $request->date_to . ' 23:59:59'
            ]);
        }

        // Get stats
        $totalActivities = $query->count();

        $activeUsers = ActivityLog::whereNotNull('causer_id')
            ->when($request->timeRange && $request->timeRange !== 'all', function ($q) use ($request) {
                $date = match ($request->timeRange) {
                    '24hours' => now()->subHours(24),
                    '7days' => now()->subDays(7),
                    '30days' => now()->subDays(30),
                    default => now()->subDays(7),
                };
                $q->where('created_at', '>=', $date);
            })
            ->distinct('causer_id')
            ->count('causer_id');

        $todayActivities = ActivityLog::whereDate('created_at', today())->count();

        // Get user stats
        $userStatsQuery = ActivityLog::with('causer')
            ->select('causer_id', DB::raw('count(*) as activity_count'))
            ->whereNotNull('causer_id')
            ->groupBy('causer_id');

        // Apply filters to user stats
        if ($request->timeRange && $request->timeRange !== 'all') {
            $date = match ($request->timeRange) {
                '24hours' => now()->subHours(24),
                '7days' => now()->subDays(7),
                '30days' => now()->subDays(30),
                default => now()->subDays(7),
            };
            $userStatsQuery->where('created_at', '>=', $date);
        }

        if ($request->minActivities) {
            $userStatsQuery->having('activity_count', '>=', $request->minActivities);
        }

        $userStats = $userStatsQuery->get()
            ->map(function ($log) {
                if (!$log->causer) return null;

                // Get user's recent activity
                $recentActivity = ActivityLog::where('causer_id', $log->causer_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Get activity types breakdown
                $activityTypes = ActivityLog::where('causer_id', $log->causer_id)
                    ->select('log_name', DB::raw('count(*) as count'))
                    ->groupBy('log_name')
                    ->pluck('count', 'log_name');

                // Calculate days since last activity
                $lastActivity = ActivityLog::where('causer_id', $log->causer_id)
                    ->orderBy('created_at', 'desc')
                    ->first();

                $lastActivityDays = $lastActivity
                    ? now()->diffInDays($lastActivity->created_at)
                    : null;

                return [
                    'id' => $log->causer_id,
                    'name' => $log->causer->name,
                    'email' => $log->causer->email,
                    'activityCount' => $log->activity_count,
                    'activityTypes' => $activityTypes,
                    'recentActivity' => $recentActivity ? [
                        'description' => $recentActivity->description,
                        'created_at' => $recentActivity->created_at,
                    ] : null,
                    'lastActivityDays' => $lastActivityDays,
                ];
            })
            ->filter()
            ->values();

        return response()->json([
            'stats' => [
                'totalActivities' => $totalActivities,
                'activeUsers' => $activeUsers,
                'todayActivities' => $todayActivities,
            ],
            'userStats' => $userStats,
        ]);
    }

    // Get activity statistics
    public function getStats(Request $request)
    {
        // dd("here we are");
        try {
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $timeRange = $request->get('timeRange', '7days');
            $minActivities = $request->get('minActivities', 0);

            // Build base query
            $query = DB::table('activity_log')
                ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
                ->where('description', '=', 'Updated voucher');

            // Apply date filters
            if ($dateFrom && $dateTo) {
                $query->whereBetween('activity_log.created_at', [$dateFrom, $dateTo]);
            } elseif ($timeRange !== 'all' && $timeRange !== 'custom') {
                $dateRange = $this->getDateRange($timeRange);
                $query->whereBetween('activity_log.created_at', $dateRange);
            }

            // Get total statistics
            $totalActivities = (clone $query)->count();
            $activeUsers = (clone $query)->distinct('activity_log.causer_id')->count('activity_log.causer_id');
            $todayActivities = (clone $query)->whereDate('activity_log.created_at', Carbon::today())->count();

            // Get user statistics
            $userStatsQuery = DB::table('activity_log')
                ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
                ->select(
                    'users.id',
                    'users.name',
                    'users.email',
                    DB::raw('COUNT(activity_log.id) as activity_count'),
                    DB::raw('MAX(activity_log.created_at) as last_activity'),
                    DB::raw('COUNT(DISTINCT DATE(activity_log.created_at)) as days_active')
                )
                ->where('description', '=', 'Updated voucher')
                ->groupBy('users.id', 'users.name', 'users.email');

            // Apply same filters to user stats
            if ($dateFrom && $dateTo) {
                $userStatsQuery->whereBetween('activity_log.created_at', [$dateFrom, $dateTo]);
            } elseif ($timeRange !== 'all' && $timeRange !== 'custom') {
                $dateRange = $this->getDateRange($timeRange);
                $userStatsQuery->whereBetween('activity_log.created_at', $dateRange);
            }

            // Filter by minimum activities
            if ($minActivities > 0) {
                $userStatsQuery->having('activity_count', '>=', $minActivities);
            }

            $userStats = $userStatsQuery
                ->orderBy('activity_count', 'desc')
                ->limit(12) // Limit to top 12 users
                ->get()
                ->map(function ($user) use ($dateFrom, $dateTo, $timeRange) {
                    // Get activity types for this user
                    $activityTypesQuery = DB::table('activity_log')
                        ->where('description', '=', 'Updated voucher')
                        ->where('causer_id', $user->id)
                        ->select('log_name', DB::raw('COUNT(*) as count'))
                        ->groupBy('log_name');

                    if ($dateFrom && $dateTo) {
                        $activityTypesQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
                    } elseif ($timeRange !== 'all' && $timeRange !== 'custom') {
                        $dateRange = $this->getDateRange($timeRange);
                        $activityTypesQuery->whereBetween('created_at', $dateRange);
                    }

                    $activityTypes = $activityTypesQuery->get()
                        ->pluck('count', 'log_name')
                        ->toArray();

                    // Get most recent activity
                    $recentActivity = DB::table('activity_log')
                        ->where('description', '=', 'Updated voucher')
                        ->where('causer_id', $user->id)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    return [
                        'id' => $user->id,
                        'name' => $user->name ?? 'System',
                        'email' => $user->email ?? 'system@example.com',
                        'activityCount' => $user->activity_count,
                        'lastActivityDays' => $user->last_activity ?
                            Carbon::parse($user->last_activity)->diffInDays(Carbon::now()) : 0,
                        'daysActive' => $user->days_active,
                        'activityTypes' => $activityTypes,
                        'recentActivity' => $recentActivity ? [
                            'description' => $recentActivity->description,
                            'created_at' => $recentActivity->created_at
                        ] : null
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => [
                        'totalActivities' => $totalActivities,
                        'activeUsers' => $activeUsers,
                        'todayActivities' => $todayActivities,
                    ],
                    'userStats' => $userStats
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get user activities
    public function getUserActivities(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $search = $request->get('search');
            $logName = $request->get('log_name');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDir = $request->get('sort_dir', 'desc');
            $perPage = $request->get('per_page', 25);
            $page = $request->get('page', 1);

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            // Build query
            $query = DB::table('activity_log')
                ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
                ->where('activity_log.causer_id', $userId)
                ->select(
                    'activity_log.*',
                    'users.name as causer_name',
                    'users.email as causer_email'
                );

            // Apply filters
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('activity_log.description', 'like', "%{$search}%")
                        ->orWhere('activity_log.log_name', 'like', "%{$search}%");
                });
            }

            if ($logName) {
                $query->where('activity_log.log_name', $logName);
            }

            if ($dateFrom && $dateTo) {
                $query->whereBetween('activity_log.created_at', [$dateFrom, $dateTo]);
            }

            // Get total count for pagination
            $total = $query->count();

            // Apply sorting and pagination
            $query->orderBy($sortBy, $sortDir)
                ->skip(($page - 1) * $perPage)
                ->take($perPage);

            $activities = $query->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'log_name' => $activity->log_name,
                        'description' => $activity->description,
                        'event' => $activity->event,
                        'properties' => json_decode($activity->properties, true) ?? [],
                        'created_at' => $activity->created_at,
                        'updated_at' => $activity->updated_at,
                        'causer' => $activity->causer_name ? [
                            'id' => $activity->causer_id,
                            'name' => $activity->causer_name,
                            'email' => $activity->causer_email,
                        ] : null
                    ];
                });

            // Get summary statistics
            $summaryQuery = DB::table('activity_log')
                ->where('causer_id', $userId);

            if ($dateFrom && $dateTo) {
                $summaryQuery->whereBetween('created_at', [$dateFrom, $dateTo]);
            }

            $totalActivities = $summaryQuery->count();
            $todayActivities = $summaryQuery->whereDate('created_at', Carbon::today())->count();
            $daysActive = $summaryQuery->distinct(DB::raw('DATE(created_at)'))->count();
            $lastActivity = $summaryQuery->max('created_at');

            // Get activity type breakdown
            $activityTypes = $summaryQuery
                ->select('log_name', DB::raw('COUNT(*) as count'))
                ->groupBy('log_name')
                ->get()
                ->pluck('count', 'log_name')
                ->toArray();

            return response()->json([
                'success' => true,
                'data' => [
                    'activities' => $activities,
                    'summary' => [
                        'totalActivities' => $totalActivities,
                        'todayActivities' => $todayActivities,
                        'daysActive' => $daysActive,
                        'lastActivity' => $lastActivity,
                        'activityTypes' => $activityTypes
                    ],
                    'pagination' => [
                        'current_page' => (int)$page,
                        'per_page' => (int)$perPage,
                        'total' => $total,
                        'last_page' => ceil($total / $perPage)
                    ]
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Export user activities
    public function exportUserActivities(Request $request)
    {
        try {
            $userId = $request->get('user_id');
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            $query = DB::table('activity_log')
                ->leftJoin('users', 'activity_log.causer_id', '=', 'users.id')
                ->where('activity_log.causer_id', $userId)
                ->select(
                    'activity_log.created_at',
                    'activity_log.log_name',
                    'activity_log.description',
                    'activity_log.event',
                    'activity_log.properties',
                    'users.name as user_name',
                    'users.email as user_email'
                );

            if ($dateFrom && $dateTo) {
                $query->whereBetween('activity_log.created_at', [$dateFrom, $dateTo]);
            }

            $activities = $query->orderBy('activity_log.created_at', 'desc')->get();

            // Generate CSV
            $csv = "Date,Time,User,Email,Type,Description,Event,IP Address,Voucher Number,Status\n";

            foreach ($activities as $activity) {
                $properties = json_decode($activity->properties, true) ?? [];

                $row = [
                    Carbon::parse($activity->created_at)->format('Y-m-d'),
                    Carbon::parse($activity->created_at)->format('H:i:s'),
                    $activity->user_name ?? 'System',
                    $activity->user_email ?? 'system@example.com',
                    $activity->log_name,
                    '"' . str_replace('"', '""', $activity->description) . '"',
                    $activity->event ?? '',
                    $properties['ip_address'] ?? '',
                    $properties['voucher_number'] ?? '',
                    $properties['status'] ?? ''
                ];

                $csv .= implode(',', $row) . "\n";
            }

            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="user-activities.csv"');
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export activities',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper function to get date range
    private function getDateRange($timeRange)
    {
        $today = Carbon::today();

        return match ($timeRange) {
            '24hours' => [Carbon::now()->subHours(24), Carbon::now()],
            '7days' => [Carbon::now()->subDays(7), Carbon::now()],
            '30days' => [Carbon::now()->subDays(30), Carbon::now()],
            default => [Carbon::now()->subDays(7), Carbon::now()]
        };
    }
}
