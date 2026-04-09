<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\AdministrativeCode; // Assuming this model exists for the parent relationship
use App\Models\AdministrativeSectorCode as administrativeCodeItem;
use App\Services\AdministrativeCodeItemService;
use App\Http\Requests\AdministrativeCodeItemRequest;
use App\Http\Resources\AdministratorCodeItemResource;
use Inertia\Inertia;
use Illuminate\Http\Request;

class AdministrativeCodeItemController extends Controller
{
    protected AdministrativeCodeItemService $itemService;

    public function __construct(AdministrativeCodeItemService $itemService)
    {
        $this->itemService = $itemService;
        // Example policy-based authorization
        // $this->authorizeResource(administrativeCodeItem::class, 'economy_code_item');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Fetch paginated items via the service
        $items = $this->itemService->getPaginatedItems(
            $request->get('per_page', 200)
        );

        // dd($items->links()->elements[0]);

        // Fetch the list of parent Economy Codes for the dropdown filter/modal
        $administrativeCodes = AdministrativeCode::select('id', 'name')->get();

        // Get statistics
        $total = administrativeCodeItem::count();
        $active = administrativeCodeItem::where('status', 1)->count();
        $inactive = administrativeCodeItem::where('status', 0)->count();

        return Inertia::render('admin/administrativeCodeItems/index', [
            // Use the Resource to format the pagination data
            'administrativeCodeItems' => AdministratorCodeItemResource::collection($items),
            'administrativeCodes' => $administrativeCodes->toArray(),
            'meta' => [
                'per_page' => $items->perPage(),
                'current_page' => $items->currentPage(),
                'totalPages' => $items->lastPage(),
                'onEachSide' => $items->onEachSide(1),
                'total' => $items->total(),
                'lastPage' => $items->lastPage(),
                // 'hasMorePages' => $items->hasMorePages(),
                // 'options' => $items->options(),
                'path' => $items->path(),


            ],
            'links' => $items->links()->elements,
            'statistics' => [
                'total' => $total,
                'active' => $active,
                'inactive' => $inactive
            ],
            'flash' => session('flash'),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AdministrativeCodeItemRequest $request)
    {
        $this->itemService->createItem($request->validated());

        return redirect()->route('administrative-code-itemss.index')->with('flash', [
            'message' => 'Administrative Code Item created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(AdministrativeCodeItem $administrativeCodeItem)
    {
        return new AdministratorCodeItemResource($administrativeCodeItem->load('AdministrativeCode'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AdministrativeCodeItemRequest $request, AdministrativeCodeItem $administrative_code_itemss)
    {



        // dd("i was here");
        $this->itemService->updateItem($administrative_code_itemss, $request->validated());

        return redirect()->route('administrative-code-itemss.index')->with('flash', [
            'message' => 'Administrative Code Item updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(AdministrativeCodeItem $administrativeCodeItem)
    {
        $this->itemService->deleteItem($administrativeCodeItem);

        return redirect()->route('administrative-code-itemss.index')->with('flash', [
            'message' => 'Administrative Code Item deleted successfully.',
        ]);
    }
}
