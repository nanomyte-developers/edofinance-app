<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Http\Requests\PayeeRequest;
use App\Http\Resources\PayeeResource;
use App\Models\Payee;
use App\Services\PayeeService;
use Inertia\Inertia;
use Inertia\Response;

class PayeeController extends Controller
{
    protected $service;

    public function __construct(PayeeService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        // Use filtered input to avoid malicious parameters
        $filters = $request->only(['search']);

        $payees = $this->service->listPayees($filters['search'] ?? null);

        return Inertia::render('admin/payees/index', [
            // We use .additional to pass meta data if needed
            'payees' => PayeeResource::collection($payees),
            'filters' => $filters
        ]);
    }
    // In your PayeeController index method
    // public function index(Request $request)
    // {
    //     $perPage = $request->get('per_page', 10);
    //     $search = $request->get('search');
        
    //     $query = Payee::query();
        
    //     if ($search) {
    //         $query->where('name', 'like', "%{$search}%");
    //     }
        
    //     $payees = $query->paginate($perPage);
        
    //     // Add metadata for statistics
    //     $payees->getCollection()->transform(function ($payee) {
    //         return $payee;
    //     });
        
    //     return inertia('admin/payees/index', [
    //         'payees' => $payees,
    //         'filters' => $request->only(['search', 'per_page']),
    //         'meta' => [
    //             'active_count' => Payee::where('status', true)->count(),
    //             'total' => Payee::count(),
    //         ]
    //     ]);
    // }

    public function store(PayeeRequest $request)
    {
        $this->service->storePayee($request->validated());
        return back()->with('message', 'Payee created successfully.');
    }

    public function update(PayeeRequest $request, Payee $payee)
    {
        $this->service->updatePayee($payee, $request->validated());
        return back()->with('message', 'Payee updated successfully.');
    }

    public function toggleStatus(Payee $payee)
    {
        $this->service->toggleStatus($payee);
        return back()->with('message', 'Status updated.');
    }
}
