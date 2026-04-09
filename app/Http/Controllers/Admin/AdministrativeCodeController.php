<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
//use Illuminate\Http\Request;

use App\Http\Requests\AdministrativeCodeRequest;
use App\Http\Resources\AdministrativeCodeResource;
use App\Models\AdministrativeCode;
use App\Services\AdministrativeCodeService;
use Inertia\Inertia;

class AdministrativeCodeController extends Controller
{
    public function __construct(protected AdministrativeCodeService $service) {}

    public function index()
    {
        $codes = $this->service->getAllPaginated();
        return Inertia::render('admin/administrativeCodes/index', [
            'administrativeCodes' => AdministrativeCodeResource::collection($codes)
        ]);
    }

    public function store(AdministrativeCodeRequest $request)
    {
        $this->service->store($request->validated());
        return back()->with('message', 'Administrative Code created successfully.');
    }

    // public function update(AdministrativeCodeRequest $request, AdministrativeCode $administrativeCode)
    // {
    //     $this->service->update($administrativeCode, $request->validated());
    //     return back()->with('message', 'Administrative Code updated successfully.');
    // }

    // public function update(AdministrativeCodeRequest $request, $id)
    // {
    //     // Make sure you're finding the record
    //     $administrativeCode = AdministrativeCode::findOrFail($id);
        
    //     // Update logic here
    //     $administrativeCode->update($request->validated());
        
    //     return redirect()->route('administrative-codes.index')
    //         ->with('message', 'Administrative code updated successfully.');
    // }

    public function update(AdministrativeCodeRequest $request, AdministrativeCode $administrative_code)
{
    $administrative_code->update($request->validated());
    
    return redirect()->route('administrative-codes.index')
        ->with('message', 'Administrative code updated successfully.');
}

    public function toggleStatus(AdministrativeCode $administrativeCode)
    {
        $this->service->toggleStatus($administrativeCode);
        return back();
    }

    public function show(AdministrativeCode $administrativeCode)
    {
        // For the "View MDA" feature
        return response()->json($administrativeCode->load('mda'));
    }
}
