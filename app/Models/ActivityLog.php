<?php

namespace App\Models;

use Spatie\Activitylog\Models\Activity as SpatieActivity;
use Carbon\Carbon;
class ActivityLog extends SpatieActivity
{
    protected $casts = [
        'properties' => 'collection',
        // 'created_at' => 'datetime:Y-m-d H:i:s',
        // 'updated_at' => 'datetime:Y-m-d H:i:s',
    ];

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('log_name', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhereHas('causer', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhereHas('subject', function ($q) use ($search) {
                    $q->where('id', 'like', "%{$search}%");
                });
        });
    }

    public function scopeFilter($query, array $filters)
    {
        $query->when($filters['log_name'] ?? null, function ($query, $logName) {
            $query->where('log_name', $logName);
        })
            ->when($filters['event'] ?? null, function ($query, $event) {
                $query->where('event', $event);
            })
            ->when($filters['causer_id'] ?? null, function ($query, $causerId) {
                $query->where('causer_id', $causerId);
            })
            ->when($filters['date_from'] ?? null, function ($query, $dateFrom) {
                $query->whereDate('created_at', '>=', $dateFrom);
            })
            ->when($filters['date_to'] ?? null, function ($query, $dateTo) {
                $query->whereDate('created_at', '<=', $dateTo);
            });
    }

    // public function getCreatedAtAttribute($date)
    // {
    //     return Carbon::parse($this->created_at)->setTimezone('Africa/Lagos')->format('Y-m-d g:i A');
    // }
}
