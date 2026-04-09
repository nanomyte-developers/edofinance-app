<?php

namespace App\Http\Controllers\Admin;
use Inertia\Inertia;
use App\Models\BankActivity;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\BankActivityService;
use App\Http\Requests\BankActivityRequest;
use App\Http\Resources\BankActivityResource;

class BankActivityController extends Controller
{
    protected $bankService;

    public function __construct(BankActivityService $service)
    {
        $this->bankService = $service;
    }

    // In your controller
    // public function index(Request $request) // This should be Illuminate\Http\Request
    // {
    //     $query = BankActivity::query();
        
    //     if ($request->has('search') && $request->search) {
    //         $search = $request->search;
    //         $query->where(function($q) use ($search) {
    //             $q->where('bank_name', 'like', "%{$search}%")
    //             ->orWhere('title', 'like', "%{$search}%")
    //             ->orWhere('tag', 'like', "%{$search}%")
    //             ->orWhere('account_number', 'like', "%{$search}%");
    //         });
    //     }
        
    //     $activities = $query->paginate(10);
        
    //     return Inertia::render('admin/bankActivities/index', [
    //         // 'activities' => $activities,
    //         'activities' => BankActivityResource::collection($activities)->response()->getData(true)

    //     ]);
    // }

    // app/Http/Controllers/Admin/BankActivityController.php
    // public function index(Request $request)
    // {
    //     $query = BankActivity::query();
        
    //     if ($request->has('search') && $request->search) {
    //         $search = $request->search;
    //         $query->where(function($q) use ($search) {
    //             $q->where('bank_name', 'like', "%{$search}%")
    //             ->orWhere('title', 'like', "%{$search}%")
    //             ->orWhere('tag', 'like', "%{$search}%")
    //             ->orWhere('account_number', 'like', "%{$search}%");
    //         });
    //     }
        
    //     $activities = $query->paginate(10);
        
    //     // Get total statistics (unpaginated)
    //     $total = BankActivity::count();
    //     $active = BankActivity::where('status', 1)->count();
    //     $inactive = BankActivity::where('status', 0)->count();
        
    //     return Inertia::render('admin/bankActivities/index', [
    //         'activities' => $activities,
    //         'statistics' => [
    //             'total' => $total,
    //             'active' => $active,
    //             'inactive' => $inactive
    //         ]
    //     ]);
    // }

    // app/Http\Controllers\Admin\BankActivityController.php
    public function index(Request $request)
    {
        $query = BankActivity::query();
        
        // Search
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('bank_name', 'like', "%{$search}%")
                ->orWhere('title', 'like', "%{$search}%")
                ->orWhere('tag', 'like', "%{$search}%")
                ->orWhere('economic_code', 'like', "%{$search}%")
                ->orWhere('account_number', 'like', "%{$search}%");
            });
        }
        
        // Sorting
        $sortField = $request->input('sort_field', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        
        // Validate sort field to prevent SQL injection
        $allowedSortFields = ['id', 'tag', 'bank_name', 'title', 'account_number', 'status', 'created_at', 'updated_at'];
        if (!in_array($sortField, $allowedSortFields)) {
            $sortField = 'id';
        }
        
        $query->orderBy($sortField, $sortOrder);
        
        // Pagination - default 20 per page
        $perPage = $request->input('per_page', 300);
        $activities = $query->paginate($perPage);
        
        // Get total statistics (unpaginated)
        $total = BankActivity::count();
        $active = BankActivity::where('status', 1)->count();
        $inactive = BankActivity::where('status', 0)->count();
        
        return Inertia::render('admin/bankActivities/index', [
            'activities' => $activities,
            'statistics' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive
            ],
            'filters' => [
                'search' => $request->search,
                'sort_field' => $sortField,
                'sort_order' => $sortOrder,
                'per_page' => $perPage,
            ]
        ]);
    }

    public function store(BankActivityRequest $request)
    {

        $this->bankService->store($request->validated());
        return redirect()->back()->with('message', 'Activity created successfully.');
    }

    public function update(BankActivityRequest $request, BankActivity $bankActivity)
    {
        $this->bankService->update($bankActivity, $request->validated());
        return redirect()->back()->with('message', 'Activity updated successfully.');
    }

    public function destroy(BankActivity $bankActivity)
    {
        $this->bankService->delete($bankActivity);
        return redirect()->back()->with('message', 'Activity deleted successfully.');
    }

}
