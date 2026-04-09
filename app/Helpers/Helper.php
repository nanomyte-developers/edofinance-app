<?php
// namespace App\Helpers;
use App\Models\EconomyCodeItem;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\BankActivity;
use App\Models\CashBookBalanceBfw;
use App\Models\EconomicCodeBalance;

function convertToMysqlDate($dateString)
{
    $date = \DateTime::createFromFormat('d-M-y', $dateString);
    return $date ? $date->format('Y-m-d') : null;
}



function getEconomicCodeSum($economicCodeItemsArr)
{
    $sum = 0;
    $month_start = '2025-01-01';
    $month_end = '2025-03-31';
    // $arrLineItems = [];
    $sum = 0;

    $economicCodeItems = EconomyCodeItem::whereIn('code', $economicCodeItemsArr)->where('status', 'active')->orderBy('code')->get();

    foreach ($economicCodeItems as $economicCodeItem) {
        $EconomicCode = $economicCodeItem->code;
        if (substr($EconomicCode, 0, 1) == '1') {

            // dd($EconomicCode);
            //  revenues 
            $receipts = Receipt::where('eco_code_item', $EconomicCode)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
            // $remitances = Remittance::where()
            if ($receipts->sum('amount') > 0) {
                $sum += $receipts->sum('amount');
            }
        } elseif (substr($EconomicCode, 0, 1) == '2') {
            // expenses

            $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
            $vouchers = Voucher::whereIn('id', $voucherIds)->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted')->get();

            $sum += $vouchers->sum('total_amount');
        } elseif (substr($EconomicCode, 0, 1) == '3') {

            $bank_activity = BankActivity::where('economic_code', $EconomicCode)->first();

            if (!empty($bank_activity)) {

                $bank_activity_id = $bank_activity->id;

                $receipts = Receipt::where('account_number', $bank_activity->account_number)->whereBetween('receipt_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $OpeningBalance = CashBookBalanceBfw::where('economic_code', $EconomicCode)->first();
                $DebitRemittances = Remittance::where('destination_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $CreditRemittances  = Remittance::where('source_bank_id', $bank_activity_id)->whereBetween('transfer_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                $CreditVouchers = Voucher::where('bank_activity_id', $bank_activity_id)->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                // $remitances = Remittance::where()

                // if ($account_number == '5030017247') {
                //     dd($CreditRemittances, $DebitRemittances, $DebitVouchers, $receipts, $bank_activity_id);
                // }

                $bCredits = $CreditRemittances->sum('amount') + $CreditVouchers->sum('total_amount');;
                $bDebits =   $receipts->sum('amount') + $DebitRemittances->sum('amount');
                $bOpening_balance = $OpeningBalance->amount ?? 0.00;
                $sum += $bOpening_balance + $bDebits - $bCredits;
            } else {


                $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
                $vouchers = Voucher::whereIn('id', $voucherIds)->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
                // if(count($vouchers) > 0){

                //     dd($economicCodeItem->code, $vouchers);
                // }
                $sum += $vouchers->sum('total_amount');
            }
        } elseif (substr($EconomicCode, 0, 1) == '4') {
            
            $OpeningBalance = EconomicCodeBalance::where('economic_code', $EconomicCode)->where('financial_year', 2024)->first();
            // if ($EconomicCode == '47010101') {
            //     dd($OpeningBalance);
            // }

            $voucherIds = VoucherItem::where('economy_code_item_id', $economicCodeItem->id)->get()->pluck('voucher_id');
            $vouchers = Voucher::whereIn('id', $voucherIds)->whereBetween('voucher_date', [$month_start, $month_end])->where('status', 'Submitted')->get();
            if (!empty($OpeningBalance) ) {
                if (!empty($vouchers)) {
                    $sum += $OpeningBalance->amount + $vouchers->sum('total_amount');
                } else {
                    $sum += $OpeningBalance->amount;
                }
            } elseif (!empty($vouchers) ) {
                $sum +=  $vouchers->sum('total_amount');
            }
        }
    }

    return $sum;

    // dd($arrLineItems);
}
