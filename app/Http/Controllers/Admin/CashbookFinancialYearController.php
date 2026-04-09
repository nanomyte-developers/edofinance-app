<?php

namespace App\Http\Controllers\Admin;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\Cashbook;
use Illuminate\Http\Request;
use App\Models\FinancialYear;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\BankActivity;
use App\Models\CashbookFinancialYear;

class CashbookFinancialYearController extends Controller
{
    public function index()
    {
        // Eager load the main financial year so the name is available
        $years = CashbookFinancialYear::with('financialYear') 
                    ->orderBy('created_at', 'desc')
                    ->get();

        return Inertia::render('admin/cashbook/cashbookFinancialYear/index', [
            'years' => $years,
            // Also send the list for the "Create" dropdown
            'globalFinancialYears' => FinancialYear::all() 
        ]);
    }

    // public function store(Request $request)
    // {
    //     $validated = $request->validate([
    //         'name' => 'required|string',
    //         'financial_year_id' => 'required',
    //         'start_date' => 'required',
    //         'end_date' => 'required',
    //         'opening_balance' => 'required|numeric',
    //     ]);

    //     DB::transaction(function () use ($validated, $request) {
    //         // 1. Create the Master Year
    //         $cfy = CashbookFinancialYear::create([
    //             'name' => $validated['name'],
    //             'financial_year_id' => $validated['financial_year_id'],
    //             'start_date' => Carbon::parse($validated['start_date']),
    //             'end_date' => Carbon::parse($validated['end_date']),
    //             'opening_balance' => $validated['opening_balance'],
    //             'is_active' => $request->is_active ?? true,
    //         ]);

    //         // 2. Fetch all active Bank Accounts
    //         $accounts = BankActivity::where('status', 1)->get();

    //         // 3. Get the year from the linked Financial Year name (should be "2025")
    //         $financialYear = FinancialYear::find($validated['financial_year_id']);
    //         $targetYear = now()->year; // Default to current year
            
    //         if ($financialYear && $financialYear->name) {
    //             // Extract year from name - could be "2025", "FY2025", "2024/2025", etc.
    //             if (preg_match('/\b(\d{4})\b/', $financialYear->name, $matches)) {
    //                 $targetYear = (int)$matches[1];
    //             }
    //         }
            
    //         // 4. Loop through each Bank Account
    //         foreach ($accounts as $account) {
    //             // 5. Create exactly 12 Monthly Cards for 2025
    //             for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
    //                 Cashbook::create([
    //                     'cashbook_financial_year_id' => $cfy->id,
    //                     'bank_activities_id' => $account->id,
    //                     'month_id' => $monthNumber, // 1, 2, 3...12
    //                     'year' => $targetYear, // Should be 2025
    //                     'status' => 'open',
    //                     // Only January gets the opening balance
    //                     'opening_balance' => ($monthNumber === 1) ? $cfy->opening_balance : 0,
    //                     'closing_balance' => ($monthNumber === 1) ? $cfy->opening_balance : 0,
    //                 ]);
    //             }
    //         }
    //     });

    //     return back()->with('message', 'Financial Year and all Bank Ledger Cards generated successfully...');
    // }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'financial_year_id' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'opening_balance' => 'required|numeric',
        ]);

        DB::transaction(function () use ($validated, $request) {
            // 1. Get the target year from financial year
            $financialYear = FinancialYear::find($validated['financial_year_id']);
            $targetYear = now()->year;
            
            if ($financialYear && $financialYear->name) {
                if (preg_match('/\b(\d{4})\b/', $financialYear->name, $matches)) {
                    $targetYear = (int)$matches[1];
                }
            }

            // 2. Fetch all opening balances from cash_book_balance_bfws table
            // Join with BankActivity to ensure we only get active accounts
            $openingBalances = DB::table('cash_book_balance_bfws as bfw')
                ->join('bank_activities as ba', 'bfw.bank_activity_id', '=', 'ba.id')
                ->where('ba.status', 1) // Only active bank accounts
                ->where('bfw.financial_year', $targetYear - 1) // Previous year's balances
                ->select('ba.id as bank_activity_id', 'bfw.amount')
                ->get()
                ->keyBy('bank_activity_id');

            // 3. Calculate total opening balance from the joined results
            $totalOpeningBalance = $openingBalances->sum('amount');

            // 4. Create the Master Year with calculated opening balance
            $cfy = CashbookFinancialYear::create([
                'name' => $validated['name'],
                'financial_year_id' => $validated['financial_year_id'],
                'start_date' => Carbon::parse($validated['start_date']),
                'end_date' => Carbon::parse($validated['end_date']),
                'opening_balance' => $totalOpeningBalance,
                'is_active' => $request->is_active ?? true,
            ]);

            // 5. Fetch all active Bank Accounts
            $accounts = BankActivity::where('status', 1)->get();

            // 6. Loop through each Bank Account
            foreach ($accounts as $account) {
                // Get the opening balance for this specific account
                $accountBalance = $openingBalances->get($account->id);
                $openingBalanceAmount = $accountBalance ? $accountBalance->amount : 0;

                // 7. Create exactly 12 Monthly Cards
                for ($monthNumber = 1; $monthNumber <= 12; $monthNumber++) {
                    // Only January gets the opening balance from cash_book_balance_bfws
                    $openingBalanceForMonth = ($monthNumber === 1) ? $openingBalanceAmount : 0;
                    
                    Cashbook::create([
                        'cashbook_financial_year_id' => $cfy->id,
                        'bank_activities_id' => $account->id,
                        'month_id' => $monthNumber,
                        'year' => $targetYear,
                        'status' => 'open',
                        'opening_balance' => $openingBalanceForMonth,
                        'closing_balance' => 0,
                    ]);
                }
            }

            // OPTIONAL: Log or store the mapping for reference
            // DB::table('cashbook_balance_mappings')->insert([
            //     'cashbook_financial_year_id' => $cfy->id,
            //     'total_opening_balance' => $totalOpeningBalance,
            //     'source_table' => 'cash_book_balance_bfws',
            //     'source_year' => $targetYear - 1,
            //     'created_at' => now(),
            //     'updated_at' => now(),
            // ]);
        });

        return back()->with('message', 'Financial Year created with opening balances imported from previous year balances. Total opening balance: ' . number_format($totalOpeningBalance ?? 0, 2));
    }

    public function showMonths(CashbookFinancialYear $cashbook_year)
    {
        $cfy = $cashbook_year->load(['financialYear', 'months' => function($q) {
            $q->orderBy('month_id');
        }]);

        $yearId = $cashbook_year->id;

        $accounts = BankActivity::with(['cashbooks' => function($query) use ($yearId) {

            $query->where('cashbook_financial_year_id', $yearId)
                ->with('month') // Now this will work
                ->orderBy('month_id');
            }])->whereHas('cashbooks', function($query) use ($yearId) {
                $query->where('cashbook_financial_year_id', $yearId);
            })->get();

        // dd($cfy);

        return Inertia::render('admin/cashbook/cashbookFinancialYear/months', [
        'year' => $cfy,
        'accounts' => $accounts
        ]);
    }

    public function showMonthAccounts(CashbookFinancialYear $cashbook_year, $month_id)
    {
        // Cast month_id to integer
        $month_id = (int) $month_id;
        
        // Validate month_id
        if ($month_id < 1 || $month_id > 12) {
            abort(404, 'Invalid month');
        }

        // Get month name
        $monthNames = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
        
        $monthName = $monthNames[$month_id] ?? 'Unknown Month';

        // Get all cashbooks for this month and year
        $cashbooks = Cashbook::with(['bankAccount', 'entries'])
            ->where('cashbook_financial_year_id', $cashbook_year->id)
            ->where('month_id', $month_id)
            ->orderBy('bank_activities_id')
            ->get();

        // Get summary statistics
        $summary = [
            'total_accounts' => $cashbooks->count(),
            'total_opening_balance' => $cashbooks->sum('opening_balance'),
            'total_closing_balance' => $cashbooks->sum('closing_balance'),
            'active_accounts' => $cashbooks->where('status', 'open')->count(),
            'closed_accounts' => $cashbooks->where('status', 'closed')->count(),
            'processed_accounts' => $cashbooks->where('status', 'processed')->count(),
            'open_accounts' => $cashbooks->where('status', 'open')->count(),
        ];

        return Inertia::render('admin/cashbook/cashbookFinancialYear/monthAccounts', [
            'year' => $cashbook_year,
            'month_id' => $month_id, // Now it's an integer
            'month_name' => $monthName,
            'cashbooks' => $cashbooks,
            'summary' => $summary,
        ]);
    }

    public function showLedger(Cashbook $cashbook)
    {
        return Inertia::render('Treasury/CashbookLedger', [
            'cashbook' => $cashbook->load('financialYear'),
            'entries' => $cashbook->entries()
                ->orderBy('transaction_date', 'asc')
                ->get(),
            // Totals for the footer calculation as seen in your image
            'totals' => [
                'receipts' => $cashbook->entries()->where('type', 'receipt')->sum('amount'),
                'payments' => $cashbook->entries()->where('type', 'payment')->sum('amount'),
            ]
        ]);
    }

}
