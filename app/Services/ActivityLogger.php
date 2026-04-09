<?php
// app/Services/ActivityLogger.php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Spatie\Activitylog\Facades\CauserResolver;

class ActivityLogger
{
    protected $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function log($description, $properties = [], $logName = 'default')
    {
        activity()
            ->causedBy(Auth::user())
            ->withProperties(array_merge($properties, [
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'url' => $this->request->fullUrl(),
                'method' => $this->request->method(),
            ]))
            ->log($description);
    }

    public function logLogin(User $user)
    {
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'login_time' => now(),
            ])
            ->log('User logged in');
    }

    public function logLogout(User $user)
    {
        activity()
            ->causedBy($user)
            ->withProperties([
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
                'logout_time' => now(),
            ])
            ->log('User logged out');
    }

    public function logApproval($model, $approvedBy, $remarks = null)
    {
        $modelName = class_basename($model);
        
        activity()
            ->causedBy($approvedBy)
            ->performedOn($model)
            ->withProperties([
                'approved_by' => $approvedBy->name,
                'approved_at' => now(),
                'remarks' => $remarks,
                'old_status' => $model->getOriginal('status'),
                'new_status' => $model->status,
            ])
            ->log("{$modelName} approved");
    }

    public function logAction($action, $entity, $details = [])
    {
        activity()
            ->causedBy(Auth::user())
            ->performedOn($entity)
            ->withProperties(array_merge($details, [
                'action' => $action,
                'ip_address' => $this->request->ip(),
                'user_agent' => $this->request->userAgent(),
            ]))
            ->log(ucfirst($action) . ' ' . class_basename($entity));
    }
}