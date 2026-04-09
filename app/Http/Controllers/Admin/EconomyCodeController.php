<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EconomyCodeStoreRequest;
use App\Http\Requests\EconomyCodeUpdateRequest;
use App\Http\Resources\EconomyCodeResource;
use App\Models\EconomyCode;
use App\Models\Bank;
use App\Services\EconomyCodeService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
class EconomyCodeController extends Controller
{
    protected EconomyCodeService $service;

    /**
     * Constructor for Dependency Injection.
     *
     * @param EconomyCodeService $service
     */
    public function __construct(EconomyCodeService $service)
    {
        $this->service = $service;
    }

        /**
         * Display a listing of the resource.
         */
        // public function index(Request $request): Response
        // {
        //     // Get search query from request
        //     $search = $request->input('search');

        //     // Fetch paginated data via the service
        //     $codes = $this->service->getPaginatedCodes($search, 10);

        //     return Inertia::render('admin/economyCodes/index', [
        //         // Use the Resource to transform the paginated collection
        //         'economyCodes' => EconomyCodeResource::collection($codes),
        //         'filters' => [
        //             'search' => $search,
        //         ],
        //     ]);
        // }
        // app/Http/Controllers/Admin/EconomyCodeController.php
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        // Get search query from request
        $search = $request->input('search');

        // Fetch paginated data via the service
        $codes = $this->service->getPaginatedCodes($search, 500);

        // Get statistics
        $total = $this->service->countAll();
        $active = $this->service->countActive();
        $inactive = $this->service->countInactive();

        return Inertia::render('admin/economyCodes/index', [
            // Use the Resource to transform the paginated collection
            'economyCodes' => EconomyCodeResource::collection($codes),
            'statistics' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * The validation is handled by EconomyCodeStoreRequest.
     */
    public function store(EconomyCodeStoreRequest $request): RedirectResponse
    {
        $this->service->createCode($request->validated());

        return redirect()->route('economy_code.index')
            ->with('message', 'Economy Code created successfully.');
    }

    /**
     * Update the specified resource in storage.
     *
     * The validation is handled by EconomyCodeUpdateRequest.
     */
    public function update(EconomyCodeUpdateRequest $request, $economyCode): RedirectResponse
    {


        $Model = EconomyCode::find($economyCode);

        $this->service->updateCode($Model, $request->validated());

        // Redirect back to the same page to update the data table
        return redirect()->back()
            ->with('message', 'Economy Code updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EconomyCode $economyCode): RedirectResponse
    {
        $this->service->deleteCode($economyCode);

        // Redirect back to the same page to update the data table
        return redirect()->back()
            ->with('message', 'Economy Code deleted successfully.');
    }

    // Since the original template had a 'bank.account.fetch' route,
    // I am including a placeholder for a similar structure for EconomyCode sub-items.
    // NOTE: This assumes you have a model `Account` or `MdaMonthlyExpenditure` that links to `EconomyCodeItem`.
    // // I will return a placeholder JSON response for demonstration.
    // public function fetchItems(EconomyCode $economyCode)
    // {
    //     // For demonstration, we link back to the EconomyCodeItem model.
    //     // In a real app, you might fetch related accounts/expenditures.
    //     $items = $economyCode->items()->active()->get(['id', 'name', 'code']);

    //     return response()->json([
    //         'items' => $items,
    //     ]);
    // }
    public function fetchItems(string $economyCodeId)
    {
        // 1. Manually find the EconomyCode model by its primary key (ID).
        // If the record is not found, a 404 error is returned.
        $economyCode = EconomyCode::findOrFail($economyCodeId);

        // 2. Fetch the related items (assuming 'items' is a defined relationship)
        // We select only the 'id', 'name', and 'code' columns and filter for active items.
        $items = $economyCode->items()
                            ->active() // Assuming a local scope 'active' exists
                            ->get(['id', 'name', 'code']);

        // 3. Return the items as a JSON response
        return response()->json([
            'items' => $items,
        ]);
    }

}
