<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mda;
use App\Models\AdministrativeCode;
use App\Models\AdministrativeSectorCode;
use Inertia\Inertia;
use App\Services\MdaService;
use Illuminate\Http\Request;
use App\Http\Requests\MdaStoreUpdateRequest;

class MdaController extends Controller
{
    protected MdaService $mdaService;

    /**
     * Constructor to inject the MdaService dependency.
     */
    public function __construct(MdaService $mdaService)
    {
        $this->mdaService = $mdaService;
    }

    /**
     * Display a listing of the resource.
     */
    // public function index()
    // {
    //     // Fetch and paginate MDAs with administrative code and its sectors
    //     $mdas = MDA::with(['administrativeCode', 'administrativeCode.sectorCodes'])
    //         ->select('id', 'name', 'code', 'initials', 'location', 'status', 'administrative_code_id')
    //         ->latest('name')
    //         ->paginate(15);

    //     // Get all administrative codes and sectors for dropdowns
    //     $administrativeCodes = AdministrativeCode::with('sectorCodes')->active()->get();
    //     $administrativeSectors = AdministrativeSectorCode::with('administrativeCode')->active()->get();

    //     // Render the Inertia component
    //     return Inertia::render('admin/mdas/index', [
    //         'mdas' => $mdas,
    //         'administrativeCodes' => $administrativeCodes,
    //         'administrativeSectors' => $administrativeSectors,
    //     ]);
    // }

    public function index()
    {
        // Fetch and paginate MDAs with administrative code and its sectors
        $mdas = Mda::with(['administrativeCode', 'administrativeCode.sectorCodes'])
            ->select('id', 'name', 'code', 'initials', 'location', 'status', 'administrative_code_id', 'type')
            ->latest('name')
            ->paginate(500);

        // Get all administrative codes and sectors for dropdowns
        $administrativeCodes = AdministrativeCode::with('sectorCodes')->active()->get();
        $administrativeSectors = AdministrativeSectorCode::with('administrativeCode')->active()->get();

        // Render the Inertia component
        return Inertia::render('admin/mdas/index', [
            'mdas' => $mdas,
            'administrativeCodes' => $administrativeCodes,
            'administrativeSectors' => $administrativeSectors,
        ]);
    }

    /**
     * Store a newly created MDA in storage.
     */
    public function store(MdaStoreUpdateRequest $request)
    { 
        $data = $request->validated();
        
        $mda = $this->mdaService->saveOrUpdate($data);

        return redirect()->route('mdas.index')
            ->with('message', "MDA '{$mda->name}' created successfully.");
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(MdaStoreUpdateRequest $request, Mda $mda)
    {
        $data = $request->validated();
        
        $mda = $this->mdaService->saveOrUpdate($data, $mda);

        return redirect()->route('mdas.index')
            ->with('message', "MDA '{$mda->name}' updated successfully.");
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mda $mda)
    {
        $mdaName = $mda->name;
        $mda->delete();

        return redirect()->route('mdas.index')
            ->with('message', "MDA '{$mdaName}' deleted successfully.");
    }

    /**
     * Fetch sectors associated with a specific MDA.
     */
    public function fetchSectors(MDA $mda)
    {
        $sectors = $mda->administrativeSectorCodes()
                    ->select('id', 'name', 'code', 'description', 'status')
                    ->get();

        return response()->json([
            'sectors' => $sectors,
            'mda_name' => $mda->name,
            'administrative_code' => $mda->administrativeCode ? $mda->administrativeCode->code : null,
        ]);
    }

    /**
     * Assign administrative sectors to MDA
     */
    public function assignSectors(Request $request, MDA $mda)
    {
        $request->validate([
            'sector_ids' => 'required|array',
            'sector_ids.*' => 'exists:administrative_sector_codes,id',
        ]);

        // Check if MDA has administrative code
        if (!$mda->administrative_code_id) {
            return response()->json([
                'message' => 'Please assign an administrative code to the MDA first.',
                'success' => false
            ], 400);
        }

        // Verify all sectors belong to the same administrative code
        $invalidSectors = AdministrativeSectorCode::whereIn('id', $request->sector_ids)
            ->where('administrative_code_id', '!=', $mda->administrative_code_id)
            ->exists();

        if ($invalidSectors) {
            return response()->json([
                'message' => 'All sectors must belong to the same administrative code as the MDA.',
                'success' => false
            ], 400);
        }

        // Update sector assignments (you might need a pivot table if many-to-many)
        // For now, sectors are linked through administrative_code_id
        
        return response()->json([
            'message' => 'Sectors assigned successfully.',
            'success' => true,
            'sectors_count' => count($request->sector_ids)
        ]);
    }

    /**
     * Fetch administrative sector details for an MDA
     */
    public function fetchAdminSectorDetails(MDA $mda)
    {
        $adminSectorDetails = null;
        
        if ($mda->administrativeCode) {
            $adminSectorDetails = [
                'administrative_code' => $mda->administrativeCode,
                'sectors' => $mda->administrativeCode->sectorCodes()->get(),
                'sector_count' => $mda->administrativeCode->sectorCodes()->count(),
            ];
        }

        return response()->json([
            'admin_sector_details' => $adminSectorDetails,
            'mda_name' => $mda->name,
        ]);
    }
}