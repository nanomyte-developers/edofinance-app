<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class CashFlowController extends Controller
{
    public function index()
    {
        $data = [
            // --- CASH FLOWS FROM OPERATING ACTIVITIES ---
            ['description' => 'CASH FLOWS FROM OPERATING ACTIVITIES', 'isHeader' => true],
            ['description' => 'INFLOWS', 'isSubHeader' => true],
            ['description' => 'Revenue From Non-Exchange & Exchange Transactions:', 'isSubHeader' => true],
            ['ref' => '1', 'notes' => '1', 'description' => 'Statutory Allocation', 'val2025' => '', 'val2024' => 287853578032.82],
            ['ref' => '1', 'notes' => '1', 'description' => 'VAT', 'val2025' => '', 'val2024' => 70656894844.83],
            ['ref' => '2.1', 'notes' => '2', 'description' => 'Tax Receipts', 'val2025' => '', 'val2024' => 57298021701.31],
            ['ref' => '3.1, 3.2', 'notes' => '3', 'description' => 'Licenses, Fees and Fines', 'val2025' => '', 'val2024' => 25213279727.79],
            ['ref' => '3.4, 3.5', 'notes' => '4', 'description' => 'Sales & Earnings', 'val2025' => '', 'val2024' => 458380160.07],
            ['description' => 'Rent from Government Property','notes' => '3.6', 'val2025' => '', 'val2024' => 218788697.39],
            ['description' => 'Rent from Government Land','notes' => '3.7', 'val2025' => '', 'val2024' => 3833137.90],
            ['description' => 'Investment Income','notes' => '4', 'val2025' => '', 'val2024' => 4916277824.49],
            ['description' => 'Interest Income','notes' => '5', 'val2025' => '', 'val2024' => 17489373.40],
            ['ref' => '5', 'notes' => '9', 'description' => 'Reimbursement, Misc.', 'val2025' => '', 'val2024' => 86766652.20],
            ['ref' => '6', 'notes' => '6', 'description' => 'Domestic Aids & Grants', 'val2025' => '', 'val2024' => 1647062824.69],
            ['ref' => '6', 'notes' => '6', 'description' => 'External Aids & Grants', 'val2025' => '', 'val2024' => 0],
            ['ref' => '7', 'notes' => '7', 'description' => 'Other Receipts', 'val2025' => '', 'val2024' => 35018888838],
            ['description' => 'Total Receipt from non-exchange & Exchange Transactions', 'isTotal' => true, 'total2025' => 0, 'total2024' => 448720561860.27],

            ['description' => 'OUTFLOWS', 'isSubHeader' => true],
            ['ref' => '20.1', 'notes' => '10.1', 'description' => 'Personnel Cost (Including CRF Salaries)', 'val2025' => '', 'val2024' => 51394746900.85],
            ['ref' => '22.2', 'notes' => '13.2', 'description' => 'Overhead (General & Admin Expenses)', 'val2025' => '', 'val2024' => 82925903553.78],
            ['ref' => '23', 'notes' => '11', 'description' => 'Contribution to Pension Schemes', 'val2025' => '', 'val2024' => 3325619031.55],
            ['ref' => '23', 'notes' => '11', 'description' => 'Contribution to Other Employee Schemes', 'val2025' => '', 'val2024' => 1063610707.62],
            ['notes' => '12','description' => 'Social Benefits', 'val2025' => '', 'val2024' => 12510020709.23],
            ['notes' => '20','description' => 'Servicing of Loans and other Charges', 'val2025' => '', 'val2024' => 1237309293084],
            ['description' => 'Total Outflows', 'isTotal' => true, 'total2025' => 0, 'total2024' => 163592993833.87],
            ['ref' => '27-28', 'notes' => 'SPL18', 'description' => 'Other Operating Activities', 'val2025' => '', 'val2024' => 8045083269.88],
            ['description' => 'Net Cash Flows from Operating Activities (I)', 'isFinal' => true, 'total2025' => 0, 'total2024' => 293172651296.28],

            // --- CASH FLOWS FROM INVESTING ACTIVITIES ---
            ['description' => 'CASH FLOWS FROM INVESTING ACTIVITIES', 'isHeader' => true],
            ['ref' => '44', 'notes' => '44', 'description' => 'Purchase and Construction of Assets', 'val2025' => '', 'val2024' => -262748302511.67],
            ['ref' => '25', 'notes' => '29', 'description' => 'Addition to Investment', 'val2025' => '', 'val2024' => -45943806.89],
            ['description' => 'Net Cashflows Used in Investing Activities (ii)', 'isFinal' => true, 'total2025' => 0, 'total2024' => -262794246318.56],

            // --- CASH FLOWS FROM FINANCING ACTIVITIES ---
            ['description' => 'CASH FLOWS FROM FINANCING ACTIVITIES', 'isHeader' => true],
            ['ref' => '34 & 35.18', 'notes' => '34 & 39.1B', 'description' => 'Proceeds from Domestic Loans & Other Borrowings', 'val2025' => '', 'val2024' => 4559808928.54],
            ['ref' => '34 & 35.18', 'notes' => '34 & 39.1B', 'description' => 'Proceeds from External Loans & Other Borrowings', 'val2025' => '', 'val2024' => 30998362860.87],
            ['description' => 'Grants and Loans to Other Governments/Agencies', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Contribution/Subscription to International Agencies/Bodies', 'val2025' => '', 'val2024' => 0],
            ['ref' => '29.18', 'notes' => '39.1B', 'description' => 'Repayment of Loans & Other Borrowings', 'val2025' => '', 'val2024' => -36740283619.72],
            ['description' => 'Net Cashflows from Financing Activities (iii)', 'isFinal' => true, 'total2025' => 0, 'total2024' => 1182111830.31],
            [],

            ['description' => 'Net Cash Flow from All Activities (i+ii+iii)', 'isFinal' => true, 'total2025' => 0, 'total2024' => 29196493147.41],
            [],

            ['description' => 'CASH AND CASH EQUIVALENT AT THE BEGINNING OF THE YEAR', 'val2025' => '', 'val2024' => 19517124202.22],

            ['description' => 'CASH AND CASH EQUIVALENT AT THE END OF THE YEAR', 'isFinal' => true, 'total2025' => 0, 'total2024' => 48713617349.63],

            ['description' => 'Notes:', 'isFinal' => true, 'total2025' => 0, 'total2024' => 0],

            // --- RECONCILIATION ---
            ['description' => 'RECONCILIATION:', 'isHeader' => true],
            ['description' => 'Surplus/(Deficit) per Statement of Performance', 'val2025' => '', 'val2024' => 43280186202.74],
            ['description' => 'Add Back Non-Cash Movement Items:', 'isSubHeader' => true],
            ['description' => 'Depreciation Charges', 'val2025' => '', 'val2024' => 46490106461.97],
            ['description' => 'Amortization Charges', 'val2025' => '', 'val2024' => 13665506953.57],
            ['description' => 'Impairment Charges', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Debt Forgiveness', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Exchange Rate Differences', 'val2025' => '', 'val2024' => 181691768408.12],
            ['description' => 'Revenue recognized under Investing Activities', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Net Movement in Assets/Liabilities:', 'isSubHeader' => true],
            ['description' => 'Net Movement in Inventories', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Net Movement in Receivables', 'val2025' => '', 'val2024' => 3230293963.80],
            ['description' => 'Net Movement in Prepayment', 'val2025' => '', 'val2024' => 12380952.38],
            ['description' => 'Net Movement in Payables', 'val2025' => '', 'val2024' => 68480572390.79],
            ['description' => 'Advances to Company', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Unremitted Deduction', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Net Operating Items in Reserve', 'val2025' => '', 'val2024' => -293172651296.28],
            ['description' => 'Net Cash Flow from Operating Activities', 'isFinal' => true, 'total2025' => 0, 'total2024' => 293172651296.28],
            [],
            // --- CASH & EQUIVALENT BREAKDOWN ---
            ['description' => 'Cash & its equivalent as at 31st December, 2025 as per SFP', 'isHeader' => true],
            ['description' => 'Cash balances', 'val2025' => '', 'val2024' => 0],
            ['description' => 'Bank balances', 'val2025' => '', 'val2024' => 48713617349.63],
            ['description' => 'Certificate of Deposits', 'val2025' => '', 'val2024' => 0],
            ['description' => 'TOTAL CASH AND CASH EQUIVALENTS', 'isFinal' => true, 'total2025' => 0, 'total2024' => 48713617349.63],
        ];

        return Inertia::render('admin/reports/cashFlow', [
            'cashFlowData' => $data
        ]);
    }
}
