<?php
// app/Http/Controllers/Api/ActivityStatsController.php

namespace App\Http\Controllers\Admin;

use Inertia\Inertia;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Carbon\CarbonPeriod;
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
        $mdas = Mda::all();
        return Inertia::render('admin/reports/trialbalanceControl', [
            'csrfToken' => csrf_token(),
            'mdas' => $mdas
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

                $voucherItems = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get();
                // if( $voucherItems->count() && $EconomicCode == '22020908' ){
                //     dd($voucherItems->toArray());
                // }


                $vouchers = Voucher::whereIn('id', $voucherItems->pluck('voucher_id'))->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {

                    $query->where('status', 'Submitted')
                        ->orWhere('status', 'Approved');
                })->with(['items'])->get();

                $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $receiptsSum = $receipts->sum('amount') ?? 0.00;
                // if( in_array( 9805, $vouchers->pluck('id')->toArray()) ){
                //     dd($vouchers->toArray());

                // }

                // dd($vouchers[0]);


                // $voucherRetirements = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id'))->get();

                $retirementItems = RetirementItem::where('economic_code_item_id', $economicCodeItem->id)
                    ->whereHas('retirementVoucher', function ($query) use ($month_start, $month_end) {
                        $query->whereHas('originalVoucher', function ($query) use ($month_start, $month_end) {
                            $query->whereBetween('voucher_date', [$month_start, $month_end])
                                ->where(function ($query) {
                                    $query->where('status', 'Submitted')
                                        ->orWhere('status', 'Approved');
                                });
                        });
                    })
                    ->get();

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

                // $voucher_item_totals = 0;
                // foreach ($vouchers as $voucher) {
                //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                // }
                $debitVouchersTotal  = 0.0;
                foreach ($vouchers as $voucher) {
                    $debitVouchersTotal += $voucher->items()->where('economy_code_item_id', $economicCodeItem->id)->get()->sum('sub_total');
                }

                $arrLineItems[] = [
                    'economic_code' => $EconomicCode,
                    'openning_balance' => 0,
                    'debits' => $debitVouchersTotal  + $sumRetirement + $journalDebits,
                    'credits' => $journalCredits + $receiptsSum,
                    'closing_balance' => (0 + $debitVouchersTotal + $sumRetirement + $journalDebits) - ($journalCredits + $receiptsSum),
                    'description' => $economicCodeItem->name,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'voucher',
                    'voucher_ids' => $vouchers->pluck('id')->toArray(),
                    'journal_ids' => $journals->pluck('id')->toArray(),
                    // 'voucher_numbers' => $vouchers->pluck('voucher_number')->toArray(),
                    // 'voucher_amounts' => $vouchers->pluck('total_amount')->toArray(),
                    // 'journal_numbers' => $journals->pluck('journal_number')->toArray(),
                ];
            } elseif (substr($EconomicCode, 0, 1) == '3') {


                $bank_activity = BankActivity::where('economic_code', $EconomicCode)->first();


                if (!empty($bank_activity)) {


                    $bank_activity_id = $bank_activity->id;

                    $receipts = Receipt::where('account_number', $bank_activity->account_number)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $OpeningBalance = CashBookBalanceBfw::where('economic_code', $EconomicCode)->first();
                    $DebitRemittances = Remittance::where('destination_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $CreditRemittances  = Remittance::where('source_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    $CreditVouchers = Voucher::where('bank_activity_id', $bank_activity_id)->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {
                        $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                    })->get();

                    // if ($EconomicCode == '31012525') {
                    //     dd( $CreditVouchers, $DebitRemittances, $CreditRemittances, $receipts);
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


                    // $bCredits = $CreditRemittances->sum('amount') + $CreditVouchers->sum('total_amount');

                    // $voucher_item_totals = 0;
                    // foreach ($CreditVouchers as $voucher) {
                    //     $voucher_item_totals += $voucher->items()->where('economy_code_item_id', $economicCodeItem->id)->get()->sum('sub_total');
                    // }


                    $bCredits = $CreditRemittances->sum('amount') +  $CreditVouchers->sum('total_amount');
                    $bDebits =   $receipts->sum('amount') + $DebitRemittances->sum('amount');
                    $bOpening_balance = $OpeningBalance->amount ?? 0.00;

                    $arrLineItems[] = [
                        'economic_code' => $EconomicCode,
                        'openning_balance' => floatVal($bOpening_balance),
                        'debits' => $bDebits + $journalDebits,
                        'credits' => $bCredits + $journalCredits,
                        'closing_balance' => $bOpening_balance + ($bDebits + $journalDebits)  - ($bCredits + $journalCredits),
                        'description' => $economicCodeItem->name,
                        'start_date' => $month_start,
                        'end_date' => $month_end,
                        'type' => 'bank',
                        'account_number' => $bank_activity->account_number,
                        'bank_activity_id' => $bank_activity_id,
                        'opening_balance' => $bOpening_balance->amount ?? 0,
                        'journal_ids' => $journals->pluck('id')->toArray(),
                        'voucher_ids' => $CreditVouchers->pluck('id')->toArray(),
                        'receipt_ids' => $receipts->pluck('id')->toArray(),
                    ];
                } else {


                    // none bank 3 series economic codes
                    $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                    $vouchers = Voucher::whereIn('id', $voucherIds->toArray())->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query) {
                        $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                    })->get();

                    // $voucher_item_totals = 0;
                    // foreach ($vouchers as $voucher) {
                    //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                    // }

                    // dd($vouchers);
                    // $retirementVouchers = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id')->toArray())->where('status', 'submitted')->get();

                    //    moved all 3 series retirement vouchers to debit
                    $CreditVouchers = 0.0;
                    if ($EconomicCode == '31080101') {
                        $retirementVouchers = RetirementVoucher::whereHas('originalVoucher', function ($query) use ($month_start, $month_end) {
                            $query->whereBetween('voucher_date', [$month_start, $month_end])->where(function ($query2) {
                                $query2->where('status', 'Submitted')->orWhere('status', 'Approved');
                            });
                        })->get();


                        $CreditVouchers = $retirementVouchers->sum('retired_amount') ?? 0.00;
                    }
                    // $retirementVoucherIds = RetirementItem::where('economic_code_item_id', $economicCodeItem->id)->get()->pluck('retirement_voucher_id');

                    // emd move all 3 series


                    $retirementItems = RetirementItem::where('economic_code_item_id', $economicCodeItem->id)
                        ->whereHas('retirementVoucher', function ($query) use ($month_start, $month_end) {
                            $query->whereHas('originalVoucher', function ($query) use ($month_start, $month_end) {
                                $query->whereBetween('voucher_date', [$month_start, $month_end])
                                    ->where(function ($query) {
                                        $query->where('status', 'Submitted')
                                            ->orWhere('status', 'Approved');
                                    });
                            });
                        })
                        ->get();

                    $sumRetirement = $retirementItems->sum('sub_total') ?? 0.00;

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
                    $debitVouchersTotal  = 0.0;
                    foreach ($vouchers as $voucher) {
                        $debitVouchersTotal += $voucher->items()->where('economy_code_item_id', $economicCodeItem->id)->get()->sum('sub_total');
                    }

                    $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                    // if ($economicCodeItem->code == '32010102') {
                    //     dd( $retirementVouchers, $vouchers);
                    // }
                    $arrLineItems[] = [
                        'economic_code' => $EconomicCode,
                        'openning_balance' => floatVal($opningBalance),
                        'debits' => $debitVouchersTotal + $journalDebits + $sumRetirement,
                        // 'debits' => $voucher_item_totals + $journalDebits,
                        'credits' => $journalCredits + $receipts->sum('amount') + $CreditVouchers,
                        'closing_balance' => ($opningBalance + $debitVouchersTotal + $journalDebits + $sumRetirement) - ( $CreditVouchers + $journalCredits + $receipts->sum('amount')),
                        'description' => $economicCodeItem->name,
                        'start_date' => $month_start,
                        'end_date' => $month_end,
                        'type' => 'voucher',
                        'voucher_ids' => $vouchers->pluck('id')->toArray(),
                        // 'retirmentIds' => $retirementVouchers->pluck('id')->toArray(),
                        'journal_ids' => $journals->pluck('id')->toArray(),
                        'receipt_ids' => $receipts->pluck('id')->toArray(),

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

                // dd($journals);

                // $voucher_item_totals = $vouchers->items()->get()->sum('sub_total');
                // $voucher_item_totals = 0;
                // foreach ($vouchers as $voucher) {
                //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                // }

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

                $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();

                $debitVouchersTotal  = 0.0;
                foreach ($vouchers as $voucher) {
                    $debitVouchersTotal += $voucher->items()->where('economy_code_item_id', $economicCodeItem->id)->get()->sum('sub_total');
                }
                $arrLineItems[] = [
                    'economic_code' => $EconomicCode,
                    'openning_balance' =>  floatVal($opningBalance),
                    'debits' => $debitVouchersTotal + $journalDebits,
                    // 'debits' => $voucher_item_totals + $journalDebits,
                    'credits' => $journalCredits + $receipts->sum('amount'),
                    'closing_balance' => ($opningBalance + $debitVouchersTotal + $journalDebits) - ($journalCredits + $receipts->sum('amount')),
                    'description' => $economicCodeItem->name,
                    'start_date' => $month_start,
                    'end_date' => $month_end,
                    'type' => 'voucher',
                    'voucher_ids' => $vouchers->pluck('id')->toArray(),
                    'journal_ids' => $journals->pluck('id')->toArray(),
                    'receipt_ids' => $receipts->pluck('id')->toArray(),
                ];
            }

            // if ($EconomicCode == '31012525') {
            //     dd(collect($arrLineItems)->where('economic_code', '31012525'));
            // }
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
            'journal_ids' => 'sometimes',
            'retirmentIds' => 'sometimes'
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
            // $vouchers = Voucher::with('bankActivity')->where('bank_activity_id', $bankActId?->id)->whereBetween('voucher_date', [$startDate, $endDate])->where(function ($query) use ($eCodeItem) {
            //     $query->where('status', 'Submitted')->orWhere('status', 'Approved');
            // })->get();

            $vouchers = Voucher::with([
                'bankActivity',
                'mda:id,name,oracle_name', // Only fetch relevant columns
                'items.economyCodeItem:id,code' // Eager load nested relationships
            ])
                ->where('bank_activity_id', $bankActId?->id)
                ->whereBetween('voucher_date', [$startDate, $endDate])
                ->whereIn('status', ['Submitted', 'Approved']) // Cleaner than orWhere
                ->get();

            // dd($vouchers->toArray());
            $receipts = Receipt::where('account_number', $bankActId->account_number)->whereBetween('receipt_date', [$startDate, $endDate])->where('status', 'Submitted')->get();
            $DebitRemittances = Remittance::where('destination_bank_id', $bankActId->id)->whereBetween('transfer_date', [$startDate, $endDate])->where('status', 'Submitted')->get();
            $CreditRemittances = Remittance::where('source_bank_id', $bankActId->id)->whereBetween('transfer_date', [$startDate, $endDate])->where('status', 'Submitted')->get();


            // dd($receipts, $CreditRemittances, $DebitRemittances, $vouchers);

            // dd($vouchers->toArray());



            foreach ($DebitRemittances  as  $remittance) {

                $data[] = [
                    'credit' => 0,
                    'debit' => $remittance->amount,
                    'description' => $remittance->narration,
                    'reference' => $remittance->receipt_number,
                    'date' => $remittance->transfer_date,
                    'type' => 'remittance',
                    'id' => $remittance->id,
                    'mda_name' => 'Remittance',
                    'bank_name' => $remittance->sourceBank->bank_name,
                    'account_number' => $remittance->sourceBank->account_number,
                    'bank_economic_code' => $remittance->sourceBank->economic_code,
                    'item_code' => 'N/A',
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
                    'id' => $remittance->id,
                    'mda_name' => 'Remittance',
                    'bank_name' => $remittance->destinationBank->bank_name,
                    'account_number' => $remittance->destinationBank->account_number,
                    'bank_economic_code' => $remittance->sourceBank->economic_code,
                    'item_code' => 'N/A',

                ];
            }



            foreach ($vouchers as  $voucher) {
                foreach ($voucher->items as $voucher_item) {

                    $data[] = [
                        // 'credit' => $voucher->total_amount,
                        'credit' => $voucher_item->sub_total,
                        'debit' => 0.00,
                        // 'description' => $voucher->bankActivity->bank_name . '_' . $voucher->bankActivity->title . '_' . $voucher->bankActivity->account_number,
                        'description' => strtoupper($voucher_item->description),
                        'reference' => $voucher->voucher_number,
                        'date' => $voucher->voucher_date,
                        'type' => 'voucher',
                        'id' => $voucher->id,
                        'mda_name' => $voucher->mda->name ?? $voucher->mda->oracle_name ?? 'N/A',
                        'bank_name' => $voucher->bankActivity->bank_name,
                        'account_number' => $voucher->bankActivity->account_number,
                        'bank_economic_code' => $voucher->bankActivity->economic_code,
                        'item_code' => $voucher_item->economyCodeItem->code ?? 'N/A',

                    ];
                }
            }

            // dd("no issues here");


            foreach ($receipts as $receipt) {

                $data[] = [
                    'credit' => 0,
                    'debit' => $receipt->amount,
                    // 'description' => $receipt->bank_name . '_' . $receipt->title . '_' . $receipt->account_number,
                    'description' => strtoupper($receipt->activity),
                    'reference' => $receipt->receipt_number,
                    'date' => $receipt->receipt_date,
                    'type' => 'receipt',
                    'id' => $receipt->id,
                    'mda_name' => $receipt->mda_name ?? 'N/A',
                    'bank_name' => $receipt->bank_name,
                    'account_number' => $receipt->account_number,
                    'bank_economic_code' => $receipt->bankActivity()->economic_code ?? 'N/A',
                    'item_code' => 'N/A',
                ];
            }


            $journalIds  = Journal::whereIn('id', $request->journal_ids)->pluck('id')->toArray();

            $journalItems = JournalEntry::whereIn('journal_id', $journalIds)->where('account_code', $eCodeItem->code)->get();

            // dd($journalIds);

            foreach ($journalItems as $journalItem) {
                $data[] = [
                    'credit' => $journalItem->credit_amount,
                    'debit' => $journalItem->debit_amount,
                    // 'description' => $journalItem->journal->bankActivity->bank_name . '_' . $journalItem->journal->bankActivity->title . '_' . $journalItem->journal->bankActivity->account_number,
                    'description' => strtoupper($journalItem->description),
                    'reference' => $journalItem->journal->journal_number,
                    'date' => $journalItem->journal->journal_date,
                    'type' => 'journal',
                    'id' => $journalItem->journal->id,
                    'mda_name' => $journalItem->journal->mda->name ?? $journalItem->journal->mda->oracle_name ?? 'N/A',
                    'bank_name' => 'Journal',
                    'account_number' => 'Journal',
                    'bank_economic_code' => 'Journal',
                    'item_code' => 'N/A',
                ];
            }

            // dd($data);
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
                    'id' => $receipt->id,
                    'mda_name' => $receipt->mda_name ?? 'N/A',
                    'bank_name' => $receipt->bank_name,
                    'account_number' => $receipt->account_number,
                    'bank_economic_code' => $receipt->bankActivity()->economic_code ?? 'N/A'
                ];
            }

            $journalIds  = Journal::whereIn('id', $request->journal_ids)->pluck('id')->toArray();

            $journalItems = JournalEntry::whereIn('journal_id', $journalIds)->where('account_code', $eCodeItem->code)->get();
            // dd($journalIds);

            foreach ($journalItems as $journalItem) {
                $data[] = [
                    'credit' => $journalItem->credit_amount,
                    'debit' => $journalItem->debit_amount,
                    // 'description' => $journalItem->journal->bankActivity->bank_name . '_' . $journalItem->journal->bankActivity->title . '_' . $journalItem->journal->bankActivity->account_number,
                    'description' => strtoupper($journalItem->description),
                    'reference' => $journalItem->journal->journal_number,
                    'date' => $journalItem->journal->journal_date,
                    'type' => 'journal',
                    'id' => $journalItem->journal->id,
                    'mda_name' => $journalItem->journal->mda->name ?? $journalItem->journal->mda->oracle_name ?? 'N/A',
                    'bank_name' => 'Journal',
                    'account_number' => 'Journal',
                    'bank_economic_code' => 'Journal'
                ];
            }
        } elseif ($type == 'voucher') {
            // $economicVoucherItems = VoucherItem::where('economy_code_item_id', $economic_code)->get();

            // $voucherItems = VoucherItem::whereIn('id', $request->voucher_ids)->get();

            // dd($request->voucher_ids );

            $vouchers = Voucher::whereIn('id', $request->voucher_ids)->get();
            // $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->first();
            // $opningBalance = $economicCodeBalance->amount ?? 0.00;

            // $voucherIds = $vouchers->pluck('id')->toArray();

            $retirementVouchers = RetirementVoucher::whereIn('original_voucher_id', $vouchers->pluck('id')->toArray())->where('status', 'submitted')->get();
            // dd($request->retirmentIds);
            // $retirementVouchers = RetirementVoucher::whereIn('id', $request->retirmentIds)->where('status', 'submitted')->get();
            // dd($retirementVouchers);
            // if (count($retirementVouchers) > 0) {
            //     dd($request->voucher_ids);
            // }

            // $retirementVoucherItems = RetirementItem::where('economic_code_item_id', $eCodeItem->id)->get();
            $retirementVoucherItems = RetirementItem::where('economic_code_item_id', $eCodeItem->id)
                ->whereHas('retirementVoucher', function ($query) use ($startDate, $endDate) {
                    $query->whereHas('originalVoucher', function ($query) use ($startDate, $endDate) {
                        $query->whereBetween('voucher_date', [$startDate, $endDate])
                            ->where(function ($query) {
                                $query->where('status', 'Submitted')
                                    ->orWhere('status', 'Approved');
                            });
                    });
                })
                ->get();
            // $CreditVouchers = $retirementVouchers->sum('retired_amount') ?? 0.00;
            // dd($request->voucher_ids, $retirementVouchers->toArray());
            foreach ($vouchers as $voucher) {
                $voucher_item_totals = $voucher->items()->where('economy_code_item_id', $eCodeItem->id)->get()->sum('sub_total');

                $data[] = [
                    'credit' => 0.00,
                    // 'debit' => $voucher->total_amount,
                    'debit' => $voucher_item_totals,
                    // 'description' => $voucher->bankActivity->bank_name . '_' . $voucher->bankActivity->title . '_' . $voucher->bankActivity->account_number,
                    'description' => strtoupper($voucher->narration),
                    'reference' => $voucher->voucher_number,
                    'date' => $voucher->voucher_date,
                    'type' => 'voucher',
                    'id' => $voucher->id,
                    'mda_name' => $voucher->mda->name ?? $voucher->mda->oracle_name ?? 'N/A',
                    'bank_name' => $voucher->bankActivity->bank_name,
                    'account_number' => $voucher->bankActivity->account_number,
                    'bank_economic_code' => $voucher->bankActivity->economic_code,
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
                    'id' => $receipt->id,
                    'mda_name' => $receipt->mda_name ?? 'N/A',
                    'bank_name' => $receipt->bank_name,
                    'account_number' => $receipt->account_number,
                    'bank_economic_code' => $receipt->bankActivity()->economic_code ?? 'N/A'
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
                        'id' => $item->retirementVoucher->originalVoucher->id,
                        'mda_name' => $item->retirementVoucher->originalVoucher->mda->name ?? $item->retirementVoucher->originalVoucher->mda->oracle_name ?? 'N/A',
                        'bank_name' => $item->retirementVoucher->originalVoucher->bankActivity->bank_name,
                        'account_number' => $item->retirementVoucher->originalVoucher->bankActivity->account_number,
                        'bank_economic_code' => $item->retirementVoucher->originalVoucher->bankActivity->economic_code

                    ];
                }
            }

            if (substr($eCodeItem->code, 0, 1) == '3') {
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
                        'id' => $item->retirementVoucher->originalVoucher->id,
                        'mda_name' => $item->retirementVoucher->originalVoucher->mda->name ?? $item->retirementVoucher->originalVoucher->mda->oracle_name ?? 'N/A',
                        'bank_name' => $item->retirementVoucher->originalVoucher->bankActivity->bank_name,
                        'account_number' => $item->retirementVoucher->originalVoucher->bankActivity->account_number,
                        'bank_economic_code' => $item->retirementVoucher->originalVoucher->bankActivity->economic_code

                    ];
                }

                foreach ($retirementVouchers as $retirementVoucher) {
                    $data[] = [
                        'credit' => $retirementVoucher->retired_amount,
                        'debit' => 0.00,
                        // 'description' => $retirementVoucher->bankActivity->bank_name . '_' . $retirementVoucher->bankActivity->title . '_' . $retirementVoucher->bankActivity->account_number,
                        'description' => strtoupper($retirementVoucher->originalVoucher->narration),
                        'reference' => $retirementVoucher->originalVoucher->voucher_number,
                        'date' => $retirementVoucher->originalVoucher->voucher_date,
                        'type' => 'voucher',
                        'id' => $retirementVoucher->originalVoucher->id,
                        'mda_name' => $retirementVoucher->originalVoucher->mda->name ?? $retirementVoucher->originalVoucher->mda->oracle_name ?? 'N/A',
                        'bank_name' => $retirementVoucher->originalVoucher->bankActivity->bank_name,
                        'account_number' => $retirementVoucher->originalVoucher->bankActivity->account_number,
                        'bank_economic_code' => $retirementVoucher->originalVoucher->bankActivity->economic_code

                    ];
                }
            }

            $journalIds  = Journal::whereIn('id', $request->journal_ids)->pluck('id')->toArray();

            $journalItems = JournalEntry::whereIn('journal_id', $journalIds)->where('account_code', $eCodeItem->code)->get();
            // dd($journalIds);

            foreach ($journalItems as $journalItem) {
                $data[] = [
                    'credit' => $journalItem->credit_amount,
                    'debit' => $journalItem->debit_amount,
                    // 'description' => $journalItem->journal->bankActivity->bank_name . '_' . $journalItem->journal->bankActivity->title . '_' . $journalItem->journal->bankActivity->account_number,
                    'description' => strtoupper($journalItem->description),
                    'reference' => $journalItem->journal->journal_number,
                    'date' => $journalItem->journal->journal_date,
                    'type' => 'journal',
                    'id' => $journalItem->journal->id,
                    'mda_name' => $journalItem->journal->mda->name ?? $journalItem->journal->mda->oracle_name ?? 'N/A',
                    'bank_name' => 'Journal',
                    'account_number' => 'Journal',
                    'bank_economic_code' => 'Journal'
                ];
            }
        }

        $dates =  array_column($data, 'date');
        $description = array_column($data, 'description');
        array_multisort($dates, SORT_ASC, $description, SORT_ASC, $data);
        // dd($data);

        return Inertia::render('admin/reports/trialbalanceDetails', ['data' => $data, 'title' => $LedgerTitle, 'EconomicCode' => $LedgerEconomicCode, 'EconomicCodeItem' => $LedgerEconomicCodeItem, 'start_date' => Carbon::parse($startDate)->format('d/m/Y'), 'end_date' => Carbon::parse($endDate)->format('d/m/Y'), 'opening_balance' => $request->opening_balance ?? 0, 'ledger_type' => $type]);
    }

    public function mdaTrialBalance(Request $request)
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

        $mda = Mda::find($request->mda_id, ['id', 'name', 'oracle_name', 'code']);

        // dd($mda);
        // Get last day of the month

        $lastDayOfMonth = $date->endOfMonth()->format('Y-m-d');
        $month_start = '2025-01-01';
        $start_date = Carbon::parse($month_start);
        $month_end = $lastDayOfMonth ?? '2025-03-31';

        $monthsDifference = (int) $start_date->diffInMonths($lastDayOfMonth);

        // dd($monthsDifference);


        $arrLineItems = [];

        // $salaryECI = [

        //     '41030101',
        //     '41030102',
        //     '41030103',
        //     '41030202',
        //     '41030203',
        //     '41030204',
        //     '41030205',
        //     '41030206',
        //     '41030208',
        //     '41030211',
        //     '41030214',
        //     '41030216',
        //     '41030319',
        //     '41040101',
        // ];

        $economicCodeItems = EconomyCodeItem::where('status', 'active')->orderBy('code')->get();

        foreach ($economicCodeItems as $economicCodeItem) {
            $EconomicCode = $economicCodeItem->code;

            $openingBalance = 0.00;
            $start_date = Carbon::parse($month_start);

            while (true) {
                $first_day = $start_date->firstOfMonth()->format('Y-m-d');
                $last_day = $start_date->endOfMonth()->format('Y-m-d');
                $monthly_opening_balance = $openingBalance;
                $monthly_debits = 0.00;
                $monthly_credits = 0.00;
                $monthly_closing_balance = 0.00;
                $monthly_receipt_ids = [];
                $monthly_journal_ids = [];
                $monthly_voucher_ids = [];
                $monthly_credit_remittance_ids = [];
                $monthly_debit_remittance_ids = [];
                $monthly_retirement_ids = [];


                // echo 'Economic Code: ' . $EconomicCode  .  'First day: ' . $first_day .  " last day: " . $last_day . "<br>";
                if (substr($EconomicCode, 0, 1) == '1') {
                    //  revenues 
                    $receipts = Receipt::where(function ($query) use ($mda) {
                        $query->where('mda_name', 'like',  '%' . $mda->name . '%')->orWhere('mda_name', 'like',  '%' . $mda->code . '%');
                    })
                        ->where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$first_day, $last_day])->where('status', 'Submitted')->get();



                    $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                    $journals = Journal::whereIn('id', $journalIds)
                        ->where('mda_id', $mda->id)
                        ->whereBetween('journal_date', [$first_day, $last_day])->where('status', 'approved')->get();
                    $journalCredits = 0.00;
                    $journalDebits = 0.00;
                    foreach ($journals as $journal) {
                        // dd($journal->entries);
                        $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                        $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                    }

                    // dd($journals);

                    // $remitances = Remittance::where()
                    if ($receipts->isNotEmpty() || $journals->isNotEmpty()) {
                        $monthly_debits  += $journalDebits;
                        $monthly_credits += $receipts->sum('amount') + $journalCredits;
                        $monthly_closing_balance += ($openingBalance +  $journalDebits)  - ($receipts->sum('amount') + $journalCredits);
                        $monthly_receipt_ids[] = $receipts->pluck('id')->toArray();
                        $monthly_journal_ids[] = $journals->pluck('id')->toArray();

                        // $arrLineItems[] = [
                        //     'economic_code' => $EconomicCode,
                        //     'openning_balance' => 0,
                        //     'debits' => $journalDebits,
                        //     'credits' => $receipts->sum('amount') + $journalCredits,
                        //     'closing_balance' => $journalDebits - ($receipts->sum('amount') + $journalCredits),
                        //     'description' => $economicCodeItem->name,
                        //     'start_date' => $first_day,
                        //     'end_date' => $last_day,
                        //     'type' => 'receipt',
                        //     'journal_ids' => $journals->pluck('id')->toArray(),
                        //     'receipt_ids' => $receipts->pluck('id')->toArray(),


                        // ];
                    }
                } elseif (substr($EconomicCode, 0, 1) == '2') {
                    // expenses

                    $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');


                    $vouchers = Voucher::whereIn('id', $voucherIds)
                        ->whereBetween('voucher_date', [$first_day, $last_day])
                        ->where(function ($query) {

                            $query->where('status', 'Submitted')
                                ->orWhere('status', 'Approved');
                        })
                        ->where('mda_id', $mda->id)
                        ->get();

                    $receipts = Receipt::where('eco_code_item', $EconomicCode)
                        ->whereBetween('receipt_date', [$first_day, $last_day])
                        ->where('status', 'Submitted')
                        ->where(function ($query) use ($mda) {
                            $query->where('mda_name', 'like',  '%' . $mda->name . '%')->orWhere('mda_name', 'like',  '%' . $mda->code . '%');
                        })
                        ->get();
                    $receiptsSum = $receipts->sum('amount') ?? 0.00;
                    // if( in_array( 9805, $vouchers->pluck('id')->toArray()) ){
                    //     dd($vouchers->toArray());

                    // }


                    $voucherRetirements = RetirementVoucher::whereHas('originalVoucher', function ($query) use ($mda, $first_day, $last_day) {
                        $query->where('mda_id', $mda->id)->where('status', 'approved')->whereBetween('voucher_date', [$first_day, $last_day]);
                    })->where('status', 'submitted')->get();

                    $retirementItems = RetirementItem::where('economic_code_item_id', $economicCodeItem->id)
                        ->whereIn('retirement_voucher_id', $voucherRetirements->pluck('id')->toArray())
                        ->get();

                    $sumRetirement = $retirementItems->sum('sub_total') ?? 0.00;

                    // if($sumRetirement > 0){
                    //     dd($retirementItems->toArray());
                    // }


                    $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                    $journals = Journal::whereIn('id', $journalIds)
                        ->whereBetween('journal_date', [$first_day, $last_day])
                        ->where('status', 'approved')
                        ->where('mda_id', $mda->id)
                        ->get();
                    $journalCredits = 0.00;
                    $journalDebits = 0.00;
                    foreach ($journals as $journal) {
                        // dd($journal->entries);
                        $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                        $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                    }

                    // $voucher_item_totals = 0;
                    // foreach ($vouchers as $voucher) {
                    //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                    // }
                    if ($vouchers->isNotEmpty() || $journals->isNotEmpty() || $receipts->isNotEmpty() || $retirementItems->isNotEmpty()) {

                        $monthly_debits += $vouchers->sum('total_amount') + $sumRetirement + $journalDebits;
                        $monthly_credits += $receiptsSum + $journalCredits;
                        $monthly_closing_balance += ($openingBalance + $vouchers->sum('total_amount') + $sumRetirement + $journalDebits) - ($journalCredits + $receiptsSum);
                        $monthly_receipt_ids[] = $receipts->pluck('id')->toArray();
                        $monthly_voucher_ids[] = $vouchers->pluck('id')->toArray();
                        $monthly_journal_ids[] = $journals->pluck('id')->toArray();

                        // $arrLineItems[] = [
                        //     'economic_code' => $EconomicCode,
                        //     'openning_balance' => 0,
                        //     'debits' => $vouchers->sum('total_amount')  + $sumRetirement + $journalDebits,
                        //     'credits' => $journalCredits + $receiptsSum,
                        //     'closing_balance' => (0 + $vouchers->sum('total_amount') + $sumRetirement + $journalDebits) - ($journalCredits + $receiptsSum),
                        //     'description' => $economicCodeItem->name,
                        //     'start_date' => $first_day,
                        //     'end_date' => $last_day,
                        //     'type' => 'voucher',
                        //     'voucher_ids' => $vouchers->pluck('id')->toArray(),
                        //     'journal_ids' => $journals->pluck('id')->toArray(),
                        //     // 'retirement_ids' => $retirementItems->pluck('retirement_voucher_id')->toArray(),
                        //     'receipt_ids' => $receipts->pluck('id')->toArray()
                        // ];
                    }
                } elseif (substr($EconomicCode, 0, 1) == '3') {


                    $bank_activity = BankActivity::where('economic_code', $EconomicCode)->first();

                    // if ($EconomicCode == '31010650') {
                    //     dd($bank_activity);

                    // }


                    if (!empty($bank_activity)) {


                        $bank_activity_id = $bank_activity->id;

                        $receipts = Receipt::where('account_number', $bank_activity->account_number)
                            ->whereBetween('receipt_date', [$first_day, $last_day])
                            ->where(function ($query) use ($mda) {
                                $query->where('mda_name', 'like',  '%' . $mda->name . '%')->orWhere('mda_name', 'like',  '%' . $mda->code . '%');
                            })
                            ->where('status', 'Submitted')->get();
                        $Opening_Balance = CashBookBalanceBfw::where('economic_code', $EconomicCode)->first();
                        $DebitRemittances = Remittance::where('destination_bank_id', $bank_activity_id)
                            ->whereBetween('transfer_date', [$first_day, $last_day])
                            ->where('status', 'Submitted')->get();
                        $CreditRemittances  = Remittance::where('source_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$first_day, $last_day])->where('status', 'Submitted')->get();
                        $CreditVouchers = Voucher::where('mda_id', $mda->id)
                            ->where('bank_activity_id', $bank_activity_id)->whereBetween('voucher_date', [$first_day, $last_day])->where(function ($query) {
                                $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                            })->get();

                        $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();

                        $journals = Journal::where('mda_id', $mda->id)
                            ->whereIn('id', $journalIds)->whereBetween('journal_date', [$first_day, $last_day])->where('status', 'approved')->get();
                        $journalCredits = 0.00;
                        $journalDebits = 0.00;

                        // if ($EconomicCode == '31012414' ) {
                        //     // dd( DB::table('journal_entries')->where('account_code', $EconomicCode)->toRawSql() );
                        //     dd($journalIds, $EconomicCode);
                        // }


                        foreach ($journals as $journal) {
                            // dd($journal->entries);
                            $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                            $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                        }


                        // $bCredits = $CreditRemittances->sum('amount') + $CreditVouchers->sum('total_amount');

                        // $voucher_item_totals = 0;
                        // foreach ($CreditVouchers as $voucher) {
                        //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                        // }


                        $bCredits = $CreditRemittances->sum('amount') + $CreditVouchers->sum('total_amount');
                        $bDebits =   $receipts->sum('amount') + $DebitRemittances->sum('amount');
                        $bOpening_balance = $Opening_Balance->amount ?? 0.00;

                        if ($receipts->isNotEmpty() || $journals->isNotEmpty() || $DebitRemittances->isNotEmpty() || $CreditRemittances->isNotEmpty() || $CreditVouchers->isNotEmpty()) {

                            $monthly_debits += $bDebits + $journalDebits;
                            $monthly_credits += $bCredits + $journalCredits;
                            $openingBalance += $bOpening_balance;
                            $monthly_closing_balance += ($openingBalance + $bDebits + $journalDebits)  - ($bCredits + $journalCredits);
                            $monthly_receipt_ids[] = $receipts->pluck('id')->toArray();
                            $monthly_journal_ids[] = $journals->pluck('id')->toArray();
                            $monthly_voucher_ids[] = $CreditVouchers->pluck('id')->toArray();
                            $monthly_debit_remittance_ids[] = $DebitRemittances->pluck('id')->toArray();
                            $monthly_credit_remittance_ids[] = $CreditRemittances->pluck('id')->toArray();


                            // $arrLineItems[] = [
                            //     'economic_code' => $EconomicCode,
                            //     'openning_balance' => floatVal($bOpening_balance),
                            //     'debits' => $bDebits + $journalDebits,
                            //     'credits' => $bCredits + $journalCredits,
                            //     'closing_balance' => $bOpening_balance + ($bDebits + $journalDebits)  - ($bCredits + $journalCredits),
                            //     'description' => $economicCodeItem->name,
                            //     'start_date' => $first_day,
                            //     'end_date' => $last_day,
                            //     'type' => 'bank',
                            //     'account_number' => $bank_activity->account_number,
                            //     'bank_activity_id' => $bank_activity_id,
                            //     'opening_balance' => $bOpening_balance->amount ?? 0,
                            //     'journal_ids' => $journals->pluck('id')->toArray(),
                            //     'receipt_ids' => $receipts->pluck('id')->toArray(),
                            // ];
                        }
                    } else {
                        $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                        $vouchers = Voucher::where('mda_id', $mda->id)->whereIn('id', $voucherIds->toArray())->whereBetween('voucher_date', [$first_day, $last_day])->where(function ($query) {
                            $query->where('status', 'Submitted')->orWhere('status', 'Approved');
                        })->get();

                        // $voucher_item_totals = 0;
                        // foreach ($vouchers as $voucher) {
                        //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                        // }

                        // dd($vouchers);
                        $retirementVouchers = RetirementVoucher::where('mda_id', $mda->id)
                            ->whereHas('originalVoucher', function ($query) use ($first_day, $last_day) {
                                $query->whereBetween('voucher_date', [$first_day, $last_day]);
                            })->where('status', 'submitted')
                            ->get();
                        // if ( $economicCodeItem->code == 31080101) {
                        //     dd($retirementVouchers, $vouchers);
                        // }

                        $CreditVouchers = $retirementVouchers->sum('retired_amount') ?? 0.00;

                        $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->first();
                        $opningBalance = $economicCodeBalance->amount ?? 0.00;

                        $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                        $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$first_day, $last_day])->where('status', 'approved')->get();
                        $journalCredits = 0.00;
                        $journalDebits = 0.00;
                        foreach ($journals as $journal) {
                            // dd($journal->entries);
                            $journalCredits += $journal->entries->where('account_code', $EconomicCode)->sum('credit_amount');
                            $journalDebits += $journal->entries->where('account_code', $EconomicCode)->sum('debit_amount');
                        }

                        if ($vouchers->isNotEmpty() || $journals->isNotEmpty() || $retirementVouchers->isNotEmpty()) {
                            $monthly_debits += $vouchers->sum('total_amount') + $journalDebits;
                            $monthly_credits += $CreditVouchers + $journalCredits;
                            $openingBalance += $opningBalance;
                            $monthly_closing_balance += ($openingBalance + $vouchers->sum('total_amount') + $journalDebits) - ($CreditVouchers + $journalCredits);
                            $monthly_voucher_ids[] = $vouchers->pluck('id')->toArray();
                            $monthly_retirement_ids[] = $retirementVouchers->pluck('id')->toArray();
                            $monthly_journal_ids[] = $journals->pluck('id')->toArray();

                            // $arrLineItems[] = [
                            //     'economic_code' => $EconomicCode,
                            //     'openning_balance' => floatVal($opningBalance),
                            //     'debits' => $vouchers->sum('total_amount') + $journalDebits,
                            //     // 'debits' => $voucher_item_totals + $journalDebits,
                            //     'credits' => $CreditVouchers + $journalCredits,
                            //     'closing_balance' => ($opningBalance + $vouchers->sum('total_amount') + $journalDebits) - ($CreditVouchers + $journalCredits),
                            //     'description' => $economicCodeItem->name,
                            //     'start_date' => $first_day,
                            //     'end_date' => $last_day,
                            //     'type' => 'voucher',
                            //     'voucher_ids' => $vouchers->pluck('id')->toArray(),
                            //     'retirmentIds' => $retirementVouchers->pluck('id')->toArray(),
                            //     'journal_ids' => $journals->pluck('id')->toArray(),

                            // ];
                        }
                    }
                    // } elseif (substr($EconomicCode, 0, 1) == '4' && in_array($EconomicCode, $salaryECI)) {
                } elseif (substr($EconomicCode, 0, 1) == '4') {

                    $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                    $vouchers = Voucher::where('mda_id', $mda->id)
                        ->whereIn('id', $voucherIds)->whereBetween('voucher_date', [$first_day, $last_day])->where('status', 'Submitted')->get();
                    $economicCodeBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->where('financial_year', 2024)->first();
                    $opningBalance = $economicCodeBalance->amount ?? 0.00;

                    // $journals = Journal::whereHas( 'entries', function ($query) use ($EconomicCode) {
                    //     $query->where('account_code', $EconomicCode);
                    // dd($query->get());
                    // } )->whereBetween('journal_date', [$first_day, $last_day])->where('status', 'approved')->get();

                    $journalIds = JournalEntry::where('account_code', $EconomicCode)->get()->pluck('journal_id')->toArray();
                    $journals = Journal::where('mda_id', $mda->id)
                        ->whereIn('id', $journalIds)->whereBetween('journal_date', [$first_day, $last_day])->where('status', 'approved')->get();

                    // dd($journals);

                    // $voucher_item_totals = $vouchers->items()->get()->sum('sub_total');
                    // $voucher_item_totals = 0;
                    // foreach ($vouchers as $voucher) {
                    //     $voucher_item_totals += $voucher->items()->get()->sum('sub_total');
                    // }

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

                    if ($vouchers->isNotEmpty() || $journals->isNotEmpty()) {

                        $monthly_debits += $vouchers->sum('total_amount') + $journalDebits;
                        $monthly_credits += $journalCredits;
                        $openingBalance += $opningBalance;
                        $monthly_closing_balance += ($openingBalance + $vouchers->sum('total_amount') + $journalDebits) - $journalCredits;
                        $monthly_journal_ids[] = $journals->pluck('id')->toArray();
                        $monthly_voucher_ids[] = $vouchers->pluck('id')->toArray();


                        // $arrLineItems[] = [
                        //     'economic_code' => $EconomicCode,
                        //     'openning_balance' =>  floatVal($opningBalance),
                        //     'debits' => $vouchers->sum('total_amount') + $journalDebits,
                        //     // 'debits' => $voucher_item_totals + $journalDebits,
                        //     'credits' => $journalCredits,
                        //     'closing_balance' => ($opningBalance + $vouchers->sum('total_amount') + $journalDebits) - $journalCredits,
                        //     'description' => $economicCodeItem->name,
                        //     'start_date' => $first_day,
                        //     'end_date' => $last_day,
                        //     'type' => 'voucher',
                        //     'voucher_ids' => $vouchers->pluck('id')->toArray(),
                        //     'journal_ids' => $journals->pluck('id')->toArray(),
                        // ];
                    }
                }


                // dd($start_date, $month_end);
                if ($monthly_debits > 0 || $monthly_credits > 0) {
                    $arrLineItems[] = [
                        'economic_code' => $EconomicCode,
                        'openning_balance' =>  floatVal($openingBalance),
                        'debits' => $monthly_debits,
                        'credits' => $monthly_credits,
                        'closing_balance' => ($openingBalance + $monthly_debits) - $monthly_credits,
                        'description' => $economicCodeItem->name,
                        'start_date' => $first_day,
                        'end_date' => $last_day,
                        'month' => $start_date->format('M'),
                        "receipt_ids" => $monthly_receipt_ids,
                        'voucher_ids' => $monthly_voucher_ids,
                        'journal_ids' => $monthly_journal_ids,
                        'retirement_ids' => $monthly_retirement_ids,
                        'çredit_remittance_ids' => $monthly_credit_remittance_ids,
                        'debit_remittance_ids' => $monthly_debit_remittance_ids,
                        'mda_name' => $mda->name,
                        'mda_id' => $mda->id,
                        'mda_code' => $mda->code
                    ];
                }
                $openingBalance = ($openingBalance + $monthly_debits) - $monthly_credits;

                $start_date =  $start_date->startOfMonth()->addMonth()->startOfMonth();
                if ($start_date->startOfMonth() > $date->startOfMonth()) {
                    break;
                }
            } // end of monthly loop
            // echo $start_date . '  ' . $EconomicCode . '<br>';
            // dd($arrLineItems);
        }
        // dd($arrLineItems);
        return Inertia::render('admin/reports/mdaTrialBalance', [

            'data' => $arrLineItems,

        ]);
    }
}
