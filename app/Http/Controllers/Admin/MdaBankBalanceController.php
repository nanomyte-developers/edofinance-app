<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Mda;
use App\Models\Bank;
use App\Http\Requests\StoreMdaBankBalanceRequest;
use App\Models\MdaBankBalance;
use App\Services\MdaBankBalanceService;
use App\Http\Resources\MdaBankBalanceResource;
use Inertia\Inertia;


class MdaBankBalanceController extends Controller
{
    protected $service;

    public function __construct(MdaBankBalanceService $service)
    {
        $this->service = $service;
    }

    /**
     * Display the listing of bank balances.
     */
        public function index()
    {
        // Use the service to get balances with relationships
        $balances = MdaBankBalance::with(['mda', 'bank'])->latest()->paginate(200);
        //dd($balances);

        return Inertia::render('admin/reports/index', [
            // FIX: You must assign the data to the 'balances' key
            'balances' => MdaBankBalanceResource::collection($balances),

            // Fetch MDAs for the dropdown
            'mdas' => Mda::active()->select('id', 'name')->get(),

            // Fetch Banks for the dropdown
            'banks' => Bank::where('status', 1)->select('id', 'name')->get(),
        ]);
    }
    public function store(StoreMdaBankBalanceRequest $request): RedirectResponse
    {
        // Business logic handled by service
        $this->service->storeBalance($request->validated());

        return redirect()->back()->with('message', 'Bank balance recorded successfully.');
    }

    /**
     * Update the specified record.
     */
    public function update(StoreMdaBankBalanceRequest $request, MdaBankBalance $mdaBankBalance): RedirectResponse
    {
        // Update logic handled by service
        $this->service->updateBalance($mdaBankBalance, $request->validated());

        return redirect()->back()->with('message', 'Bank balance updated successfully.');
    }

    /**
     * Remove the specified record.
     */
    public function destroy(MdaBankBalance $mdaBankBalance): RedirectResponse
    {
        $this->service->deleteBalance($mdaBankBalance);

        return redirect()->back()->with('message', 'Record deleted successfully.');
    }
        public function cashAndBankBalanceHeldByMda()
    {
        // Fetch all balances with relationships to ensure names are visible
        $balances = MdaBankBalance::where('id', '<>',0)
            ->orderBy('mda_id')
            ->get();

        // Calculate totals for the footer as seen in the jpeg
        $total2024 = $balances->sum('balance_current_year');
        $total2023 = $balances->sum('balance_previous_year');
        return Inertia::render('admin/reports/CashAndBankBalanceReport', [
            'balances' => MdaBankBalanceResource::collection($balances),
            'totals' => [
                'current' => $total2024,
                'previous' => $total2023
            ]
        ]);
    }
   // app/Http/Controllers/MdaBankBalanceController.php

    // public function getGroupedBalances()
    // {
    //     // Eager load relationships and group by mda_id
    //     $groupedBalances =MdaBankBalance::with(['mda', 'bank'])
    //         ->get()
    //         ->groupBy('mda_id');
    //        // dd($groupedBalances);

    //     return Inertia::render('admin/mdaBankBalances/groupedAccounts', [
    //         'groupedBalances' => $groupedBalances,
    //         'banks' => \App\Models\Bank::all(['id', 'name']),
    //     ]);
    // }
    // app/Http/Controllers/Admin/MdaBankBalanceController.php

    public function getGroupedBalances()
    {
        // 1. Fetch Aggregated Data for the Graph (Summed by MDA and Ordered by Total)
        $chartData = MdaBankBalance::join('mdas', 'mda_bank_balances.mda_id', '=', 'mdas.id')
            ->select(
                'mdas.name as mda_name',
                \DB::raw('SUM(balance_previous_year) as total_prev'),
                \DB::raw('SUM(balance_current_year) as total_curr')
            )
            ->groupBy('mdas.id', 'mdas.name')
            ->orderBy('total_curr', 'desc') // Order by the highest current total
            ->get();

        // 2. Fetch Grouped Data for the Tables (Existing logic)
        $groupedBalances = MdaBankBalance::with(['mda', 'bank'])
            ->get()
            ->groupBy('mda_id');

        return Inertia::render('admin/reports/groupedAccounts', [
            'chartData' => $chartData,
            'groupedBalances' => $groupedBalances,
            'banks' => \App\Models\Bank::all(['id', 'name']),
        ]);
    }
}
