<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankActivity;
use App\Models\Cashbook;
use App\Models\CashbookEntry;
use App\Models\CashbookFinancialYear;
use App\Models\EconomyCode;
use App\Models\EconomyCodeItem;
use App\Models\Receipt;
use App\Models\Remittance;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use App\Models\JournalEntry;
use App\Models\Journal;

class CashbookController extends Controller
{
    /**
     * Generate entries for a specific cashbook (account + month)
     */
    // public function generateEntries(Cashbook $cashbook)
    // {
    //     try {
    //         DB::transaction(function () use ($cashbook) {
    //             // Clear existing entries for this cashbook
    //             $cashbook->entries()->delete();

    //             $bankId = $cashbook->bank_activities_id;
    //             $accountNumber = $cashbook->bankAccount->account_number;
    //             $currentBankActivityId = $cashbook->bank_activities_id;

    //             // Format month for date comparison
    //             $formattedMonth = strlen($cashbook->month_id) == 1 ? "0{$cashbook->month_id}" : $cashbook->month_id;

    //             // Get date range for the month
    //             $startDate = "{$cashbook->year}-{$formattedMonth}-01";
    //             $endDate = date('Y-m-t', strtotime($startDate));

    //             // 1. Fetch and create entries for receipts
    //             $receipts = Receipt::where('account_number', $accountNumber)
    //                 ->whereBetween('receipt_date', [$startDate, $endDate])
    //                 ->where('status', 'Submitted')
    //                 ->get();

    //             foreach ($receipts as $receipt) {
    //                 // Create description from receipt data
    //                 $description = $receipt->activity ?? "Receipt #{$receipt->receipt_number}";
    //                 if ($receipt->classification) {
    //                     $description = "{$receipt->classification}: ".$description;
    //                 }

    //                 CashbookEntry::create([
    //                     // Core relationships
    //                     'cashbook_id' => $cashbook->id,
    //                     'bank_activities_id' => $bankId,
    //                     'user_id' => auth()->id(),

    //                     // Transaction information
    //                     'transaction_date' => $receipt->receipt_date,
    //                     'description' => $description,
    //                     'amount' => $receipt->amount,
    //                     'type' => 'receipt',

    //                     // Reference tracking
    //                     'reference_number' => $receipt->receipt_number,
    //                     'cheque_number' => null, // Receipts typically don't have cheque numbers

    //                     // Party information
    //                     'payer_name' => $receipt->account_name ?? 'N/A',
    //                     'payee_name' => null,

    //                     // Source document tracking
    //                     'source_type' => 'receipt',
    //                     'source_id' => $receipt->id,

    //                     // Classification
    //                     'category' => $receipt->classification ?? 'Revenue',
    //                     'sub_category' => $receipt->eco_code_item ?? $receipt->eco_code,

    //                     // Payment details
    //                     'payment_mode' => 'transfer', // Assuming receipts are bank transfers
    //                     'bank_name' => $receipt->bank_name,

    //                     // Status
    //                     'status' => 'posted',
    //                     'is_reconciled' => false,

    //                     // Metadata for additional data
    //                     'metadata' => [
    //                         'mda_name' => $receipt->mda_name,
    //                         'eco_code' => $receipt->eco_code,
    //                         'eco_code_item' => $receipt->eco_code_item,
    //                         'activity' => $receipt->activity,
    //                         'account_number' => $receipt->account_number,
    //                         'account_name' => $receipt->account_name,
    //                         'receipt_type' => $receipt->classification,
    //                     ],

    //                     // Remarks
    //                     'remarks' => $receipt->activity,
    //                 ]);
    //             }

    //             // 2. Fetch and create entries for vouchers (payments)
    //             $vouchers = Voucher::where('bank_activity_id', $bankId)
    //                 ->where('status', Voucher::STATUS_SUBMITTED)
    //                 ->whereBetween('voucher_date', [$startDate, $endDate])
    //                 ->get();

    //             foreach ($vouchers as $voucher) {
    //                 // Create description
    //                 $description = $voucher->narration ?? "Voucher #{$voucher->voucher_number}";

    //                 CashbookEntry::create([
    //                     // Core relationships
    //                     'cashbook_id' => $cashbook->id,
    //                     'bank_activities_id' => $bankId,
    //                     'user_id' => auth()->id(),

    //                     // Transaction information
    //                     'transaction_date' => $voucher->voucher_date,
    //                     'description' => $description,
    //                     'amount' => $voucher->total_amount,
    //                     'type' => 'payment',

    //                     // Reference tracking
    //                     'reference_number' => $voucher->voucher_number,
    //                     'cheque_number' => null, // Add if you have cheque number field in vouchers

    //                     // Party information
    //                     'payer_name' => null,
    //                     'payee_name' => $voucher->payee_name ?? 'N/A',

    //                     // Source document tracking
    //                     'source_type' => 'voucher',
    //                     'source_id' => $voucher->id,

    //                     // Classification (you might want to get this from voucher items)
    //                     'category' => 'Expense',
    //                     'sub_category' => $voucher->voucher_type,

    //                     // Payment details (assuming cheque for vouchers)
    //                     'payment_mode' => 'cheque',
    //                     'bank_name' => $voucher->bankActivity->bank_name ?? null, // Assuming relationship

    //                     // Status
    //                     'status' => 'posted',
    //                     'is_reconciled' => false,

    //                     // Metadata for additional data
    //                     'metadata' => [
    //                         'voucher_type' => $voucher->voucher_type,
    //                         'mda_id' => $voucher->mda_id,
    //                         'year_id' => $voucher->year_id,
    //                         'schedule_id' => $voucher->schedule_id,
    //                         'current_stage' => $voucher->current_stage,
    //                         'requires_retirement' => $voucher->requires_retirement,
    //                         'retired_at' => $voucher->retired_at,
    //                         'retirement_voucher_id' => $voucher->retirement_voucher_id,
    //                     ],

    //                     // Remarks
    //                     'remarks' => $voucher->narration,
    //                 ]);
    //             }

    //             // 3. Fetch and create entries for remittances
    //             // Remittances where this bank is the destination (credit side - money coming in)
    //             $incomingRemittances = Remittance::where('destination_bank_id', $currentBankActivityId)
    //                 ->whereBetween('transfer_date', [$startDate, $endDate])
    //                 ->where('status', 'Submitted')
    //                 ->get();

    //             foreach ($incomingRemittances as $remittance) {
    //                 // Create description for incoming remittance
    //                 $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Incoming)";

    //                 CashbookEntry::create([
    //                     // Core relationships
    //                     'cashbook_id' => $cashbook->id,
    //                     'bank_activities_id' => $currentBankActivityId,
    //                     'user_id' => auth()->id(),

    //                     // Transaction information
    //                     'transaction_date' => $remittance->transfer_date,
    //                     'description' => $description,
    //                     'amount' => $remittance->amount,
    //                     'type' => 'receipt', // Incoming remittance is like a receipt

    //                     // Reference tracking
    //                     'reference_number' => $remittance->receipt_number,
    //                     'cheque_number' => null,

    //                     // Party information - source bank is the payer
    //                     'payer_name' => $remittance->sourceBank->bank_name ?? 'Source Bank',
    //                     'payee_name' => null,

    //                     // Source document tracking
    //                     'source_type' => 'remittance',
    //                     'source_id' => $remittance->id,

    //                     // Classification
    //                     'category' => 'Transfer',
    //                     'sub_category' => 'Interbank Transfer',

    //                     // Payment details
    //                     'payment_mode' => 'transfer',
    //                     'bank_name' => $remittance->sourceBank->bank_name ?? null,

    //                     // Status
    //                     'status' => 'posted',
    //                     'is_reconciled' => false,

    //                     // Metadata for additional data
    //                     'metadata' => [
    //                         'remittance_type' => 'incoming',
    //                         'source_bank_id' => $remittance->source_bank_id,
    //                         'destination_bank_id' => $remittance->destination_bank_id,
    //                         'receipt_number' => $remittance->receipt_number,
    //                         'transfer_date' => $remittance->transfer_date,
    //                     ],

    //                     // Remarks
    //                     'remarks' => $remittance->narration ?? 'Interbank remittance received',
    //                 ]);
    //             }

    //             // Remittances where this bank is the source (debit side - money going out)
    //             $outgoingRemittances = Remittance::where('source_bank_id', $currentBankActivityId)
    //                 ->whereBetween('transfer_date', [$startDate, $endDate])
    //                 ->where('status', 'Submitted')
    //                 ->get();

    //             foreach ($outgoingRemittances as $remittance) {
    //                 // Create description for outgoing remittance
    //                 $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Outgoing)";

    //                 CashbookEntry::create([
    //                     // Core relationships
    //                     'cashbook_id' => $cashbook->id,
    //                     'bank_activities_id' => $currentBankActivityId,
    //                     'user_id' => auth()->id(),

    //                     // Transaction information
    //                     'transaction_date' => $remittance->transfer_date,
    //                     'description' => $description,
    //                     'amount' => $remittance->amount,
    //                     'type' => 'payment', // Outgoing remittance is like a payment

    //                     // Reference tracking
    //                     'reference_number' => $remittance->receipt_number,
    //                     'cheque_number' => null,

    //                     // Party information - destination bank is the payee
    //                     'payer_name' => null,
    //                     'payee_name' => $remittance->destinationBank->bank_name ?? 'Destination Bank',

    //                     // Source document tracking
    //                     'source_type' => 'remittance',
    //                     'source_id' => $remittance->id,

    //                     // Classification
    //                     'category' => 'Transfer',
    //                     'sub_category' => 'Interbank Transfer',

    //                     // Payment details
    //                     'payment_mode' => 'transfer',
    //                     'bank_name' => $remittance->destinationBank->bank_name ?? null,

    //                     // Status
    //                     'status' => 'posted',
    //                     'is_reconciled' => false,

    //                     // Metadata for additional data
    //                     'metadata' => [
    //                         'remittance_type' => 'outgoing',
    //                         'source_bank_id' => $remittance->source_bank_id,
    //                         'destination_bank_id' => $remittance->destination_bank_id,
    //                         'receipt_number' => $remittance->receipt_number,
    //                         'transfer_date' => $remittance->transfer_date,
    //                     ],

    //                     // Remarks
    //                     'remarks' => $remittance->narration ?? 'Interbank remittance sent',
    //                 ]);
    //             }

    //             // 4. Recalculate cashbook totals
    //             $totalReceipts = $cashbook->entries()->where('type', 'receipt')->sum('amount');
    //             $totalPayments = $cashbook->entries()->where('type', 'payment')->sum('amount');

    //             $closingBalance = ($cashbook->opening_balance + $totalReceipts) - $totalPayments;

    //             // Use the new method to update balances and carry forward
    //             $balances = $this->updateCashbookBalances($cashbook, $totalReceipts, $totalPayments);

    //             // $cashbook->update([
    //             //     'total_remittances' => $totalReceipts,
    //             //     'total_payments' => $totalPayments,
    //             //     'closing_balance' => $closingBalance,
    //             //     'status' => 'processed', // Mark as processed
    //             // ]);
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Cashbook entries generated successfully',
    //             'entries_count' => $cashbook->entries()->count(),
    //             'receipts_count' => $cashbook->entries()->where('type', 'receipt')->count(),
    //             'payments_count' => $cashbook->entries()->where('type', 'payment')->count(),
    //             'remittances_incoming_count' => $cashbook->entries()
    //                 ->where('source_type', 'remittance')
    //                 ->where('type', 'receipt')
    //                 ->count(),
    //             'remittances_outgoing_count' => $cashbook->entries()
    //                 ->where('source_type', 'remittance')
    //                 ->where('type', 'payment')
    //                 ->count(),
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Failed to generate entries: '.$e->getMessage(),
    //         ], 500);
    //     }
    // }

    public function generateEntries(Cashbook $cashbook)
    {
        // try {
        DB::transaction(function () use ($cashbook) {
            // Clear existing entries for this cashbook
            $cashbook->entries()->delete();

            $bankId = $cashbook->bank_activities_id;
            $accountNumber = $cashbook->bankAccount->account_number;
            $currentBankActivityId = $cashbook->bank_activities_id;

            // Format month for date comparison
            $formattedMonth = strlen($cashbook->month_id) == 1 ? "0{$cashbook->month_id}" : $cashbook->month_id;

            // Get date range for the month
            $startDate = "{$cashbook->year}-{$formattedMonth}-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            // 1. Fetch and create entries for receipts
            $receipts = Receipt::where('account_number', $accountNumber)
                ->whereBetween('receipt_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->get();

            foreach ($receipts as $receipt) {
                // Get economic code info from receipt
                $economicCode = EconomyCode::where('code', $receipt->eco_code)->first();
                $economicCodeItem = EconomyCodeItem::where('code', $receipt->eco_code_item)->first();

                // Create description from receipt data
                $description = $receipt->activity ?? "Receipt #{$receipt->receipt_number}";
                if ($receipt->classification) {
                    $description = "{$receipt->classification}: " . $description;
                }


                // dd($economicCode);

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $bankId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $receipt->receipt_date,
                    'description' => $description,
                    'amount' => $receipt->amount,
                    'type' => 'receipt',

                    // Reference tracking
                    'reference_number' => $receipt->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => $receipt->account_name ?? 'N/A',
                    'payee_name' => null,

                    // Source document tracking
                    'source_type' => 'receipt',
                    'source_id' => $receipt->id,

                    // Classification
                    'category' => $receipt->classification ?? 'Revenue',
                    'sub_category' => $receipt->eco_code_item ?? $receipt->eco_code,

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $receipt->bank_name,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'mda_name' => $receipt->mda_name,
                        'eco_code' => $receipt->eco_code,
                        'eco_code_item' => $receipt->eco_code_item,
                        'activity' => $receipt->activity,
                        'account_number' => $receipt->account_number,
                        'account_name' => $receipt->account_name,
                        'receipt_type' => $receipt->classification,
                        // Economic code information
                        'economic_code_id' => $receipt->economic_code_id,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $receipt->economy_code_item_id,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $receipt->classification,
                        'sub_classification' => $receipt->sub_classification,
                        // Bank info
                        'payer_bank_name' => $receipt->bank_name,
                        'payer_account_number' => $receipt->account_number,
                    ],

                    // Remarks
                    'remarks' => $receipt->activity,
                ]);
            }

            // 2. Fetch and create entries for vouchers (payments)
            // 2. Fetch and create entries for vouchers (payments)
            $vouchers = Voucher::where('bank_activity_id', $bankId)
                ->where(function ($query) {
                    $query->where('status', Voucher::STATUS_SUBMITTED)->orWhere('status', Voucher::ACTION_APPROVED);
                })
                // ->where('status', Voucher::STATUS_SUBMITTED)
                // ->orWhere('status', Voucher::ACTION_APPROVED)
                ->whereBetween('voucher_date', [$startDate, $endDate])
                ->with(['items']) // Only load voucher items
                ->get();

            foreach ($vouchers as $voucher) {
                // Initialize variables
                $isBankChargesVoucher = false;
                $economicCodeItemId = null;
                $economicCodeItemName = null;
                $economicCodeItemCode = null;
                $economicCodeId = null;
                $economicCodeName = null;
                $economicCodeCode = null;

                // Check the first voucher item for economic code information
                if ($voucher->items->isNotEmpty()) {
                    $firstItem = $voucher->items->first();

                    // Get economic code item details from economy_code_item_id
                    if ($firstItem->economy_code_item_id) {
                        $economicCodeItemId = $firstItem->economy_code_item_id;

                        // Get economic code item details
                        $economicCodeItem = EconomyCodeItem::find($economicCodeItemId);
                        if ($economicCodeItem) {
                            $economicCodeItemName = $economicCodeItem->name;
                            $economicCodeItemCode = $economicCodeItem->code;

                            // Get the parent economic code
                            $economicCode = $economicCodeItem->economyCode;
                            if ($economicCode) {
                                $economicCodeId = $economicCode->id;
                                $economicCodeName = $economicCode->name;
                                $economicCodeCode = $economicCode->code;

                                // Check if this is bank charges (ID 74 for economic_code, ID 364 for economic_code_item)
                                $isBankChargesVoucher = ($economicCodeId == 74 && $economicCodeItemId == 364);
                            }
                        }
                    }
                }

                // Create description
                $description = $voucher->narration ?? "Voucher #{$voucher->voucher_number}";
                if ($isBankChargesVoucher) {
                    $description = 'Bank Charges: ' . $description;
                }

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $bankId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $voucher->voucher_date,
                    'description' => $description,
                    'amount' => $voucher->total_amount,
                    'type' => 'payment',

                    // Reference tracking
                    'reference_number' => $voucher->voucher_number,
                    'cheque_number' => $voucher->cheque_number ?? null,

                    // Party information
                    'payer_name' => null,
                    'payee_name' => $voucher->payee_name ?? 'N/A',

                    // Source document tracking
                    'source_type' => 'voucher',
                    'source_id' => $voucher->id,

                    // Classification
                    'category' => $isBankChargesVoucher ? 'Bank Charges' : 'Expense',
                    'sub_category' => $voucher->voucher_type,

                    // Payment details
                    'payment_mode' => $voucher->payment_mode ?? 'cheque',
                    'bank_name' => $voucher->bankActivity->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'voucher_type' => $voucher->voucher_type,
                        'mda_id' => $voucher->mda_id,
                        'year_id' => $voucher->year_id,
                        'schedule_id' => $voucher->schedule_id,
                        'current_stage' => $voucher->current_stage,
                        'requires_retirement' => $voucher->requires_retirement,
                        'retired_at' => $voucher->retired_at,
                        'retirement_voucher_id' => $voucher->retirement_voucher_id,
                        // Economic code information
                        'economic_code_id' => $economicCodeId,
                        'economic_code_name' => $economicCodeName,
                        'economic_code_code' => $economicCodeCode,
                        'economic_code_item_id' => $economicCodeItemId,
                        'economic_code_item_name' => $economicCodeItemName,
                        'economic_code_item_code' => $economicCodeItemCode,
                        // Bank charges flag
                        'is_bank_charges' => $isBankChargesVoucher,
                        'classification' => $voucher->classification ?? ($isBankChargesVoucher ? 'Bank Charges' : 'Expense'),
                        'sub_classification' => $voucher->sub_classification ?? $voucher->voucher_type,
                        // Bank info
                        'bank_account_number' => $voucher->bankActivity ? $voucher->bankActivity->account_number : null,
                        'bank_name' => $voucher->bankActivity ? $voucher->bankActivity->bank_name : null,
                    ],

                    // Remarks
                    'remarks' => $voucher->narration,
                ]);
            }

            // 3. Fetch and create entries for remittances
            // Remittances where this bank is the destination (credit side - money coming in)
            $incomingRemittances = Remittance::where('destination_bank_id', $currentBankActivityId)
                ->whereBetween('transfer_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->with(['sourceBank', 'destinationBank'])
                ->get();

            foreach ($incomingRemittances as $remittance) {
                // Get economic code info from remittance
                $economicCode = null;
                $economicCodeItem = null;

                // Try to find economic code item by destination account number
                if ($remittance->destinationBank && $remittance->destinationBank->account_number) {
                    $accountNumber = $remittance->destinationBank->account_number;

                    // Look for economic code item where name contains the account number
                    $economicCodeItem = EconomyCodeItem::where('name', 'LIKE', "%{$accountNumber}%")->first();

                    if (isset($economicCodeItem)) {
                        // dd('A1');
                        // Get the parent economic code
                        $economicCode = $economicCodeItem->economyCode;
                        $economicCode->status = 'inactive';
                        $economicCode->update();
                    }
                }

                // Create description for incoming remittance
                $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Incoming)";

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $remittance->transfer_date,
                    'description' => $description,
                    'amount' => $remittance->amount,
                    'type' => 'receipt',

                    // Reference tracking
                    'reference_number' => $remittance->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => $remittance->sourceBank->bank_name ?? 'Source Bank',
                    'payee_name' => null,

                    // Source document tracking
                    'source_type' => 'remittance',
                    'source_id' => $remittance->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $remittance->sourceBank->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'remittance_type' => 'incoming',
                        'source_bank_id' => $remittance->source_bank_id,
                        'destination_bank_id' => $remittance->destination_bank_id,
                        'receipt_number' => $remittance->receipt_number,
                        'transfer_date' => $remittance->transfer_date,
                        // Economic code information
                        'economic_code_id' => $economicCode ? $economicCode->id : null,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $economicCodeItem ? $economicCodeItem->id : null,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $remittance->classification,
                        'sub_classification' => $remittance->sub_classification,
                        // Source bank info
                        'source_bank_account_number' => $remittance->sourceBank ? $remittance->sourceBank->account_number : null,
                        'source_bank_name' => $remittance->sourceBank ? $remittance->sourceBank->bank_name : null,
                        // Destination bank info
                        'destination_bank_account_number' => $remittance->destinationBank ? $remittance->destinationBank->account_number : null,
                        'destination_bank_name' => $remittance->destinationBank ? $remittance->destinationBank->bank_name : null,
                        // Account number used for lookup
                        'lookup_account_number' => $accountNumber ?? null,
                    ],

                    // Remarks
                    'remarks' => $remittance->narration ?? 'Interbank remittance received',
                ]);
            }

            // Remittances where this bank is the source (debit side - money going out)
            $outgoingRemittances = Remittance::where('source_bank_id', $currentBankActivityId)
                ->whereBetween('transfer_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->with(['sourceBank', 'destinationBank'])
                ->get();

            foreach ($outgoingRemittances as $remittance) {
                // Get economic code info from remittance
                $economicCode = null;
                $economicCodeItem = null;

                // Try to find economic code item by source account number
                if ($remittance->sourceBank && $remittance->sourceBank->account_number) {
                    $accountNumber = $remittance->sourceBank->account_number;

                    // Look for economic code item where name contains the account number
                    $economicCodeItem = EconomyCodeItem::where('name', 'LIKE', "%{$accountNumber}%")->first();

                    if (isset($economicCodeItem)) {
                        // dd('A2');
                        // Get the parent economic code
                        $economicCode = $economicCodeItem->economyCode;
                        $economicCode->status = 'inactive';
                        $economicCode->update();
                    }
                }

                // Create description for outgoing remittance
                $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Outgoing)";

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $remittance->transfer_date,
                    'description' => $description,
                    'amount' => $remittance->amount,
                    'type' => 'payment',

                    // Reference tracking
                    'reference_number' => $remittance->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => null,
                    'payee_name' => $remittance->destinationBank->bank_name ?? 'Destination Bank',

                    // Source document tracking
                    'source_type' => 'remittance',
                    'source_id' => $remittance->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $remittance->destinationBank->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'remittance_type' => 'outgoing',
                        'source_bank_id' => $remittance->source_bank_id,
                        'destination_bank_id' => $remittance->destination_bank_id,
                        'receipt_number' => $remittance->receipt_number,
                        'transfer_date' => $remittance->transfer_date,
                        // Economic code information
                        'economic_code_id' => $economicCode ? $economicCode->id : null,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $economicCodeItem ? $economicCodeItem->id : null,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $remittance->classification,
                        'sub_classification' => $remittance->sub_classification,
                        // Source bank info
                        'source_bank_account_number' => $remittance->sourceBank ? $remittance->sourceBank->account_number : null,
                        'source_bank_name' => $remittance->sourceBank ? $remittance->sourceBank->bank_name : null,
                        // Destination bank info
                        'destination_bank_account_number' => $remittance->destinationBank ? $remittance->destinationBank->account_number : null,
                        'destination_bank_name' => $remittance->destinationBank ? $remittance->destinationBank->bank_name : null,
                        // Account number used for lookup
                        'lookup_account_number' => $accountNumber ?? null,
                    ],

                    // Remarks
                    'remarks' => $remittance->narration ?? 'Interbank remittance sent',
                ]);
            }






            $economicCode = $cashbook->bankAccount->economic_code;

            $startDate = "{$cashbook->year}/{$cashbook->month_id}/01";
            $endDate = "{$cashbook->year}/{$cashbook->month_id}/31";
            $journalEntries = JournalEntry::where('account_code', $economicCode)
                ->whereHas('journal', function ($journal) use ($startDate, $endDate) {
                    $journal->where('status', 'approved')
                        ->whereBetween('journal_date', [$startDate, $endDate]);
                })->get();
            // $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$startDate, $endDate])->where('status', 'approved')->get();

            $journalCredits = 0.00;
            $journalDebits = 0.00;
            // if ($journalEntries->isNotEmpty()) {
            //     dd($economicCode, $journalEntries);
            // }
            foreach ($journalEntries as $journalEntry) {


                // Create description for outgoing remittance
                $description = $journalEntry->description;

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $journalEntry->journal->journal_date,
                    'description' => $description,
                    'amount' => floatVal($journalEntry->credit_amount) > 0 ? $journalEntry->credit_amount : $journalEntry->debit_amount,
                    'type' => floatVal($journalEntry->credit_amount) > 0 ? 'payment' : 'receipt',

                    // Reference tracking
                    'reference_number' => $journalEntry->journal->journal_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => 'Journal '  . $journalEntry->journal->journal_type,
                    'payee_name' => 'Journal',

                    // Source document tracking
                    'source_type' => 'journal ' .  $journalEntry->journal->journal_type,
                    'source_id' => $journalEntry->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer', // $journalEntry->journal->journal_type,
                    'bank_name' => BankActivity::where('id', $currentBankActivityId)->first()?->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'journal_type' => floatVal($journalEntry->credit_amount) > 0 ? 'outflow' : 'inflow',
                        'journal_id' => $journalEntry->journal->journal_number,
                        'destination_bank_id' => BankActivity::where('id', $currentBankActivityId)->first()?->id,
                        'journal_number' => $journalEntry->journal->journal_number,
                        'journal_type' => $journalEntry->journal->journal_type,
                        'journal_date' => $journalEntry->journal->journal_date,
                        'journal_entry_id' => $journalEntry->id,
                        'journal_entry_credit' => $journalEntry->credit_amount,
                        'journal_entry_debit' => $journalEntry->debit_amount,
                        'journal_entry_description' => $journalEntry->description,
                        'journal_entry_economic_code' => $journalEntry->account_code,
                        'journal_entry_line_number' => $journalEntry->line_number,


                    ],

                    // Remarks
                    'remarks' => $journalEntry->journal->remarks ?? 'Journal operation',
                ]);
            }






            // 4. Recalculate cashbook totals
            $totalReceipts = $cashbook->entries()->where('type', 'receipt')->sum('amount');
            $totalPayments = $cashbook->entries()->where('type', 'payment')->sum('amount');

            // Use the new method to update balances and carry forward
            $balances = $this->updateCashbookBalances($cashbook, $totalReceipts, $totalPayments);
        });

        return response()->json([
            'success' => true,
            'message' => 'Cashbook entries generated successfully',
            'entries_count' => $cashbook->entries()->count(),
            'receipts_count' => $cashbook->entries()->where('type', 'receipt')->count(),
            'payments_count' => $cashbook->entries()->where('type', 'payment')->count(),
            'remittances_incoming_count' => $cashbook->entries()
                ->where('source_type', 'remittance')
                ->where('type', 'receipt')
                ->count(),
            'remittances_outgoing_count' => $cashbook->entries()
                ->where('source_type', 'remittance')
                ->where('type', 'payment')
                ->count(),
        ]);

        // } catch (\Exception $e) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Failed to generate entries: '.$e->getMessage(),
        //     ], 500);
        // }
    }

    /**
     * Generate transaction view for a specific month and year
     */
    public function GenerateTransaction($month_id, $year, Request $request)
    {
        try {
            // Get the cashbook record
            $bankId = $request->get('account_id');

            if ($bankId) {
                // Get specific account's cashbook
                $cashbook = Cashbook::with(['bankAccount', 'entries'])
                    ->where('month_id', $month_id)
                    ->where('year', $year)
                    ->where('bank_activities_id', $bankId)
                    ->firstOrFail();
            } else {
                // Get first cashbook (for backward compatibility)
                $cashbook = Cashbook::with(['bankAccount', 'entries'])
                    ->where('month_id', $month_id)
                    ->where('year', $year)
                    ->firstOrFail();
            }

            // dd($cashbook);

            // Get month name
            $monthNames = [
                1 => 'January',
                2 => 'February',
                3 => 'March',
                4 => 'April',
                5 => 'May',
                6 => 'June',
                7 => 'July',
                8 => 'August',
                9 => 'September',
                10 => 'October',
                11 => 'November',
                12 => 'December',
            ];
            $monthName = $monthNames[$month_id] ?? 'Unknown';

            // Format dates
            $formattedMonth = strlen($month_id) == 1 ? "0{$month_id}" : $month_id;
            $startDate = "{$year}-{$formattedMonth}-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            // Get entries with proper field mapping
            $entries = $cashbook->entries()
                ->orderBy('transaction_date')
                ->orderBy('created_at')
                ->get();

            // dd($entries);

            // Format receipts for the view - EXACT field names as Vue expects
            $receiptsArray = [];
            if ($entries->where('type', 'receipt')->isNotEmpty()) {
                $receiptsArray = $entries->where('type', 'receipt')->map(function ($entry) {
                    $metadata = $entry->metadata ?? [];

                    return [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date,
                        'cb_sn' => $entry->reference_number ?? $metadata['receipt_number'] ?? '', // CB S/N
                        'payer_name' => $entry->payer_name ?? $metadata['account_name'] ?? 'N/A',
                        'classification_title' => $entry->category ?? $metadata['receipt_type'] ?? $entry->description,
                        'sub_category' => $metadata['eco_code_item'] ?? '', // Classification number
                        'receipt_no' => $entry->reference_number ?? $metadata['receipt_number'] ?? '',
                        'amount' => (float) $entry->amount,
                        'source_id' => $entry->source_id,
                        'source_type' => $entry->source_type,
                    ];
                })->values()->all() ?? [];
            }

            // Format payments for the view - EXACT field names as Vue expects
            $paymentsArray = [];
            if ($entries->where('type', 'payment')->isNotEmpty()) {
                $paymentsArray = $entries->where('type', 'payment')->map(function ($entry) {
                    $metadata = $entry->metadata ?? [];

                    return [
                        'id' => $entry->id,
                        'transaction_date' => $entry->transaction_date,
                        'cb_sn' => $entry->reference_number ?? $metadata['voucher_number'] ?? '', // CB S/N
                        'department_number' => $metadata['mda_id'] ?? '', // Dept No.
                        'payee_name' => $entry->payee_name ?? $metadata['payee_name'] ?? 'N/A',
                        'classification_title' => $entry->description,
                        'sub_category' => $entry['eco_code_item'] ?? '', // Classification number
                        'cheque_no' => $entry->reference_number, // Cheque No. (using voucher number)
                        'amount' => (float) $entry->amount,
                        'source_id' => $entry->source_id,
                        'source_type' => $entry->source_type,
                    ];
                })->values()->all() ?? [];
            }

            // Calculate totals
            $totalReceipts = collect($receiptsArray)->sum('amount');
            $totalPayments = collect($paymentsArray)->sum('amount');
            $totalDebitSide = (float) $cashbook->opening_balance + $totalReceipts;
            $balanceCD = $totalDebitSide - $totalPayments;

            return Inertia::render('admin/cashbook/entries', [
                'cashbook' => array_merge($cashbook->toArray(), [
                    'month_name' => $monthName,
                    'month_id' => $month_id,
                    'year' => $year,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'opening_balance' => (float) $cashbook->opening_balance,
                    'bank_name' => $cashbook->bankAccount->bank_name ?? 'N/A',
                    'account_number' => $cashbook->bankAccount->account_number ?? 'N/A',
                ]),
                'receipts' => $receiptsArray,
                'payments' => $paymentsArray,
            ]);
        } catch (\Exception $e) {
            \Log::error('Cashbook GenerateTransaction Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'month_id' => $month_id,
                'year' => $year,
                'account_id' => $request->get('account_id'),
            ]);

            // Return a safe response with empty arrays
            return Inertia::render('admin/cashbook/entries', [
                'cashbook' => [
                    'month_id' => $month_id,
                    'year' => $year,
                    'month_name' => $monthNames[$month_id] ?? 'Unknown',
                    'opening_balance' => 0,
                    'bank_name' => 'Error loading account',
                    'account_number' => 'N/A',
                ],
                'receipts' => [],
                'payments' => [],
            ]);
        }
    }

    /**
     * Get detailed entry view
     */
    public function showEntry(CashbookEntry $entry)
    {
        $entry->load(['cashbook', 'bankAccount', 'createdBy']);

        return Inertia::render('admin/cashbook/EntryDetail', [
            'entry' => $entry,
            'metadata' => $entry->metadata ?? [],
        ]);
    }

    /**
     * Update entry status (e.g., mark as reconciled)
     */
    public function updateEntryStatus(Request $request, CashbookEntry $entry)
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,posted,reconciled,cancelled',
            'is_reconciled' => 'boolean',
            'reconciliation_date' => 'nullable|date',
        ]);

        $entry->update($validated);

        // If marking as reconciled, update the cashbook
        if ($validated['is_reconciled']) {
            $cashbook = $entry->cashbook;
            $cashbook->update([
                'is_reconciled' => $cashbook->entries()->where('is_reconciled', false)->count() === 0,
            ]);
        }

        return back()->with('success', 'Entry status updated');
    }

    /**
     * Search entries
     */
    public function searchEntries(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string',
            'type' => 'nullable|in:receipt,payment',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'bank_account_id' => 'nullable|exists:bank_activities,id',
        ]);

        $query = CashbookEntry::with(['cashbook', 'bankAccount']);

        if (! empty($validated['search'])) {
            $query->where(function ($q) use ($validated) {
                $q->where('reference_number', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('description', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('payer_name', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('payee_name', 'LIKE', "%{$validated['search']}%")
                    ->orWhere('cheque_number', 'LIKE', "%{$validated['search']}%");
            });
        }

        if (! empty($validated['type'])) {
            $query->where('type', $validated['type']);
        }

        if (! empty($validated['start_date'])) {
            $query->where('transaction_date', '>=', $validated['start_date']);
        }

        if (! empty($validated['end_date'])) {
            $query->where('transaction_date', '<=', $validated['end_date']);
        }

        if (! empty($validated['bank_account_id'])) {
            $query->where('bank_activities_id', $validated['bank_account_id']);
        }

        $entries = $query->orderBy('transaction_date', 'desc')
            ->paginate(50);

        return Inertia::render('admin/cashbook/EntrySearch', [
            'entries' => $entries,
            'filters' => $validated,
        ]);
    }

    /**
     * Generate entries for multiple cashbooks across all bank accounts
     */
    public function generateBatchEntries(Request $request)
    {
        try {
            $validated = $request->validate([
                'month_ids' => 'required|array',
                'month_ids.*' => 'integer|min:1|max:12',
                'year' => 'required|integer',
                'cashbook_financial_year_id' => 'required|exists:cashbook_financial_years,id',
            ]);

            \Log::info('Batch generation request received:', [
                'month_ids' => $validated['month_ids'],
                'year' => $validated['year'],
                'cashbook_financial_year_id' => $validated['cashbook_financial_year_id'],
                'user_id' => auth()->id(),
            ]);

            $results = [];
            $totalEntries = 0;
            $successCount = 0;
            $failureCount = 0;
            $totalCashbooksProcessed = 0;

            // Get all active bank accounts for this financial year
            $bankAccounts = BankActivity::where('status', 1)->get();



            \Log::info('Found bank accounts for batch:', [
                'bank_accounts_count' => $bankAccounts->count(),
                'bank_accounts' => $bankAccounts->pluck('id')->toArray(),
            ]);

            DB::transaction(function () use ($validated, $bankAccounts, &$results, &$totalEntries, &$successCount, &$failureCount, &$totalCashbooksProcessed) {
                foreach ($validated['month_ids'] as $monthId) {
                    foreach ($bankAccounts as $bankAccount) {
                        $totalCashbooksProcessed++;

                        // Find cashbook for this month/account/year/financial year
                        $cashbook = Cashbook::where([
                            'month_id' => $monthId,
                            'year' => $validated['year'],
                            'bank_activities_id' => $bankAccount->id,
                            'cashbook_financial_year_id' => $validated['cashbook_financial_year_id'],
                        ])->first();

                        if (! $cashbook) {
                            $results[] = [
                                'month_id' => $monthId,
                                'month_name' => $this->getMonthName($monthId),
                                'bank_account_id' => $bankAccount->id,
                                'bank_account_name' => $bankAccount->title,
                                'success' => false,
                                'message' => 'Cashbook not found for this month and account. Bank Activity ID: ' . $bankAccount->id . ' Title: ' . $bankAccount->title,
                                'entries_count' => 0,
                            ];
                            $failureCount++;

                            continue;
                        }

                        // Generate entries for this cashbook
                        $result = $this->generateSingleCashbook($cashbook);
                        $results[] = array_merge([
                            'month_id' => $monthId,
                            'month_name' => $this->getMonthName($monthId),
                            'bank_account_id' => $bankAccount->id,
                            'bank_account_name' => $bankAccount->title,
                            'account_number' => $bankAccount->account_number,
                        ], $result);

                        if ($result['success']) {
                            $totalEntries += $result['entries_count'];
                            $successCount++;
                        } else {
                            $failureCount++;
                        }
                    }
                }
            });

            \Log::info('Batch generation completed:', [
                'total_cashbooks_processed' => $totalCashbooksProcessed,
                'successful' => $successCount,
                'failed' => $failureCount,
                'total_entries' => $totalEntries,
            ]);

            return response()->json([
                'success' => true,
                'message' => sprintf(
                    'Batch generation completed. Processed %d cashbooks (%d months × %d accounts). %d succeeded, %d failed. Total entries: %d',
                    $totalCashbooksProcessed,
                    count($validated['month_ids']),
                    $bankAccounts->count(),
                    $successCount,
                    $failureCount,
                    $totalEntries
                ),
                'summary' => [
                    'total_months' => count($validated['month_ids']),
                    'total_accounts' => $bankAccounts->count(),
                    'total_cashbooks' => $totalCashbooksProcessed,
                    'successful' => $successCount,
                    'failed' => $failureCount,
                    'total_entries' => $totalEntries,
                ],
                'results' => $results,
            ]);
        } catch (\Exception $e) {

            // dd($e);
            \Log::error('Batch generation failed:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate batch entries: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Helper method to generate entries for a single cashbook
     */
    // private function generateSingleCashbook(Cashbook $cashbook)
    // {
    //     try {
    //         // Clear existing entries for this cashbook
    //         $cashbook->entries()->delete();

    //         $bankId = $cashbook->bank_activities_id;
    //         $accountNumber = $cashbook->bankAccount->account_number;
    //         $currentBankActivityId = $cashbook->bank_activities_id;

    //         // Format month for date comparison
    //         $formattedMonth = strlen($cashbook->month_id) == 1 ? "0{$cashbook->month_id}" : $cashbook->month_id;

    //         // Get date range for the month
    //         $startDate = "{$cashbook->year}-{$formattedMonth}-01";
    //         $endDate = date('Y-m-t', strtotime($startDate));

    //         $entriesCreated = 0;

    //         // 1. Fetch and create entries for receipts
    //         $receipts = Receipt::where('account_number', $accountNumber)
    //             ->whereBetween('receipt_date', [$startDate, $endDate])
    //             ->where('status', 'Submitted')
    //             ->get();

    //         foreach ($receipts as $receipt) {
    //             // Create description from receipt data
    //             $description = $receipt->activity ?? "Receipt #{$receipt->receipt_number}";
    //             if ($receipt->classification) {
    //                 $description = "{$receipt->classification}: ".$description;
    //             }

    //             CashbookEntry::create([
    //                 // Core relationships
    //                 'cashbook_id' => $cashbook->id,
    //                 'bank_activities_id' => $bankId,
    //                 'user_id' => auth()->id(),

    //                 // Transaction information
    //                 'transaction_date' => $receipt->receipt_date,
    //                 'description' => $description,
    //                 'amount' => $receipt->amount,
    //                 'type' => 'receipt',

    //                 // Reference tracking
    //                 'reference_number' => $receipt->receipt_number,
    //                 'cheque_number' => null,

    //                 // Party information
    //                 'payer_name' => $receipt->account_name ?? 'N/A',
    //                 'payee_name' => null,

    //                 // Source document tracking
    //                 'source_type' => 'receipt',
    //                 'source_id' => $receipt->id,

    //                 // Classification
    //                 'category' => $receipt->classification ?? 'Revenue',
    //                 'sub_category' => $receipt->eco_code_item ?? $receipt->eco_code,

    //                 // Payment details
    //                 'payment_mode' => 'transfer',
    //                 'bank_name' => $receipt->bank_name,

    //                 // Status
    //                 'status' => 'posted',
    //                 'is_reconciled' => false,

    //                 // Metadata for additional data
    //                 'metadata' => [
    //                     'mda_name' => $receipt->mda_name,
    //                     'eco_code' => $receipt->eco_code,
    //                     'eco_code_item' => $receipt->eco_code_item,
    //                     'activity' => $receipt->activity,
    //                     'account_number' => $receipt->account_number,
    //                     'account_name' => $receipt->account_name,
    //                     'receipt_type' => $receipt->classification,
    //                 ],

    //                 // Remarks
    //                 'remarks' => $receipt->activity,
    //             ]);
    //             $entriesCreated++;
    //         }

    //         // 2. Fetch and create entries for vouchers (payments)
    //         $vouchers = Voucher::where('bank_activity_id', $bankId)
    //             ->where('status', Voucher::STATUS_SUBMITTED)
    //             ->whereBetween('voucher_date', [$startDate, $endDate])
    //             ->get();

    //         foreach ($vouchers as $voucher) {
    //             // Create description
    //             $description = $voucher->narration ?? "Voucher #{$voucher->voucher_number}";

    //             CashbookEntry::create([
    //                 // Core relationships
    //                 'cashbook_id' => $cashbook->id,
    //                 'bank_activities_id' => $bankId,
    //                 'user_id' => auth()->id(),

    //                 // Transaction information
    //                 'transaction_date' => $voucher->voucher_date,
    //                 'description' => $description,
    //                 'amount' => $voucher->total_amount,
    //                 'type' => 'payment',

    //                 // Reference tracking
    //                 'reference_number' => $voucher->voucher_number,
    //                 'cheque_number' => null,

    //                 // Party information
    //                 'payer_name' => null,
    //                 'payee_name' => $voucher->payee_name ?? 'N/A',

    //                 // Source document tracking
    //                 'source_type' => 'voucher',
    //                 'source_id' => $voucher->id,

    //                 // Classification
    //                 'category' => 'Expense',
    //                 'sub_category' => $voucher->voucher_type,

    //                 // Payment details
    //                 'payment_mode' => 'cheque',
    //                 'bank_name' => $voucher->bankActivity->bank_name ?? null,

    //                 // Status
    //                 'status' => 'posted',
    //                 'is_reconciled' => false,

    //                 // Metadata for additional data
    //                 'metadata' => [
    //                     'voucher_type' => $voucher->voucher_type,
    //                     'mda_id' => $voucher->mda_id,
    //                     'year_id' => $voucher->year_id,
    //                     'schedule_id' => $voucher->schedule_id,
    //                     'current_stage' => $voucher->current_stage,
    //                     'requires_retirement' => $voucher->requires_retirement,
    //                     'retired_at' => $voucher->retired_at,
    //                     'retirement_voucher_id' => $voucher->retirement_voucher_id,
    //                 ],

    //                 // Remarks
    //                 'remarks' => $voucher->narration,
    //             ]);
    //             $entriesCreated++;
    //         }

    //         // 3. Fetch and create entries for remittances
    //         // Remittances where this bank is the destination (credit side - money coming in)
    //         $incomingRemittances = Remittance::where('destination_bank_id', $currentBankActivityId)
    //             ->whereBetween('transfer_date', [$startDate, $endDate])
    //             ->where('status', 'Submitted')
    //             ->get();

    //         foreach ($incomingRemittances as $remittance) {
    //             // Create description for incoming remittance
    //             $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Incoming)";

    //             CashbookEntry::create([
    //                 // Core relationships
    //                 'cashbook_id' => $cashbook->id,
    //                 'bank_activities_id' => $currentBankActivityId,
    //                 'user_id' => auth()->id(),

    //                 // Transaction information
    //                 'transaction_date' => $remittance->transfer_date,
    //                 'description' => $description,
    //                 'amount' => $remittance->amount,
    //                 'type' => 'receipt',

    //                 // Reference tracking
    //                 'reference_number' => $remittance->receipt_number,
    //                 'cheque_number' => null,

    //                 // Party information
    //                 'payer_name' => $remittance->sourceBank->bank_name ?? 'Source Bank',
    //                 'payee_name' => null,

    //                 // Source document tracking
    //                 'source_type' => 'remittance',
    //                 'source_id' => $remittance->id,

    //                 // Classification
    //                 'category' => 'Transfer',
    //                 'sub_category' => 'Interbank Transfer',

    //                 // Payment details
    //                 'payment_mode' => 'transfer',
    //                 'bank_name' => $remittance->sourceBank->bank_name ?? null,

    //                 // Status
    //                 'status' => 'posted',
    //                 'is_reconciled' => false,

    //                 // Metadata for additional data
    //                 'metadata' => [
    //                     'remittance_type' => 'incoming',
    //                     'source_bank_id' => $remittance->source_bank_id,
    //                     'destination_bank_id' => $remittance->destination_bank_id,
    //                     'receipt_number' => $remittance->receipt_number,
    //                     'transfer_date' => $remittance->transfer_date,
    //                 ],

    //                 // Remarks
    //                 'remarks' => $remittance->narration ?? 'Interbank remittance received',
    //             ]);
    //             $entriesCreated++;
    //         }

    //         // Remittances where this bank is the source (debit side - money going out)
    //         $outgoingRemittances = Remittance::where('source_bank_id', $currentBankActivityId)
    //             ->whereBetween('transfer_date', [$startDate, $endDate])
    //             ->where('status', 'Submitted')
    //             ->get();

    //         foreach ($outgoingRemittances as $remittance) {
    //             // Create description for outgoing remittance
    //             $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Outgoing)";

    //             CashbookEntry::create([
    //                 // Core relationships
    //                 'cashbook_id' => $cashbook->id,
    //                 'bank_activities_id' => $currentBankActivityId,
    //                 'user_id' => auth()->id(),

    //                 // Transaction information
    //                 'transaction_date' => $remittance->transfer_date,
    //                 'description' => $description,
    //                 'amount' => $remittance->amount,
    //                 'type' => 'payment',

    //                 // Reference tracking
    //                 'reference_number' => $remittance->receipt_number,
    //                 'cheque_number' => null,

    //                 // Party information
    //                 'payer_name' => null,
    //                 'payee_name' => $remittance->destinationBank->bank_name ?? 'Destination Bank',

    //                 // Source document tracking
    //                 'source_type' => 'remittance',
    //                 'source_id' => $remittance->id,

    //                 // Classification
    //                 'category' => 'Transfer',
    //                 'sub_category' => 'Interbank Transfer',

    //                 // Payment details
    //                 'payment_mode' => 'transfer',
    //                 'bank_name' => $remittance->destinationBank->bank_name ?? null,

    //                 // Status
    //                 'status' => 'posted',
    //                 'is_reconciled' => false,

    //                 // Metadata for additional data
    //                 'metadata' => [
    //                     'remittance_type' => 'outgoing',
    //                     'source_bank_id' => $remittance->source_bank_id,
    //                     'destination_bank_id' => $remittance->destination_bank_id,
    //                     'receipt_number' => $remittance->receipt_number,
    //                     'transfer_date' => $remittance->transfer_date,
    //                 ],

    //                 // Remarks
    //                 'remarks' => $remittance->narration ?? 'Interbank remittance sent',
    //             ]);
    //             $entriesCreated++;
    //         }

    //         // 4. Recalculate cashbook totals
    //         $totalReceipts = $cashbook->entries()->where('type', 'receipt')->sum('amount');
    //         $totalPayments = $cashbook->entries()->where('type', 'payment')->sum('amount');

    //         // $closingBalance = ($cashbook->opening_balance + $totalReceipts) - $totalPayments;

    //         // Use the new method to update balances and carry forward
    //         $balances = $this->updateCashbookBalances($cashbook, $totalReceipts, $totalPayments);
    //         $closingBalance = $balances['closing_balance'];

    //         // $cashbook->update([
    //         //     'total_remittances' => $totalReceipts,
    //         //     'total_payments' => $totalPayments,
    //         //     'closing_balance' => $closingBalance,
    //         //     'status' => 'processed',
    //         // ]);

    //         return [
    //             'success' => true,
    //             'message' => 'Cashbook entries generated successfully',
    //             'entries_count' => $entriesCreated,
    //             'receipts_count' => $cashbook->entries()->where('type', 'receipt')->count(),
    //             'payments_count' => $cashbook->entries()->where('type', 'payment')->count(),
    //             'remittances_incoming_count' => $cashbook->entries()
    //                 ->where('source_type', 'remittance')
    //                 ->where('type', 'receipt')
    //                 ->count(),
    //             'remittances_outgoing_count' => $cashbook->entries()
    //                 ->where('source_type', 'remittance')
    //                 ->where('type', 'payment')
    //                 ->count(),
    //             'total_receipts' => $totalReceipts,
    //             'total_payments' => $totalPayments,
    //             'closing_balance' => $closingBalance,
    //         ];

    //     } catch (\Exception $e) {
    //         return [
    //             'success' => false,
    //             'message' => 'Failed to generate entries: '.$e->getMessage(),
    //             'error' => $e->getMessage(),
    //             'entries_count' => 0,
    //         ];
    //     }
    // }

    private function generateSingleCashbook(Cashbook $cashbook)
    {
        // dd($cashbook);
        try {
            // Clear existing entries for this cashbook
            $cashbook->entries()->delete();

            $bankId = $cashbook->bank_activities_id;
            $accountNumber = $cashbook->bankAccount->account_number;
            $currentBankActivityId = $cashbook->bank_activities_id;

            // Format month for date comparison
            $formattedMonth = strlen($cashbook->month_id) == 1 ? "0{$cashbook->month_id}" : $cashbook->month_id;

            // Get date range for the month
            $startDate = "{$cashbook->year}-{$formattedMonth}-01";
            $endDate = date('Y-m-t', strtotime($startDate));

            $entriesCreated = 0;

            // 1. Fetch and create entries for receipts
            $receipts = Receipt::where('account_number', $accountNumber)
                ->whereBetween('receipt_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->get();

            foreach ($receipts as $receipt) {
                // Get economic code info from receipt
                $economicCode = EconomyCode::where('code', $receipt->eco_code)->first();
                $economicCodeItem = EconomyCodeItem::where('code', $receipt->eco_code_item)->first();

                // Create description from receipt data
                $description = $receipt->activity ?? "Receipt #{$receipt->receipt_number}";
                if ($receipt->classification) {
                    $description = "{$receipt->classification}: " . $description;
                }

                // dd($economicCode);

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $bankId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $receipt->receipt_date,
                    'description' => $description,
                    'amount' => $receipt->amount,
                    'type' => 'receipt',

                    // Reference tracking
                    'reference_number' => $receipt->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => $receipt->account_name ?? 'N/A',
                    'payee_name' => null,

                    // Source document tracking
                    'source_type' => 'receipt',
                    'source_id' => $receipt->id,

                    // Classification
                    'category' => $receipt->classification ?? 'Revenue',
                    'sub_category' => $receipt->eco_code_item ?? $receipt->eco_code,

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $receipt->bank_name,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'mda_name' => $receipt->mda_name,
                        'eco_code' => $receipt->eco_code,
                        'eco_code_item' => $receipt->eco_code_item,
                        'activity' => $receipt->activity,
                        'account_number' => $receipt->account_number,
                        'account_name' => $receipt->account_name,
                        'receipt_type' => $receipt->classification,
                        // Economic code information
                        'economic_code_id' => $receipt->economic_code_id,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $receipt->economy_code_item_id,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $receipt->classification,
                        'sub_classification' => $receipt->sub_classification,
                        // Bank info
                        'payer_bank_name' => $receipt->bank_name,
                        'payer_account_number' => $receipt->account_number,
                    ],

                    // Remarks
                    'remarks' => $receipt->activity,
                ]);
                $entriesCreated++;
            }

            // 2. Fetch and create entries for vouchers (payments)
            // 2. Fetch and create entries for vouchers (payments)
            $vouchers = Voucher::where('bank_activity_id', $bankId)
                ->where('status', Voucher::STATUS_SUBMITTED)
                ->whereBetween('voucher_date', [$startDate, $endDate])
                ->with(['items'])
                ->get();

            foreach ($vouchers as $voucher) {
                // Initialize variables
                $isBankChargesVoucher = false;
                $economicCodeItemId = null;
                $economicCodeItemName = null;
                $economicCodeItemCode = null;
                $economicCodeId = null;
                $economicCodeName = null;
                $economicCodeCode = null;

                // Check the first voucher item for economic code information
                if ($voucher->items->isNotEmpty()) {
                    $firstItem = $voucher->items->first();

                    // Get economic code item details from economy_code_item_id
                    if ($firstItem->economy_code_item_id) {
                        $economicCodeItemId = $firstItem->economy_code_item_id;

                        // Get economic code item details
                        $economicCodeItem = EconomyCodeItem::find($economicCodeItemId);
                        if ($economicCodeItem) {
                            $economicCodeItemName = $economicCodeItem->name;
                            $economicCodeItemCode = $economicCodeItem->code;

                            // Get the parent economic code
                            $economicCode = $economicCodeItem->economyCode;
                            if ($economicCode) {
                                $economicCodeId = $economicCode->id;
                                $economicCodeName = $economicCode->name;
                                $economicCodeCode = $economicCode->code;

                                // Check if this is bank charges (ID 74 for economic_code, ID 364 for economic_code_item)
                                $isBankChargesVoucher = ($economicCodeId == 74 && $economicCodeItemId == 364);
                            }
                        }
                    }
                }

                // Create description
                $description = $voucher->narration ?? "Voucher #{$voucher->voucher_number}";
                if ($isBankChargesVoucher) {
                    $description = 'Bank Charges: ' . $description;
                }

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $bankId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $voucher->voucher_date,
                    'description' => $description,
                    'amount' => $voucher->total_amount,
                    'type' => 'payment',

                    // Reference tracking
                    'reference_number' => $voucher->voucher_number,
                    'cheque_number' => $voucher->cheque_number ?? null,

                    // Party information
                    'payer_name' => null,
                    'payee_name' => $voucher->payee_name ?? 'N/A',

                    // Source document tracking
                    'source_type' => 'voucher',
                    'source_id' => $voucher->id,

                    // Classification
                    'category' => $isBankChargesVoucher ? 'Bank Charges' : 'Expense',
                    'sub_category' => $voucher->voucher_type,

                    // Payment details
                    'payment_mode' => $voucher->payment_mode ?? 'cheque',
                    'bank_name' => $voucher->bankActivity->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'voucher_type' => $voucher->voucher_type,
                        'mda_id' => $voucher->mda_id,
                        'year_id' => $voucher->year_id,
                        'schedule_id' => $voucher->schedule_id,
                        'current_stage' => $voucher->current_stage,
                        'requires_retirement' => $voucher->requires_retirement,
                        'retired_at' => $voucher->retired_at,
                        'retirement_voucher_id' => $voucher->retirement_voucher_id,
                        // Economic code information
                        'economic_code_id' => $economicCodeId,
                        'economic_code_name' => $economicCodeName,
                        'economic_code_code' => $economicCodeCode,
                        'economy_code_item_id' => $economicCodeItemId,
                        'economic_code_item_name' => $economicCodeItemName,
                        'economic_code_item_code' => $economicCodeItemCode,
                        // Bank charges flag
                        'is_bank_charges' => $isBankChargesVoucher,
                        'classification' => $voucher->classification ?? ($isBankChargesVoucher ? 'Bank Charges' : 'Expense'),
                        'sub_classification' => $voucher->sub_classification ?? $voucher->voucher_type,
                        // Bank info
                        'bank_account_number' => $voucher->bankActivity ? $voucher->bankActivity->account_number : null,
                        'bank_name' => $voucher->bankActivity ? $voucher->bankActivity->bank_name : null,
                    ],

                    // Remarks
                    'remarks' => $voucher->narration,
                ]);
                $entriesCreated++;
            }

            // 3. Fetch and create entries for remittances
            // Remittances where this bank is the destination (credit side - money coming in)
            $incomingRemittances = Remittance::where('destination_bank_id', $currentBankActivityId)
                ->whereBetween('transfer_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->with(['sourceBank', 'destinationBank'])
                ->get();

            // if ($cashbook->bank_activities_id == 173) {
            //     dd( $startDate, $endDate, $incomingRemittances);
            // }

            foreach ($incomingRemittances as $remittance) {
                // Get economic code info from remittance
                $economicCode = null;
                $economicCodeItem = null;

                // Try to find economic code item by destination account number
                if ($remittance->destinationBank && $remittance->destinationBank->account_number) {
                    $accountNumber = $remittance->destinationBank->account_number;

                    // Look for economic code item where name contains the account number
                    $economicCodeItem = EconomyCodeItem::where('name', 'LIKE', "%{$accountNumber}%")->first();

                    if (isset($economicCodeItem)) {
                        // dd("B1");
                        // Get the parent economic code
                        $economicCode = $economicCodeItem->economyCode;
                        // $economicCodeItem->status = 'inactive';
                        // $economicCodeItem->save();
                    }
                }

                // Create description for incoming remittance
                $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Incoming)";

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $remittance->transfer_date,
                    'description' => $description,
                    'amount' => $remittance->amount,
                    'type' => 'receipt',

                    // Reference tracking
                    'reference_number' => $remittance->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => $remittance->sourceBank->bank_name ?? 'Source Bank',
                    'payee_name' => null,

                    // Source document tracking
                    'source_type' => 'remittance',
                    'source_id' => $remittance->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $remittance->sourceBank->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'remittance_type' => 'incoming',
                        'source_bank_id' => $remittance->source_bank_id,
                        'destination_bank_id' => $remittance->destination_bank_id,
                        'receipt_number' => $remittance->receipt_number,
                        'transfer_date' => $remittance->transfer_date,
                        // Economic code information
                        'economic_code_id' => $economicCode ? $economicCode->id : null,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $economicCodeItem ? $economicCodeItem->id : null,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $remittance->classification,
                        'sub_classification' => $remittance->sub_classification,
                        // Source bank info
                        'source_bank_account_number' => $remittance->sourceBank ? $remittance->sourceBank->account_number : null,
                        'source_bank_name' => $remittance->sourceBank ? $remittance->sourceBank->bank_name : null,
                        // Destination bank info
                        'destination_bank_account_number' => $remittance->destinationBank ? $remittance->destinationBank->account_number : null,
                        'destination_bank_name' => $remittance->destinationBank ? $remittance->destinationBank->bank_name : null,
                        // Account number used for lookup
                        'lookup_account_number' => $accountNumber ?? null,
                    ],

                    // Remarks
                    'remarks' => $remittance->narration ?? 'Interbank remittance received',
                ]);
            }

            // Remittances where this bank is the source (debit side - money going out)
            $outgoingRemittances = Remittance::where('source_bank_id', $currentBankActivityId)
                ->whereBetween('transfer_date', [$startDate, $endDate])
                ->where('status', 'Submitted')
                ->with(['sourceBank', 'destinationBank'])
                ->get();

            foreach ($outgoingRemittances as $remittance) {
                // Get economic code info from remittance
                $economicCode = null;
                $economicCodeItem = null;

                // Try to find economic code item by source account number
                if ($remittance->sourceBank && $remittance->sourceBank->account_number) {
                    $accountNumber = $remittance->sourceBank->account_number;

                    // Look for economic code item where name contains the account number
                    $economicCodeItem = EconomyCodeItem::where('name', 'LIKE', "%{$accountNumber}%")->whereBetween('id', [626, 916])->first();

                    if (isset($economicCodeItem)) {

                        // dd('B2');
                        // Get the parent economic code
                        $economicCode = $economicCodeItem->economyCode;
                        // $economicCodeItem->status = 'inactive';
                        // $economicCodeItem->save();
                    }
                }

                // Create description for outgoing remittance
                $description = $remittance->narration ?? "Remittance #{$remittance->receipt_number} (Outgoing)";

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $remittance->transfer_date,
                    'description' => $description,
                    'amount' => $remittance->amount,
                    'type' => 'payment',

                    // Reference tracking
                    'reference_number' => $remittance->receipt_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => null,
                    'payee_name' => $remittance->destinationBank->bank_name ?? 'Destination Bank',

                    // Source document tracking
                    'source_type' => 'remittance',
                    'source_id' => $remittance->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer',
                    'bank_name' => $remittance->destinationBank->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'remittance_type' => 'outgoing',
                        'source_bank_id' => $remittance->source_bank_id,
                        'destination_bank_id' => $remittance->destination_bank_id,
                        'receipt_number' => $remittance->receipt_number,
                        'transfer_date' => $remittance->transfer_date,
                        // Economic code information
                        'economic_code_id' => $economicCode ? $economicCode->id : null,
                        'economic_code_name' => $economicCode ? $economicCode->name : null,
                        'economic_code_code' => $economicCode ? $economicCode->code : null,
                        'economy_code_item_id' => $economicCodeItem ? $economicCodeItem->id : null,
                        'economic_code_item_name' => $economicCodeItem ? $economicCodeItem->name : null,
                        'economic_code_item_code' => $economicCodeItem ? $economicCodeItem->code : null,
                        'classification' => $remittance->classification,
                        'sub_classification' => $remittance->sub_classification,
                        // Source bank info
                        'source_bank_account_number' => $remittance->sourceBank ? $remittance->sourceBank->account_number : null,
                        'source_bank_name' => $remittance->sourceBank ? $remittance->sourceBank->bank_name : null,
                        // Destination bank info
                        'destination_bank_account_number' => $remittance->destinationBank ? $remittance->destinationBank->account_number : null,
                        'destination_bank_name' => $remittance->destinationBank ? $remittance->destinationBank->bank_name : null,
                        // Account number used for lookup
                        'lookup_account_number' => $accountNumber ?? null,
                    ],

                    // Remarks
                    'remarks' => $remittance->narration ?? 'Interbank remittance sent',
                ]);
            }

            $economicCode = $cashbook->bankAccount->economic_code;

            $startDate = "{$cashbook->year}/{$cashbook->month_id}/01";
            $endDate = "{$cashbook->year}/{$cashbook->month_id}/31";
            $journalEntries = JournalEntry::where('account_code', $economicCode)
                ->whereHas('journal', function ($journal) use ($startDate, $endDate) {
                    $journal->where('status', 'approved')
                        ->whereBetween('journal_date', [$startDate, $endDate]);
                })->get();
            // $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$startDate, $endDate])->where('status', 'approved')->get();

            $journalCredits = 0.00;
            $journalDebits = 0.00;
            // if ($journalEntries->isNotEmpty()) {
            //     dd($economicCode, $journalEntries);
            // }
            foreach ($journalEntries as $journalEntry) {


                // Create description for outgoing remittance
                $description = $journalEntry->description;

                CashbookEntry::create([
                    // Core relationships
                    'cashbook_id' => $cashbook->id,
                    'bank_activities_id' => $currentBankActivityId,
                    'user_id' => auth()->id(),

                    // Transaction information
                    'transaction_date' => $journalEntry->journal->journal_date,
                    'description' => $description,
                    'amount' => floatVal($journalEntry->credit_amount) > 0 ? $journalEntry->credit_amount : $journalEntry->debit_amount,
                    'type' => floatVal($journalEntry->credit_amount) > 0 ? 'payment' : 'receipt',

                    // Reference tracking
                    'reference_number' => $journalEntry->journal->journal_number,
                    'cheque_number' => null,

                    // Party information
                    'payer_name' => 'Journal ' . $journalEntry->journal->journal_type,
                    'payee_name' => 'Journal',

                    // Source document tracking
                    'source_type' => 'journal ' .  $journalEntry->journal->journal_type,
                    'source_id' => $journalEntry->id,

                    // Classification
                    'category' => $remittance->classification ?? 'Transfer',
                    'sub_category' => $remittance->sub_classification ?? 'Interbank Transfer',

                    // Payment details
                    'payment_mode' => 'transfer', // $journalEntry->journal->journal_type,
                    'bank_name' => BankActivity::where('id', $currentBankActivityId)->first()?->bank_name ?? null,

                    // Status
                    'status' => 'posted',
                    'is_reconciled' => false,

                    // Metadata for additional data
                    'metadata' => [
                        'journal_type' => floatVal($journalEntry->credit_amount) > 0 ? 'outflow' : 'inflow',
                        'journal_id' => $journalEntry->journal->journal_number,
                        'destination_bank_id' => BankActivity::where('id', $currentBankActivityId)->first()?->id,
                        'journal_number' => $journalEntry->journal->journal_number,
                        'journal_type' => $journalEntry->journal->journal_type,
                        'journal_date' => $journalEntry->journal->journal_date,
                        'journal_entry_id' => $journalEntry->id,
                        'journal_entry_credit' => $journalEntry->credit_amount,
                        'journal_entry_debit' => $journalEntry->debit_amount,
                        'journal_entry_description' => $journalEntry->description,
                        'journal_entry_economic_code' => $journalEntry->account_code,
                        'journal_entry_line_number' => $journalEntry->line_number,


                    ],

                    // Remarks
                    'remarks' => $journalEntry->journal->remarks ?? 'Journal operation',
                ]);
            }






            // 4. Recalculate cashbook totals
            $totalReceipts = $cashbook->entries()->where('type', 'receipt')->sum('amount');
            $totalPayments = $cashbook->entries()->where('type', 'payment')->sum('amount');

            // Use the new method to update balances and carry forward
            $balances = $this->updateCashbookBalances($cashbook, $totalReceipts, $totalPayments);
            $closingBalance = $balances['closing_balance'];

            return [
                'success' => true,
                'message' => 'Cashbook entries generated successfully',
                'entries_count' => $entriesCreated,
                'receipts_count' => $cashbook->entries()->where('type', 'receipt')->count(),
                'payments_count' => $cashbook->entries()->where('type', 'payment')->count(),
                'remittances_incoming_count' => $cashbook->entries()
                    ->where('source_type', 'remittance')
                    ->where('type', 'receipt')
                    ->count(),
                'remittances_outgoing_count' => $cashbook->entries()
                    ->where('source_type', 'remittance')
                    ->where('type', 'payment')
                    ->count(),
                'total_receipts' => $totalReceipts,
                'total_payments' => $totalPayments,
                'closing_balance' => $closingBalance,
            ];
        } catch (\Exception $e) {
            // dd($cashbook, $e);
            return [
                'success' => false,
                'message' => 'Failed to generate entries: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'entries_count' => 0,
            ];
        }
    }

    /**
     * Get month name from month ID
     */
    private function getMonthName($monthId)
    {
        $monthNames = [
            1 => 'January',
            2 => 'February',
            3 => 'March',
            4 => 'April',
            5 => 'May',
            6 => 'June',
            7 => 'July',
            8 => 'August',
            9 => 'September',
            10 => 'October',
            11 => 'November',
            12 => 'December',
        ];

        return $monthNames[$monthId] ?? 'Unknown Month';
    }

    /**
     * Calculate opening balance from previous month's closing balance
     */
    private function calculateOpeningBalance($cashbook)
    {
        try {
            $currentMonth = $cashbook->month_id;
            $currentYear = $cashbook->year;
            $bankId = $cashbook->bank_activities_id;

            // If it's January (month 1), get opening balance from financial year
            if ($currentMonth == 1) {
                // For January, get from cashbook financial year opening balance
                // This is already handled in the store method

                // $economicCode = $cashbook->bankAccount->economic_code;
                // $startDate = "{$cashbook->year}/{$cashbook->month_id}/01";
                // $endDate = "{$cashbook->year}/{$cashbook->month_id}/31";
                // $journalIds = JournalEntry::where('account_code', $economicCode)->get()->pluck('journal_id')->toArray();
                // $journals = Journal::whereIn('id', $journalIds)->whereBetween('journal_date', [$startDate, $endDate])->where('status', 'approved')->get();

                // $journalCredits = 0.00;
                // $journalDebits = 0.00;
                // foreach ($journals as $journal) {
                //     // dd($journal->entries);
                //     $journalCredits += $journal->entries->where('account_code', $economicCode)->sum('credit_amount');
                //     $journalDebits += $journal->entries->where('account_code', $economicCode)->sum('debit_amount');
                // }

                return $cashbook->opening_balance;
            }

            // For other months, get previous month's closing balance
            $previousMonth = $currentMonth - 1;
            $previousYear = $currentYear;

            // Handle year transition (if previous month is December of previous year)
            if ($previousMonth == 0) {
                $previousMonth = 12;
                $previousYear = $currentYear - 1;
            }

            // Find previous month's cashbook
            $previousCashbook = Cashbook::where([
                'month_id' => $previousMonth,
                'year' => $previousYear,
                'bank_activities_id' => $bankId,
                'cashbook_financial_year_id' => $cashbook->cashbook_financial_year_id,
            ])->first();

            // If previous cashbook exists and has been processed, use its closing balance
            if ($previousCashbook && $previousCashbook->closing_balance !== null) {
                return (float) $previousCashbook->closing_balance;
            }

            // If no previous cashbook found, try to get the last processed cashbook
            $lastProcessedCashbook = Cashbook::where('bank_activities_id', $bankId)
                ->where('cashbook_financial_year_id', $cashbook->cashbook_financial_year_id)
                ->where('status', 'processed')
                ->where(function ($query) use ($currentMonth, $currentYear) {
                    $query->where('year', '<', $currentYear)
                        ->orWhere(function ($q) use ($currentMonth, $currentYear) {
                            $q->where('year', '=', $currentYear)
                                ->where('month_id', '<', $currentMonth);
                        });
                })
                ->orderBy('year', 'desc')
                ->orderBy('month_id', 'desc')
                ->first();

            if ($lastProcessedCashbook) {
                return (float) $lastProcessedCashbook->closing_balance;
            }

            // Default to current opening balance if nothing found
            return (float) $cashbook->opening_balance;
        } catch (\Exception $e) {
            \Log::error('Error calculating opening balance: ' . $e->getMessage());


            return (float) $cashbook->opening_balance;
        }
    }

    /**
     * Update cashbook closing balance and carry forward to next month
     */
    private function updateCashbookBalances(Cashbook $cashbook, $totalReceipts, $totalPayments)
    {
        try {
            DB::beginTransaction();

            // Calculate opening balance (carried from previous month)
            $openingBalance = $this->calculateOpeningBalance($cashbook);

            // Calculate closing balance
            $closingBalance = ($openingBalance + $totalReceipts) - $totalPayments;

            // Update current cashbook
            $cashbook->update([
                'opening_balance' => $openingBalance,
                'total_remittances' => $totalReceipts,
                'total_payments' => $totalPayments,
                'closing_balance' => $closingBalance,
                'status' => 'processed',
            ]);

            // Update next month's opening balance if it exists
            $this->carryForwardToNextMonth($cashbook, $closingBalance);

            DB::commit();

            return [
                'opening_balance' => $openingBalance,
                'closing_balance' => $closingBalance,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Carry forward closing balance to next month's opening balance
     */
    private function carryForwardToNextMonth(Cashbook $currentCashbook, $closingBalance)
    {
        $nextMonth = $currentCashbook->month_id + 1;
        $nextYear = $currentCashbook->year;

        // Handle year transition
        if ($nextMonth > 12) {
            $nextMonth = 1;
            $nextYear = $currentCashbook->year + 1;
        }

        // Find next month's cashbook
        $nextCashbook = Cashbook::where([
            'month_id' => $nextMonth,
            'year' => $nextYear,
            'bank_activities_id' => $currentCashbook->bank_activities_id,
            'cashbook_financial_year_id' => $currentCashbook->cashbook_financial_year_id,
        ])->first();

        if ($nextCashbook && $nextCashbook->status !== 'processed') {
            // Update next month's opening balance with current month's closing balance
            $nextCashbook->update([
                'opening_balance' => $closingBalance,
            ]);

            \Log::info('Carried forward balance', [
                'from' => "Month {$currentCashbook->month_id}/{$currentCashbook->year}",
                'to' => "Month {$nextMonth}/{$nextYear}",
                'amount' => $closingBalance,
                'account' => $currentCashbook->bank_activities_id,
            ]);
        }

        return $nextCashbook;
    }

    /**
     * Recalculate all cashbook balances in sequence for a financial year
     */
    public function recalculateBalances(CashbookFinancialYear $financialYear)
    {
        try {
            DB::beginTransaction();

            $results = [];

            // Get all bank accounts for this financial year
            $bankAccounts = BankActivity::where('status', 1)->get();

            foreach ($bankAccounts as $account) {
                // Process months in sequence (1-12)
                for ($month = 1; $month <= 12; $month++) {
                    $cashbook = Cashbook::where([
                        'cashbook_financial_year_id' => $financialYear->id,
                        'bank_activities_id' => $account->id,
                        'month_id' => $month,
                    ])->first();

                    if (! $cashbook) {
                        continue;
                    }

                    // Calculate totals from entries
                    $totalReceipts = $cashbook->entries()->where('type', 'receipt')->sum('amount');
                    $totalPayments = $cashbook->entries()->where('type', 'payment')->sum('amount');

                    // Get opening balance (calculated from previous month)
                    $openingBalance = $this->calculateOpeningBalance($cashbook);
                    $closingBalance = ($openingBalance + $totalReceipts) - $totalPayments;

                    // Update the cashbook
                    $cashbook->update([
                        'opening_balance' => $openingBalance,
                        'total_remittances' => $totalReceipts,
                        'total_payments' => $totalPayments,
                        'closing_balance' => $closingBalance,
                    ]);

                    $results[] = [
                        'account' => $account->title,
                        'month' => $month,
                        'opening_balance' => $openingBalance,
                        'closing_balance' => $closingBalance,
                        'total_receipts' => $totalReceipts,
                        'total_payments' => $totalPayments,
                    ];
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'All cashbook balances recalculated successfully',
                'results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to recalculate balances: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get previous month's closing balance for a cashbook
     */
    public function getPreviousMonthBalance(Cashbook $cashbook)
    {
        try {
            $currentMonth = $cashbook->month_id;
            $currentYear = $cashbook->year;
            $bankId = $cashbook->bank_activities_id;

            // If it's January, there's no previous month in the same financial year
            if ($currentMonth == 1) {
                return response()->json([
                    'success' => true,
                    'is_january' => true,
                    'message' => 'January - Opening balance from financial year',
                    'previous_month_balance' => $cashbook->opening_balance,
                    'previous_month' => null,
                    'previous_year' => null,
                    'source' => 'financial_year_opening',
                ]);
            }

            // Get previous month
            $previousMonth = $currentMonth - 1;
            $previousYear = $currentYear;

            // Find previous month's cashbook
            $previousCashbook = Cashbook::where([
                'month_id' => $previousMonth,
                'year' => $previousYear,
                'bank_activities_id' => $bankId,
                'cashbook_financial_year_id' => $cashbook->cashbook_financial_year_id,
            ])->first();

            if ($previousCashbook) {
                return response()->json([
                    'success' => true,
                    'is_january' => false,
                    'previous_month_balance' => (float) $previousCashbook->closing_balance,
                    'previous_month_name' => $this->getMonthName($previousMonth),
                    'previous_month_number' => $previousMonth,
                    'previous_year' => $previousYear,
                    'previous_cashbook_status' => $previousCashbook->status,
                    'source' => 'previous_month_closing',
                    'has_previous_cashbook' => true,
                ]);
            }

            // If no previous cashbook found, return financial year opening balance for January
            $financialYear = $cashbook->treasuryYear;
            $januaryCashbook = Cashbook::where([
                'month_id' => 1,
                'year' => $previousYear,
                'bank_activities_id' => $bankId,
                'cashbook_financial_year_id' => $cashbook->cashbook_financial_year_id,
            ])->first();

            return response()->json([
                'success' => true,
                'is_january' => false,
                'previous_month_balance' => $januaryCashbook ? (float) $januaryCashbook->opening_balance : 0,
                'previous_month_name' => 'January',
                'previous_month_number' => 1,
                'previous_year' => $previousYear,
                'source' => 'financial_year_january',
                'has_previous_cashbook' => (bool) $januaryCashbook,
                'note' => 'Using January opening balance as reference',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get previous month balance: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get next month's info for carry forward
     */
    public function getNextMonthInfo(Cashbook $cashbook)
    {
        try {
            $currentMonth = $cashbook->month_id;
            $currentYear = $cashbook->year;

            // Calculate next month
            $nextMonth = $currentMonth + 1;
            $nextYear = $currentYear;

            // Handle year transition
            if ($nextMonth > 12) {
                $nextMonth = 1;
                $nextYear = $currentYear + 1;
            }

            // Find next month's cashbook
            $nextCashbook = Cashbook::where([
                'month_id' => $nextMonth,
                'year' => $nextYear,
                'bank_activities_id' => $cashbook->bank_activities_id,
                'cashbook_financial_year_id' => $cashbook->cashbook_financial_year_id,
            ])->first();

            $nextMonthName = $this->getMonthName($nextMonth);

            return response()->json([
                'success' => true,
                'next_month_name' => $nextMonthName,
                'next_month_number' => $nextMonth,
                'next_year' => $nextYear,
                'has_next_cashbook' => (bool) $nextCashbook,
                'next_cashbook_status' => $nextCashbook ? $nextCashbook->status : null,
                'next_cashbook_opening_balance' => $nextCashbook ? (float) $nextCashbook->opening_balance : null,
                'will_carry_forward' => $cashbook->status === 'processed' && $nextCashbook,
                'carry_forward_amount' => $cashbook->closing_balance,
                'note' => $nextCashbook
                    ? "This month's closing balance will become {$nextMonthName}'s opening balance"
                    : "No cashbook found for {$nextMonthName} {$nextYear}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get next month info: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get balance chain for a cashbook (previous, current, next)
     */
    public function getBalanceChain(Cashbook $cashbook)
    {
        try {
            $currentMonth = $cashbook->month_id;
            $currentYear = $cashbook->year;
            $bankId = $cashbook->bank_activities_id;
            $financialYearId = $cashbook->cashbook_financial_year_id;

            $chain = [];

            // Get previous 3 months
            for ($i = 3; $i >= 1; $i--) {
                $lookbackMonth = $currentMonth - $i;
                $lookbackYear = $currentYear;

                if ($lookbackMonth < 1) {
                    $lookbackMonth += 12;
                    $lookbackYear -= 1;
                }

                $prevCashbook = Cashbook::where([
                    'month_id' => $lookbackMonth,
                    'year' => $lookbackYear,
                    'bank_activities_id' => $bankId,
                    'cashbook_financial_year_id' => $financialYearId,
                ])->first();

                if ($prevCashbook) {
                    $chain['previous_months'][] = [
                        'month' => $this->getMonthName($lookbackMonth),
                        'month_number' => $lookbackMonth,
                        'year' => $lookbackYear,
                        'opening_balance' => (float) $prevCashbook->opening_balance,
                        'closing_balance' => (float) $prevCashbook->closing_balance,
                        'status' => $prevCashbook->status,
                        'is_processed' => $prevCashbook->status === 'processed',
                    ];
                }
            }

            // Current month
            $chain['current_month'] = [
                'month' => $this->getMonthName($currentMonth),
                'month_number' => $currentMonth,
                'year' => $currentYear,
                'opening_balance' => (float) $cashbook->opening_balance,
                'closing_balance' => (float) $cashbook->closing_balance,
                'status' => $cashbook->status,
                'is_processed' => $cashbook->status === 'processed',
            ];

            // Get next 3 months
            for ($i = 1; $i <= 3; $i++) {
                $lookaheadMonth = $currentMonth + $i;
                $lookaheadYear = $currentYear;

                if ($lookaheadMonth > 12) {
                    $lookaheadMonth -= 12;
                    $lookaheadYear += 1;
                }

                $nextCashbook = Cashbook::where([
                    'month_id' => $lookaheadMonth,
                    'year' => $lookaheadYear,
                    'bank_activities_id' => $bankId,
                    'cashbook_financial_year_id' => $financialYearId,
                ])->first();

                if ($nextCashbook) {
                    $chain['next_months'][] = [
                        'month' => $this->getMonthName($lookaheadMonth),
                        'month_number' => $lookaheadMonth,
                        'year' => $lookaheadYear,
                        'opening_balance' => (float) $nextCashbook->opening_balance,
                        'closing_balance' => (float) $nextCashbook->closing_balance,
                        'status' => $nextCashbook->status,
                        'is_processed' => $nextCashbook->status === 'processed',
                        'will_receive_carry_forward' => $i === 1 && $cashbook->status === 'processed',
                    ];
                }
            }

            // Calculate carry forward chain
            $carryForwardChain = [];
            if ($cashbook->status === 'processed') {
                $currentBalance = $cashbook->closing_balance;

                for ($i = 1; $i <= 3; $i++) {
                    $futureMonth = $currentMonth + $i;
                    $futureYear = $currentYear;

                    if ($futureMonth > 12) {
                        $futureMonth -= 12;
                        $futureYear += 1;
                    }

                    $futureCashbook = Cashbook::where([
                        'month_id' => $futureMonth,
                        'year' => $futureYear,
                        'bank_activities_id' => $bankId,
                        'cashbook_financial_year_id' => $financialYearId,
                    ])->first();

                    if ($futureCashbook) {
                        $carryForwardChain[] = [
                            'from_month' => $i === 1 ? $this->getMonthName($currentMonth) : $this->getMonthName($futureMonth - 1),
                            'to_month' => $this->getMonthName($futureMonth),
                            'amount' => $currentBalance,
                            'is_carried' => $i === 1,
                            'future_cashbook_exists' => true,
                            'future_status' => $futureCashbook->status,
                        ];
                    }
                }
            }

            return response()->json([
                'success' => true,
                'chain' => $chain,
                'carry_forward_chain' => $carryForwardChain,
                'financial_year' => $cashbook->treasuryYear ? $cashbook->treasuryYear->name : null,
                'bank_account' => $cashbook->bankAccount ? [
                    'title' => $cashbook->bankAccount->title,
                    'account_number' => $cashbook->bankAccount->account_number,
                    'bank_name' => $cashbook->bankAccount->bank_name,
                ] : null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get balance chain: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function print(Cashbook $cashbook)
    {
        // Load relationships
        $cashbook->load(['bankAccount', 'month']);

        // Get receipts and payments
        $receipts = $cashbook->entries()
            ->where('type', 'receipt')
            ->orderBy('transaction_date')
            ->get();

        $payments = $cashbook->entries()
            ->where('type', 'payment')
            ->orderBy('transaction_date')
            ->get();

        // Add properties that entries.vue expects
        $cashbook->month_name = $cashbook->month->name ?? '';

        // Calculate start and end dates for the month
        $formattedMonth = str_pad($cashbook->month_id, 2, '0', STR_PAD_LEFT);
        $cashbook->start_date = "{$cashbook->year}-{$formattedMonth}-01";
        $cashbook->end_date = date('Y-m-t', strtotime($cashbook->start_date));

        // Make bank_account accessible
        $cashbook->bank_account = $cashbook->bankAccount;
        //dd();
        return inertia('admin/cashbook/print', [
            'cashbook' => $cashbook,
            'receipts' => $receipts,
            'payments' => $payments,
        ]);
    }

    public function printledger(Cashbook $cashbook)
    {
        // Load relationships
        $cashbook->load(['bankAccount', 'month']);

        // Get receipts and payments
        $receipts = $cashbook->entries()
            ->where('type', 'receipt')
            ->orderBy('transaction_date')
            ->get();

        $payments = $cashbook->entries()
            ->where('type', 'payment')
            ->orderBy('transaction_date')
            ->get();

        // Add properties that entries.vue expects
        $cashbook->month_name = $cashbook->month->name ?? '';

        // Calculate start and end dates for the month
        $formattedMonth = str_pad($cashbook->month_id, 2, '0', STR_PAD_LEFT);
        $cashbook->start_date = "{$cashbook->year}-{$formattedMonth}-01";
        $cashbook->end_date = date('Y-m-t', strtotime($cashbook->start_date));

        // Make bank_account accessible
        $cashbook->bank_account = $cashbook->bankAccount;

        return inertia('admin/cashbook/printLedger', [
            'cashbook' => $cashbook,
            'receipts' => $receipts,
            'payments' => $payments,
        ]);
    }
}
