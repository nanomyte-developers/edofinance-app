<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use App\Models\Bank;
use App\Services\BankService;
use App\Http\Requests\BankRequest;
use App\Http\Resources\BankResource;
use Inertia\Inertia;
use Illuminate\Http\Request;

class BankController extends Controller
{
    protected $bankService;

    public function __construct(BankService $bankService)
    {
        $this->bankService = $bankService;
    }

    // public function index()
    // {
    //     $banks = $this->bankService->getAllPaginated();
    //     return Inertia::render('admin/banks/index', [
    //         'banks' => BankResource::collection($banks)
    //     ]);
    // }

    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $banks = $this->bankService->getAllPaginated($search);
        
        return Inertia::render('admin/banks/index', [
            'banks' => BankResource::collection($banks),
            'filters' => $request->only(['search'])
        ]);
    }



    public function store(BankRequest $request)
    {
        $this->bankService->storeBank($request->validated());
        return redirect()->back()->with('message', 'Bank added successfully.');
    }

    public function update(BankRequest $request, Bank $bank)
    {
        $this->bankService->updateBank($bank, $request->validated());
        return redirect()->back()->with('message', 'Bank updated successfully.');
    }

    public function toggleStatus(Bank $bank)
    {
        $this->bankService->toggleStatus($bank);
        return redirect()->back()->with('message', 'Bank status updated.');
    }

    /// cash

}
