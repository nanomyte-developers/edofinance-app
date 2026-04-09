<?php

namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\EconomyCode; // Assuming this model exists for the parent relationship
use App\Models\EconomyCodeItem;
use App\Services\EconomyCodeItemService;
use App\Http\Requests\EconomyCodeItemRequest;
use App\Http\Resources\EconomyCodeItemResource;
use Inertia\Inertia;
use Illuminate\Http\Request;

class EconomyCodeItemController extends Controller
{
    protected EconomyCodeItemService $itemService;

    public function __construct(EconomyCodeItemService $itemService)
    {
        $this->itemService = $itemService;
        // Example policy-based authorization
        // $this->authorizeResource(EconomyCodeItem::class, 'economy_code_item');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Fetch paginated items via the service
        $items = $this->itemService->getPaginatedItems(
            $request->get('per_page', 100)
        );

        // Fetch the list of parent Economy Codes for the dropdown filter/modal
        $economyCodes = EconomyCode::select('id', 'name')->get();

        // Get statistics
        $total = EconomyCodeItem::count();
        $active = EconomyCodeItem::where('status', 1)->count();
        $inactive = EconomyCodeItem::where('status', 0)->count();

        return Inertia::render('admin/economyCodeItems/index', [
            // Use the Resource to format the pagination data
            'economyCodeItems' => EconomyCodeItemResource::collection($items),
            'economyCodes' => $economyCodes->toArray(),
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
    public function store(EconomyCodeItemRequest $request)
    {
        $this->itemService->createItem($request->validated());

        return redirect()->route('economy-code-itemss.index')->with('flash', [
            'message' => 'Economy Code Item created successfully.',
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(EconomyCodeItem $economyCodeItem)
    {
        return new EconomyCodeItemResource($economyCodeItem->load('economyCode'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EconomyCodeItemRequest $request, EconomyCodeItem $economy_code_itemss)
    {
        // dd($economy_code_itemss);
        $this->itemService->updateItem($economy_code_itemss, $request->validated());

        return redirect()->route('economy-code-itemss.index')->with('flash', [
            'message' => 'Economy Code Item updated successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(EconomyCodeItem $economyCodeItem)
    {
        $this->itemService->deleteItem($economyCodeItem);

        return redirect()->route('economy-code-itemss.index')->with('flash', [
            'message' => 'Economy Code Item deleted successfully.',
        ]);
    }
}
