<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\ProgrammeCode;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;

class ProgrammeCodeController extends Controller
{
    /**
     * Get programme codes for dropdown (searchable)
     */
    public function index(Request $request)
    {
        $query = ProgrammeCode::with(['economicCode', 'parent'])
            ->active()
            ->projects(); // Only get project-level programme codes
        
        // Apply search filter
        if ($request->has('search') && !empty($request->search)) {
            $query->search($request->search);
        }
        
        // Filter by financial year
        if ($request->has('financial_year_id') && $request->financial_year_id) {
            $query->where('financial_year_id', $request->financial_year_id);
        }
        
        // Filter by sector
        if ($request->has('sector') && $request->sector) {
            $query->where('sector', $request->sector);
        }
        
        // Paginate results
        $perPage = $request->get('per_page', 20);
        $programmes = $query->paginate($perPage);
        
        // Format for dropdown
        $formatted = $programmes->map(function ($programme) {
            return [
                'id' => $programme->id,
                'code' => $programme->code,
                'name' => $programme->name,
                'description' => $programme->project_description ?: $programme->name,
                'budget_code' => $programme->budget_code,
                'approved_budget' => (float) $programme->approved_budget,
                'remaining_budget' => (float) $programme->remaining_budget,
                'economic_code_id' => $programme->economic_code_id,
                'economic_code' => $programme->economicCode ? [
                    'id' => $programme->economicCode->id,
                    'code' => $programme->economicCode->code,
                    'name' => $programme->economicCode->name,
                ] : null,
                'mda_name' => $programme->mda_name,
                'sector' => $programme->sector,
                'label' => "{$programme->code} - {$programme->name} (Budget: ₦" . number_format($programme->remaining_budget, 2) . ")",
                'value' => $programme->id,
            ];
        });
        
        return response()->json([
            'data' => $formatted,
            'total' => $programmes->total(),
            'current_page' => $programmes->currentPage(),
            'last_page' => $programmes->lastPage(),
            'per_page' => $programmes->perPage(),
        ]);
    }
    
    /**
     * Search programme codes (autocomplete)
     */
    public function search(Request $request)
    {
        $request->validate([
            'q' => 'nullable|string|min:2',
            'financial_year_id' => 'nullable|exists:financial_years,id',
        ]);
        
        $query = ProgrammeCode::with('economicCode')
            ->active()
            ->projects();
        
        if ($request->has('q') && !empty($request->q)) {
            $query->search($request->q);
        }
        
        if ($request->has('financial_year_id') && $request->financial_year_id) {
            $query->where('financial_year_id', $request->financial_year_id);
        }
        
        $programmes = $query->limit(20)->get();
        
        return response()->json(
            $programmes->map(function ($programme) {
                return [
                    'id' => $programme->id,
                    'code' => $programme->code,
                    'name' => $programme->name,
                    'budget_code' => $programme->budget_code,
                    'remaining_budget' => (float) $programme->remaining_budget,
                    'economic_code_id' => $programme->economic_code_id,
                    'economic_code_code' => $programme->economicCode?->code,
                    'economic_code_name' => $programme->economicCode?->name,
                    'display_text' => "{$programme->code} - {$programme->name}",
                    'detail_text' => "Budget Code: {$programme->budget_code} | Remaining: ₦" . number_format($programme->remaining_budget, 2),
                ];
            })
        );
    }
    
    /**
     * Get single programme code
     */
    public function show($id)
    {
        $programme = ProgrammeCode::with(['economicCode', 'parent', 'children'])
            ->findOrFail($id);
        
        return response()->json([
            'id' => $programme->id,
            'code' => $programme->code,
            'name' => $programme->name,
            'description' => $programme->project_description ?: $programme->name,
            'budget_code' => $programme->budget_code,
            'approved_budget' => (float) $programme->approved_budget,
            'utilized_budget' => (float) $programme->utilized_budget,
            'remaining_budget' => (float) $programme->remaining_budget,
            'economic_code' => $programme->economicCode ? [
                'id' => $programme->economicCode->id,
                'code' => $programme->economicCode->code,
                'name' => $programme->economicCode->name,
            ] : null,
            'mda_name' => $programme->mda_name,
            'sector' => $programme->sector,
        ]);
    }
    
    /**
     * Get sectors for filtering
     */
    public function getSectors()
    {
        $sectors = ProgrammeCode::select('sector')
            ->whereNotNull('sector')
            ->distinct()
            ->pluck('sector');
        
        return response()->json($sectors);
    }
    
    /**
     * Get available budget for a programme
     */
    public function getBudget($id)
    {
        $programme = ProgrammeCode::findOrFail($id);
        
        return response()->json([
            'id' => $programme->id,
            'code' => $programme->code,
            'name' => $programme->name,
            'remaining_budget' => (float) $programme->remaining_budget,
            'approved_budget' => (float) $programme->approved_budget,
            'utilized_budget' => (float) $programme->utilized_budget,
            'budget_code' => $programme->budget_code,
        ]);
    }
}
