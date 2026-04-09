<?php
// app/Http/Controllers/DashboardController.php

namespace App\Http\Controllers;

use App\Models\ActivityLog; // Assuming you have this model
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        // Fetch recent activity logs
        $activities = ActivityLog::with('causer')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'log_name' => $activity->log_name,
                    'description' => $activity->description,
                    'event' => $activity->event,
                    'subject_type' => $activity->subject_type,
                    'subject_id' => $activity->subject_id,
                    'causer_type' => $activity->causer_type,
                    'causer_id' => $activity->causer_id,
                    'properties' => $activity->properties,
                    'created_at' => $activity->created_at->toISOString(),
                    'updated_at' => $activity->updated_at->toISOString(),
                    'causer' => $activity->causer ? [
                        'id' => $activity->causer->id,
                        'name' => $activity->causer->name,
                        'email' => $activity->causer->email,
                    ] : null,
                ];
            });

        return Inertia::render('Dashboard', [
            'activities' => $activities,
            'stats' => [
                'orders' => 152,
                'revenue' => 2100,
                'customers' => 28441,
                'comments' => 152,
            ],
        ]);
    }
}