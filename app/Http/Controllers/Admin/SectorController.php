<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Models\Mda; 
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Services\SectorService; 
use App\Http\Requests\SectorStoreUpdateRequest; 

class SectorController extends Controller
{
    protected SectorService $sectorService;

    // Inject the service into the controller
    public function __construct(SectorService $sectorService)
    {
        $this->sectorService = $sectorService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // 1. Fetch and paginate sectors, eager loading the related MDA
        $sectors = Sector::with('mda:id,initials,name') // Only select necessary MDA fields
            ->select('id', 'mda_id', 'name', 'code', 'initials', 'location', 'status')
            ->latest('name')
            ->paginate(15);

        // 2. Fetch all MDAs for the dropdown list (select only ID and Initials)
        $mdas = Mda::select('id', 'initials', 'name')
            ->orderBy('initials')
            ->get()
            ->map(fn($mda) => [
                'value' => $mda->id,
                'label' => "{$mda->initials} ({$mda->name})",
            ]);
            
        // 3. Render the Inertia component
        return Inertia::render('admin/sectors/index', [
            'sectors' => $sectors,
            'mdas' => $mdas, // Pass the formatted list of MDAs
        ]);
    }

    /**
     * Store a newly created Sector in storage.
     */
    public function store(SectorStoreUpdateRequest $request) 
    {
        $this->sectorService->saveOrUpdate($request->validated());
        
        return redirect()->route('sectors.index')
            ->with('message', 'New Sector created successfully.');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SectorStoreUpdateRequest $request, Sector $sector) 
    {
        $this->sectorService->saveOrUpdate($request->validated(), $sector);
        
        return redirect()->route('sectors.index')
            ->with('message', 'Sector updated successfully.');
    }
    
    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Sector $sector)
    {
        $sectorName = $sector->name;
        $sector->delete();
        
        return redirect()->route('sectors.index')
            ->with('message', "Sector '{$sectorName}' deleted successfully.");
    }
}