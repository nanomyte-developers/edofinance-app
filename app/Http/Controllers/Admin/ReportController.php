<?php
// app/Http/Controllers/Api/ActivityStatsController.php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\BankActivity;
use App\Models\CashBookBalanceBfw;
use App\Models\EconomyCode;
use App\Models\EconomyCodeItem;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\Mda;
use App\Models\EconomicCodeBalance;
use App\Models\RetirementVoucher;
use App\Models\RetirementItem;
use App\Models\Journal;
use App\Models\JournalEntry;
use Exception;

class ReportController extends Controller
{

    function get_month_dates($date_string = 'now')
    {
        $date = new \DateTime($date_string);

        // Get the first day
        $first_day = (clone $date)->modify('first day of this month')->format('Y-m-d');

        // Get the last day
        $last_day = (clone $date)->modify('last day of this month')->format('Y-m-d');

        return [
            'first_day' => $first_day,
            'last_day' => $last_day
        ];
    }

    function get_months_between_dates($startDate, $endDate)
    {
        // Create DateTime objects from the input dates
        // The date strings can be in various formats that PHP's DateTime understands
        $date1 = new \DateTime($startDate);
        $date2 = new \DateTime($endDate);

        // Calculate the difference between the two dates
        // Passing 'true' to diff() ensures a positive interval, regardless of date order
        $interval = $date1->diff($date2, true);

        // Calculate the total months: years * 12 + months
        $totalMonths = ($interval->y * 12) + $interval->m;

        return $totalMonths;
    }

    function getFirstDaysOfNextMonthsBetweenDates($startDateString, $endDateString)
    {
        $firstDays = [];

        // Create DateTime objects for start and end dates
        $startDate = new \DateTime($startDateString);
        $endDate = new \DateTime($endDateString);

        // Initial check: if start date is already the first of the month, the 'first day of next month' 
        // logic will give the desired first *subsequent* first day correctly.

        // Set up DatePeriod to iterate monthly. We add one day to the end date 
        // in the DatePeriod creation so the final month is correctly included in the loop
        // if the end date is not exactly the last day of the month.
        $interval = \DateInterval::createFromDateString('first day of next month');
        $period = new \DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

        foreach ($period as $date) {
            // The date object from the loop is already the 'first day of next month' relative to the previous
            // iteration's date (or the start date).
            // Ensure the generated date is not after the original end date.
            if ($date < $endDate) {
                $firstDays[] = $date->format('Y-m-d');
            }
        }

        return $firstDays;
    }


    public function index()
    {

        return Inertia::render('admin/reports/trialbalanceControl', [
            'csrfToken' => csrf_token()
        ]);
    }
    public function trialBalance(Request $request)
    {

        $month_start = '2025-01-01';
        $month_end = $request->end_date;
        $arrLineItems = [];

        // $month_start = $request->month_start;
        // $month_end = $request->month_end;


        // $dateRange = $this->getFirstDaysOfNextMonthsBetweenDates($month_start, $month_end);

        // foreach ($dateRange as $key => $date) {
        //     $sDate = $this->get_month_dates($date);

        //     $s_month = $sDate['first_day'];
        //     $e_month = $sDate['last_day'];

        // We query VoucherItem directly and filter based on the parent Voucher's date
        $voucherItems = VoucherItem::whereHas('voucher', function ($query) use ($month_start, $month_end) {
            $query->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted');
        })
            ->get();

        $receipts = Receipt::whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->whereNotNull('account_number')->orderBy('eco_code_item', 'asc')->get();
        $remittances = Remittance::whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();

        // Fetch all economy code items once
        $allEconomyCodes = EconomyCodeItem::all()->keyBy('code');

        // Fetch all bank activities for receipts and remittances
        $receiptAccountNumbers = $receipts->pluck('account_number')->unique();
        $bankActivitiesByAccountNumber = BankActivity::whereIn('account_number', $receiptAccountNumbers)->get()->keyBy('account_number');

        $remittanceDestinationBankIds = $remittances->pluck('destination_bank_id')->unique();
        // $bankActivitiesRemittance = BankActivity::whereIn('id', $remittanceDestinationBankIds)->get()->keyBy('id');

        // Fetch voucher items with related voucher and economy code item in one go
        $voucherIds = VoucherItem::whereHas('voucher', function ($q) use ($month_start, $month_end) {
            $q->whereBetween('voucher_date', [$month_start, $month_end])
                ->where('status', 'Submitted');
        })->pluck('voucher_id')->unique();

        $voucherItems = VoucherItem::with('voucher', 'economyCodeItem')->whereIn('voucher_id', $voucherIds)->get();

        // Map voucher items by their economic code
        $voucherItemsByEcoCode = $voucherItems->groupBy(function ($vi) {
            return $vi->economyCodeItem->code ?? '';
        });

        // Prepare a map for economy code description
        $ecoCodeNames = EconomyCodeItem::pluck('name', 'code')->toArray();

        // Initialize balances
        $AccountOpeningBalances = [];
        foreach ($remittances as $remittance) {
            $accountId = $remittance->destination_bank_id;
            $amount = $remittance->amount;
            if (isset($AccountOpeningBalances[$accountId])) {
                $AccountOpeningBalances[$accountId]['amount'] += $amount;
            } else {
                $AccountOpeningBalances[$accountId] = ['account_id' => $accountId, 'amount' => $amount];
            }
        }

        // Extract unique economic codes from receipts
        $economicCodes = array_unique($receipts->pluck('eco_code_item')->toArray());

        // Prepare a collection for line items
        $arrLineItems = [];

        // Preload all economy code item details
        $ecoCodeDetails = EconomyCodeItem::whereIn('code', $economicCodes)->get()->keyBy('code');

        foreach ($economicCodes as $EconomicCode) {
            $economicReceipts = $receipts->where('eco_code_item', $EconomicCode);

            // Get related bank activity IDs for receipts
            $bankIds = $economicReceipts->pluck('account_number')->map(function ($acc) use ($bankActivitiesByAccountNumber) {
                return $bankActivitiesByAccountNumber[$acc]->id ?? null;
            })->filter()->unique();

            // Sum opening balances
            $OpenningBalances = CashBookBalanceBfw::whereIn('bank_activity_id', $bankIds)
                ->where('financial_year', 2024)
                ->sum('amount');

            // Sum remittances
            $totalRemitances = $remittances->whereIn('destination_bank_id', $bankIds)->sum('amount');

            $description = $ecoCodeDetails[$EconomicCode]->name ?? '';

            $arrLineItems[] = [
                'economic_code' => $EconomicCode,
                'openning_balance' => 0.00, // Assuming placeholder
                'debits' => 0.00, // Placeholder, calculation below
                'credits' => $economicReceipts->sum('amount'),
                'closing_balance' => $OpenningBalances + $economicReceipts->sum('amount'),
                'description' => $description,
                'start_date' => $month_start,
                'end_date' => $month_end,
                'type' => 'receipt',
            ];
        }

        // Process voucher items related to economic codes
        $vEconomicCodes = $voucherItems->pluck('economyCodeItem.code')->unique()->toArray();
        sort($vEconomicCodes);

        $AllbankIds = [];

        foreach ($vEconomicCodes as $vEconomicCode) {
            if (substr($vEconomicCode, 0, 1) == '2') {
                $ecoItem = $allEconomyCodes[$vEconomicCode] ?? null;
                if (!$ecoItem) continue;

                $economicVoucherItems = $voucherItems->filter(function ($vi) use ($ecoItem) {
                    return $vi->economyCodeItem->id == $ecoItem->id;
                });

                $voucherIds = $economicVoucherItems->pluck('voucher_id')->unique();

                $vouchers = Voucher::with('bankActivity')->whereIn('id', $voucherIds)->get();

                // Get bank activity IDs from vouchers
                $bankIds = $vouchers->pluck('bank_activity_id')->unique();

                // Get related bank activities for remittance inflows
                $BankActivitiesInflows = $remittances->whereIn('destination_bank_id', $bankIds)->sum('amount');

                // Initialize sums
                $sumCredits = 0;
                $sumDebits = 0;
                $activityIds = [];

                foreach ($vouchers as $voucher) {
                    $bankActId = $voucher->bank_activity_id;

                    if (isset($AccountOpeningBalances[$bankActId]) && !empty($AccountOpeningBalances[$bankActId])) {
                        if (!in_array($bankActId, $activityIds)) {
                            $activityIds[] = $bankActId;
                            $sumCredits += $AccountOpeningBalances[$bankActId]['amount'];
                        }
                        // Deduct voucher total amount from account balance
                        $AccountOpeningBalances[$bankActId]['amount'] -= $voucher->total_amount;
                        $sumDebits += $voucher->total_amount;
                    }
                }

                // $description = $ecoCodeDetails[$vEconomicCode]->name ?? '';
                $description = $allEconomyCodes[$vEconomicCode]->name ?? '';

                $arrLineItems[] = [
                    'economic_code' => $vEconomicCode,
                    'openning_balance' => 0,
                    'debits' => $sumDebits,
                    'credits' => 0,
                    'closing_balance' => 0 - $sumDebits,
                    'description' => $description,
                    'bank_ids' => $bankIds,
                    'voucher_ids' => $voucherIds,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'voucher'
                ];
            }
        }
        $OpenningBalancez = CashBookBalanceBfw::get();
        foreach ($OpenningBalancez as $OpeningBalance) {
            $voucherDebits = Voucher::where('bank_activity_id', $OpeningBalance->bank_activity_id)->whereBetween('voucher_date', [$month_start, $month_end])->sum('total_amount');
            $remitanceCredits = $remittances->where('destination_bank_id', $OpeningBalance->bank_activity_id)->sum('amount');
            $arrLineItems[] = [
                'economic_code' => $OpeningBalance->economic_code,
                'openning_balance' => floatVal($OpeningBalance->amount),
                'debits' => $voucherDebits,
                'credits' => $remitanceCredits,
                'closing_balance' =>  $OpeningBalance->amount + $remitanceCredits - $voucherDebits,
                'description' => $OpeningBalance->bankActivity->bank_name . '_' . $OpeningBalance->bankActivity->title . '_' . $OpeningBalance->bankActivity->account_number,
                'start_date' => $month_start,
                'end_date' => $month_end,
                'type' => 'bank '
            ];
        }

        // dd($AccountOpeningBalancesCopy, $AccountOpeningBalances, $AllbankIds);

        // }

        // dd($arrLineItems);


        // $arrayLines = [];

        // 1130004993


        // dd($receipts->toArray(), $remittances->toArray());
        return Inertia::render('admin/reports/trialbalance', [

            'data' => $arrLineItems,

        ]);
    }


    public function newTrialBalance(Request $request)
    {
        // dd($request->all());

        if (preg_match('/^(\d{1,2})\/(\d{4})$/', $request->yearMonth, $matches)) {
            // Format: MM/YYYY
            $month = intval($matches[1]);
            $year = intval($matches[2]);
        } elseif (preg_match('/^([a-zA-Z]+)\/(\d{4})$/', $request->yearMonth, $matches)) {
            // Format: MonthName/YYYY
            $monthName = ucfirst(strtolower($matches[1]));
            $year = intval($matches[2]);
            $month = Carbon::parse($monthName)->month;
        } else {
            // Handle invalid format
            throw new Exception('Invalid date format');
        }

        // Create Carbon date for the first day of the month
        $date = Carbon::create($year, $month, 1);

        // Get last day of the month
        $lastDayOfMonth = $date->endOfMonth()->format('Y-m-d');

        $month_start = '2025-01-01';
        $month_end = $lastDayOfMonth ?? '2025-03-31';
        $arrLineItems = [];

        $salaryECI = [

            '41030101',
            '41030102',
            '41030103',
            '41030202',
            '41030203',
            '41030204',
            '41030205',
            '41030206',
            '41030208',
            '41030211',
            '41030214',
            '41030216',
            '41030319',
            '41040101',
        ];

        $economicCodeItems = EconomyCodeItem::where('status', 'active')->orderBy('code')->get();

        foreach ($economicCodeItems as $economicCodeItem) {
            $EconomicCode = $economicCodeItem->code;
            if (substr($EconomicCode, 0, 1) == '1') {
                //  revenues 
                $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();

                $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$month_start, $month_end])->where('status', 'approved')->get();
                $journalCredits = 0.00;
                $journalDebits = 0.00;
                foreach ($journals as $journal) {
                    // dd($journal->entries);
                    $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                    $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                }
                // $remitances = Remittance::where()
                $arrLineItems[] = [
                    'economic_code' => $EconomicCode,
                    'openning_balance' => 0,
                    'debits' => $journalDebits,
                    'credits' => $receipts->sum('amount') + $journalCredits,
                    'closing_balance' => $journalDebits - ($receipts->sum('amount') + $journalCredits),
                    'description' => $economicCodeItem->name,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'receipt',
                    'journal_ids' => $journals->pluck('id')->toArray(),

                ];
            } elseif (substr($EconomicCode, 0, 1) == '2') {
                // expenses

                $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');


                $vouchers = Voucher::whereIn('id', $voucherIds)->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {

                    $query->where('status', 'Submitted')
                        ->orWhere('status', 'Approved');
                })->get();

                $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $receiptsSum = $receipts->sum('amount') ?? 0.00;
                // if( in_array( 9805, $vouchers->pluck('id')->toArray()) ){
                //     dd($vouchers->toArray());

                // }


                // $voucherRetirements = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id'))->get();

                $retirementItems = RetirementItem::where('economic_code_item_id', $economicCodeItem->id)->get();

                $sumRetirement = $retirementItems->sum('sub_total') ?? 0.00;

                // if($sumRetirement > 0){
                //     dd($retirementItems->toArray());
                // }


                $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$month_start, $month_end])->where('status', 'approved')->get();
                $journalCredits = 0.00;
                $journalDebits = 0.00;
                foreach ($journals as $journal) {
                    // dd($journal->entries);
                    $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                    $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                }


                $arrLineItems[] = [
                    'economic_code' => $EconomicCode,
                    'openning_balance' => 0,
                    'debits' => $vouchers->sum('total_amount')  + $sumRetirement + $journalDebits,
                    'credits' => $journalCredits + $receiptsSum,
                    'closing_balance' => (0 + $vouchers->sum('total_amount') + $sumRetirement + $journalDebits) - ($journalCredits + $receiptsSum),
                    'description' => $economicCodeItem->name,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'voucher',
                    'voucher_ids' => $vouchers->pluck('id')->toArray(),
                    'journal_ids' => $journals->pluck('id')->toArray(),
                ];
            } elseif (substr($EconomicCode, 0, 1) == '3') {


                $bank_activity = BankActivity::where('economic_code', $EconomicCode)->first();

                // if ($EconomicCode == '31010650') {
                //     dd($bank_activity);

                // }

                if (!empty($bank_activity)) {


                    $bank_activity_id = $bank_activity->id;

                    $receipts = Receipt::where('account_number', $bank_activity->account_number)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $OpeningBalance = CashBookBalanceBfw::where('economic_code', $EconomicCode)->first();
                    $DebitRemittances = Remittance::where('destination_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $CreditRemittances  = Remittance::where('source_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $CreditVouchers = Voucher::where('bank_activity_id', $bank_activity_id)->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {
                        $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                    })->get();


                    $bCredits = $CreditRemittances->sum('amount') + $CreditVouchers->sum('total_amount');;
                    $bDebits =   $receipts->sum('amount') + $DebitRemittances->sum('amount');
                    $bOpening_balance = $OpeningBalance->amount ?? 0.00;
                    $arrLineItems[] = [
                        'economic_code' => $EconomicCode,
                        'openning_balance' => floatVal($bOpening_balance),
                        'debits' => $bDebits,
                        'credits' => $bCredits,
                        'closing_balance' => $bOpening_balance + $bDebits - $bCredits,
                        'description' => $economicCodeItem->name,
                        'start_date' => $month_start,
                        'end_date' => $month_end,
                        'type' => 'bank',
                        'account_number' => $bank_activity->account_number,
                        'bank_activity_id' => $bank_activity_id,
                        'opening_balance' => $bOpening_balance->amount ?? 0
                    ];
                } else {
                    $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                    $vouchers = Voucher::whereIn('id', $voucherIds->toArray())->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {
                        $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                    })->get();


                    // dd($vouchers);
                    $retirementVouchers = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id')->toArray())->get();
                    // if ( $economicCodeItem->code == 31080101) {
                    //     dd($retirementVouchers, $vouchers);
                    // }

                    $CreditVouchers = $retirementVouchers->sum('retired_amount') ?? 0.00;

                    $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->first();
                    $opningBalance = $economicCodeBalance->amount ?? 0.00;

                    $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                    $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$month_start, $month_end])->where('status', 'approved')->get();
                    $journalCredits = 0.00;
                    $journalDebits = 0.00;
                    foreach ($journals as $journal) {
                        // dd($journal->entries);
                        $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                        $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                    }
                    $arrLineItems[] = [
                        'economic_code' => $EconomicCode,
                        'openning_balance' => floatVal($opningBalance),
                        'debits' => $vouchers->sum('total_amount') + $journalDebits,
                        'credits' => $CreditVouchers + $journalCredits,
                        'closing_balance' => ($opningBalance + $vouchers->sum('total_amount') + $journalDebits) - ($CreditVouchers + $journalCredits),
                        'description' => $economicCodeItem->name,
                        'start_date' => $month_start,
                        'end_date' => $month_end,
                        'type' => 'voucher',
                        'voucher_ids' => $vouchers->pluck('id')->toArray(),
                        'retirmentIds' => $retirementVouchers->pluck('id')->toArray(),
                        'journal_ids' => $journals->pluck('id')->toArray(),

                    ];
                }
                // } elseif (substr($EconomicCode, 0, 1) == '4' && in_array($EconomicCode, $salaryECI)) {
            } elseif (substr($EconomicCode, 0, 1) == '4') {

                $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                $vouchers = Voucher::whereIn('id', $voucherIds)->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->where('financial_year', 2024)->first();
                $opningBalance = $economicCodeBalance->amount ?? 0.00;

                // $journals = Journal::whereHas( 'entries', function ($query) use ($EconomicCode) {
                //     $query->where('account_code', $EconomicCode);
                // dd($query->get());
                // } )->whereBetween('journal_date', [$month_start, $month_end])->where('status', 'approved')->get();

                $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$month_start, $month_end])->where('status', 'approved')->get();
                $journalCredits = 0.00;
                $journalDebits = 0.00;
                foreach ($journals as $journal) {
                    // dd($journal->entries);
                    $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                    $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                }

                // if ($EconomicCode == '41010103') {
                //     dd($journalIds, $journalDebits, $journalCredits);
                // }
                $arrLineItems[] = [
                    'economic_code' => $EconomicCode,
                    'openning_balance' =>  floatVal($opningBalance),
                    'debits' => $vouchers->sum('total_amount') + $journalDebits,
                    'credits' => $journalCredits,
                    'closing_balance' => ($opningBalance + $vouchers->sum('total_amount') + $journalDebits) - $journalCredits,
                    'description' => $economicCodeItem->name,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'voucher',
                    'voucher_ids' => $vouchers->pluck('id')->toArray(),
                    'journal_ids' => $journals->pluck('id')->toArray(),
                ];




                // if ($journals->count() > 0) {
                //     $arrLineItems[] = [
                //         'economic_code' => $EconomicCode,
                //         'openning_balance' => 0.00,
                //         'debits' => 0.00,
                //         'credits' => $journals->where()->sum('total_amount'),
                //         'closing_balance' => $opningBalance - $journals->sum('total_amount'),
                //         'description' => $economicCodeItem->name,
                //         'start_date' => $month_start,
                //         'end_date' => $month_end,
                //         'type' => 'journal',
                //         'journal_ids' => $journals->pluck('id')->toArray()
                //     ];
                //}
            }
        }

        return Inertia::render('admin/reports/trialbalance', [

            'data' => $arrLineItems,

        ]);
    }


    public function trialBalanceDetails(Request $request)
    {
        $request->validate([
            'economic_code' => 'required',
            'type' => 'required',
            'start_date' => 'required',
            'end_date' => 'required',
            'voucher_ids' => 'sometimes',
            'account_number' => 'sometimes',
            'bank_activity_id' => 'sometimes',
            'opening_balance' => 'sometimes',
            'journal_ids' => 'sometimes'
        ]);

        $LedgerTitle = "";
        $LedgerEconomicCode = "";
        $LedgerEconomicCodeItem = "";

        // dd($request->all());
        $type = $request->type;
        $economic_code = $request->economic_code;
        $startDate = $request->start_date;
        $endDate = $request->end_date;
        $data = [];
        $eCodeItem = EconomyCodeItem::whereCode($economic_code)->first();

        // dd($eCodeItem);
        $LedgerTitle = $eCodeItem->economyCode->name . "/" . $eCodeItem->name;
        $LedgerEconomicCode = $eCodeItem->economyCode->code;
        $LedgerEconomicCodeItem = $economic_code;

        // dd($LedgerTitle, $LedgerEconomicCode, $LedgerEconomicCodeItem);



        if ($type == 'bank') {

            // dd($request->all());
            // $bankActId = CashBookBalanceBfw::where('economic_code', $economic_code)->first();
            //  dd($bankActId);
            // if (!empty($bankActId)) {
            //     $bankActId = BankActivity::find($bankActId->bank_activity_id);
            // } else

            if (!empty($request->account_number)) {
                $bankActId = BankActivity::where('account_number', $request->account_number)->first();
            } else {
                $bankActId = BankActivity::find($request->bank_activity_id);
            }
            // dd($bankActId);

            // $remittances = Remittance::with('destinationBank')->where('source_bank_id', $bank->id)->whereBetween('transfer_date', [$startDate, $endDate])->where('status',  'Submitted')->get();
            $vouchers = Voucher::with('bankActivity')->where('bank_activity_id', $bankActId?->id)->whereBetween('voucher_date', [$startDate, $endDate])->where(function ($query) use ($eCodeItem) {
                $query->where('status', 'Submitted')->orWhere('status', 'Approved');
            })->get();

            // dd($vouchers->toArray());
            $receipts = Receipt::where('account_number', $bankActId->account_number)->whereBetween('receipt_date', [$startDate, $endDate])->where('status', 'Submitted')->get();
            $DebitRemittances = Remittance::where('destination_bank_id', $bankActId->id)->whereBetween('transfer_date', [$startDate, $endDate])->where('status', 'Submitted')->get();
            $CreditRemittances = Remittance::where('source_bank_id', $bankActId->id)->whereBetween('transfer_date', [$startDate, $endDate])->where('status', 'Submitted')->get();


            // dd($receipts->toArray(), $CreditRemittances->toArray(), $DebitRemittances->toArray(), $vouchers->toArray());

            // dd($vouchers->toArray());



            foreach ($DebitRemittances  as  $remittance) {

                $data[] = [
                    'credit' => 0,
                    'debit' => $remittance->amount,
                    'description' => $remittance->narration,
                    'reference' => $remittance->receipt_number,
                    'date' => $remittance->transfer_date,
                    'type' => 'remittance',
                    'id' => $remittance->id
                ];
            }

            foreach ($CreditRemittances as $remittance) {

                $data[] = [
                    'credit' => $remittance->amount,
                    'debit' => 0,
                    'description' => $remittance->narration,
                    'reference' => $remittance->receipt_number,
                    'date' => $remittance->transfer_date,
                    'type' => 'remittance',
                    'id' => $remittance->id
                ];
            }


            foreach ($vouchers as  $voucher) {

                $data[] = [
                    'credit' => $voucher->total_amount,
                    'debit' => 0.00,
                    // 'description' => $voucher->bankActivity->bank_name . '_' . $voucher->bankActivity->title . '_' . $voucher->bankActivity->account_number,
                    'description' => strtoupper($voucher->narration),
                    'reference' => $voucher->voucher_number,
                    'date' => $voucher->voucher_date,
                    'type' => 'voucher',
                    'id' => $voucher->id
                ];
            }

            foreach ($receipts as $receipt) {

                $data[] = [
                    'credit' => 0,
                    'debit' => $receipt->amount,
                    // 'description' => $receipt->bank_name . '_' . $receipt->title . '_' . $receipt->account_number,
                    'description' => strtoupper($receipt->activity),
                    'reference' => $receipt->receipt_number,
                    'date' => $receipt->receipt_date,
                    'type' => 'receipt',
                    'id' => $receipt->id
                ];
            }
        } elseif ($type == 'receipt') {
            $receipts = Receipt::where('eco_code_item', $economic_code)->whereBetween('receipt_date', [$startDate, $endDate])->where('status', 'Submitted')->get();


            // dd($receipts->toArray());
            foreach ($receipts as $receipt) {
                $data[] = [
                    'credit' => $receipt->amount,
                    'debit' => 0.00,
                    // 'description' => $receipt->bank_name . '_' . $receipt->title . '_' . $receipt->account_number,
                    'description' => strtoupper($receipt->activity),
                    'reference' => $receipt->receipt_number,
                    'date' => $receipt->receipt_date,
                    'type' => 'receipt',
                    'id' => $receipt->id
                ];
            }
        } elseif ($type == 'voucher') {
            // $economicVoucherItems = VoucherItem::where('economy_code_item_id', $economic_code)->get();

            // $voucherItems = VoucherItem::whereIn('id', $request->voucher_ids)->get();

            //  dd($request->voucher_ids , $voucherItems->toArray());

            $vouchers = Voucher::whereIn('id', $request->voucher_ids)->get();
            // $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->first();
            // $opningBalance = $economicCodeBalance->amount ?? 0.00;

            // $voucherIds = $vouchers->pluck('id')->toArray();

            $retirementVouchers = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id')->toArray())->where('status', 'submitted')->get();

            // if (count($retirementVouchers) > 0) {
            //     dd($request->voucher_ids);
            // }

            $retirementVoucherItems = RetirementItem::where('economic_code_item_id', $eCodeItem->id)->get();
            // $CreditVouchers = $retirementVouchers->sum('retired_amount') ?? 0.00;
            // dd($request->voucher_ids, $retirementVouchers->toArray());
            foreach ($vouchers as $voucher) {
                $data[] = [
                    'credit' => 0.00,
                    'debit' => $voucher->total_amount,
                    // 'description' => $voucher->bankActivity->bank_name . '_' . $voucher->bankActivity->title . '_' . $voucher->bankActivity->account_number,
                    'description' => strtoupper($voucher->narration),
                    'reference' => $voucher->voucher_number,
                    'date' => $voucher->voucher_date,
                    'type' => 'voucher',
                    'id' => $voucher->id
                ];
            }


            $receipts = Receipt::where('eco_code_item', $economic_code)->whereBetween('receipt_date', [$startDate, $endDate])->where('status', 'Submitted')->get();


            // dd($receipts->toArray());
            foreach ($receipts as $receipt) {
                $data[] = [
                    'credit' => $receipt->amount,
                    'debit' => 0.00,
                    // 'description' => $receipt->bank_name . '_' . $receipt->title . '_' . $receipt->account_number,
                    'description' => strtoupper($receipt->activity),
                    'reference' => $receipt->receipt_number,
                    'date' => $receipt->receipt_date,
                    'type' => 'receipt',
                    'id' => $receipt->id
                ];
            }



            if (substr($eCodeItem->code, 0, 1) == '2') {

                // dd($retirementVoucherItems);
                foreach ($retirementVoucherItems as $item) {
                    $data[] = [
                        'credit' => 0.00,
                        'debit' => $item->sub_total,
                        // 'description' => $voucher->bankActivity->bank_name . '_' . $voucher->bankActivity->title . '_' . $voucher->bankActivity->account_number,
                        'description' => strtoupper($item->description),
                        'reference' => $item->retirementVoucher->originalVoucher->voucher_number,
                        'date' => $item->retirementVoucher->originalVoucher->voucher_date,
                        'type' => 'voucher',
                        'id' => $item->retirementVoucher->originalVoucher->id
                    ];
                }
            }

            if (substr($eCodeItem->code, 0, 1) == '3') {

                foreach ($retirementVouchers as $retirementVoucher) {
                    $data[] = [
                        'credit' => $retirementVoucher->retired_amount,
                        'debit' => 0.00,
                        // 'description' => $retirementVoucher->bankActivity->bank_name . '_' . $retirementVoucher->bankActivity->title . '_' . $retirementVoucher->bankActivity->account_number,
                        'description' => strtoupper($retirementVoucher->originalVoucher->narration),
                        'reference' => $retirementVoucher->originalVoucher->voucher_number,
                        'date' => $retirementVoucher->originalVoucher->voucher_date,
                        'type' => 'voucher',
                        'id' => $retirementVoucher->originalVoucher->id
                    ];
                }
            }

            $journalIds  = Journal::whereIn('id', $request->journal_ids)->pluck('id')->toArray();

            $journalItems = JournalEntry::whereIn('journal_id', $journalIds)->where('account_code', $eCodeItem->code)->get();

            foreach ($journalItems as $journalItem) {
                $data[] = [
                    'credit' => $journalItem->credit_amount,
                    'debit' => $journalItem->debit_amount,
                    // 'description' => $journalItem->journal->bankActivity->bank_name . '_' . $journalItem->journal->bankActivity->title . '_' . $journalItem->journal->bankActivity->account_number,
                    'description' => strtoupper($journalItem->description),
                    'reference' => $journalItem->journal->journal_number,
                    'date' => $journalItem->journal->journal_date,
                    'type' => 'journal',
                    'id' => $journalItem->journal->id
                ];
            }
        }

        $dates =  array_column($data, 'date');
        $description = array_column($data, 'description');
        array_multisort($dates, SORT_ASC, $description, SORT_ASC, $data);


        return Inertia::render('admin/reports/trialbalanceDetails', ['data' => $data, 'title' => $LedgerTitle, 'EconomicCode' => $LedgerEconomicCode, 'EconomicCodeItem' => $LedgerEconomicCodeItem, 'start_date' => Carbon::parse($startDate)->format('d/m/Y'), 'end_date' => Carbon::parse($endDate)->format('d/m/Y'), 'opening_balance' => $request->opening_balance ?? 0]);
    }
}
