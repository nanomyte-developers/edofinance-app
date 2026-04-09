<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class NoteToGpfsController extends Controller
{
    public function index()
    {
        $data = [
            // NOTE 1
            [
                'note' => '1',
                'title' => 'SHARE OF STATUTORY ALLOCATION FROM FAAC',
                'ref_notes' => 'SPI 1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 13864502565.46, // Moved from actual_2025
                'is_header' => true
            ],
            [
                'details' => 'Share of Statutory Allocation from FAAC',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 13864502565.46,
            ],
            [],
            [
                'details' => 'Gross Share of Federal Accounts Allocation (SRA)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 13864502565.46,
                'is_subtotal' => true
            ],
            [
                'details' => 'Share of Statutory Allocation - Other agencies',
                'ref_notes' => 'SPI 1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 273989075467.36,
            ],
            [
                'details' => 'Share of Federal Accounts Allocation-Excess Crude  Oil ',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025'=> 0,
                'actual_2024' => 0, // Reset from '-'
            ],
            [
                'details' => 'Total (Gross) FAAC Allocation to EDSG',
                'ref_notes' => 'SPI 1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 287853578032.82,
                'is_subtotal' => true
            ],
             [
                'details' => 'Value added Tax',
                'ref_notes' => 'SPI 1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 273989075467.36,
            ],
            [   'details' => 'Total -SRA & VAT',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 287853578032.82,
                'is_subtotal' => true
            ],
            [],
             [  'details' => 'Total -SRA VAT & Others',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 287853578032.82,
                'is_subtotal' => true
            ],
            [],[],
            // NOTE 2
            [
                'note' => '2',
                'title' => 'Tax Revenue',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 57298021701.31,
                'is_header' => true
            ],
            [
                'note' => '2.1',
                'details' => 'Direct Taxes - Code (12010101)',
                'ref_notes' => 'SPI 2',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 57298021701.31,
            ],
            [
                'details' => 'Edo State Internal Revenue Services (EIRS)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 57298021701.31,
            ],
            [
                'details' => 'Total Direct Taxes',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 57298021701.31,
                'is_subtotal' => true
            ],
            [], [],
            // NOTE 3
            [
                'note' => '3',
                'title' => 'Non-Tax Revenue',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1667752571.86,
                'is_header' => true
            ],
            [
                'note' => '3.1',
                'details' => 'Licences - Code (12020101)',
                'ref_notes' => 'SPI 3.1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1667752571.86,
            ],
            [
                'details' => 'Edo State Internal Revenue Services (EIRS)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1613146909.22,
            ],
            [
                'details' => 'Ministry Of Finance (MOF)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 23868742.94,
            ],
            [
                'details' => 'Bureau Of Public Procurement',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 50000.00,
            ],
            [
                'details' => 'Ministry of Social Development and Gender Issues',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 19571870.54,
            ],
            [
                'details' => 'Ministry Of Youth Development',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 11218081.16,
            ],
            [
                'details' => 'Total Licences',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1667752571.86,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.2',
                'details' => 'Fees - Code (12020400)',
                'ref_notes' => 'SPI 3.2',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 321093336.51,
            ],
            [
                'details' => 'Edo State Public Procurement Agency (EDPPA)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 112192076.49,
            ],
            [
                'details' => 'Edo Broadcasting Service (EBS)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 99535577.26,
            ],
            [
                'details' => 'Edo State Traffic Mgt Agency (EDTSMA)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 74170255.43,
            ],
            [
                'details' => 'High Court of Justice (HCJ)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 456801977.26,
            ],
            [
                'details' => 'Judicial Service Commission (JSC)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 656360.00,
            ],
            [
                'details' => 'Edo State Health Insurance Scheme',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 2311103.54,
            ],
            [
                'details' => 'Edo State Investment Promotion Office',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 2445772322.42,
            ],
            [
                'details' => 'Auditor General State',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 12000.00,
            ],
            [
                'details' => 'Ministry of Agric. & Natural Resources (MANR)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 45041000.00,
            ],
            [
                'details' => 'Ministry of Environment & Sustainability (ME&S)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 172159154.67,
            ],
            [
                'details' => 'Min. of Education (MOE)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 180122246.47,
            ],
            [
                'details' => 'Ministry Of Finance (MOF)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 13411425424.39,
            ],
            [
                'details' => 'Ministry Of Health (MOH)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 165431528.00,
            ],
            [
                'details' => 'Hospital Management Board (HMB)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 14044700.22,
            ],
            [
                'details' => 'Edo State Transport Authority',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 429116354.26,
            ],
            [
                'details' => 'Ministry Of Youth Development',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 6168706.58,
            ],
            [
                'details' => 'Total Fees',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 23013533773.11,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.3',
                'details' => 'Fines - Code (12020500)',
                'ref_notes' => 'SPI 3.3',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 742512111.40,
            ],
            [
                'details' => 'Ministry of Environment & Sustainability (ME&S)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 509809839.56,
            ],
            [
                'details' => 'High Court of Justice (HCJ)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 21639652.34,
            ],
            [
                'details' => 'Edo State Traffic Mgt Agency (EDTSMA)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 340000.00,
            ],
            [
                'details' => 'Edo State Internal Revenue Service',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 7665.47,
            ],
            [
                'details' => 'Information communication Tech. Agency',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 196220.45,
            ],
            [
                'details' => 'Total Fines',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 531993382.82,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.4',
                'details' => 'Sales - Code (12020600)',
                'ref_notes' => 'SPI 3.4',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 407540993.23,
            ],
            [
                'details' => 'Ministry of Agric. & Natural Resources (MANR)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 183471426.01,
            ],
            [
                'details' => 'Land Bureau/EDO GIS',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 2782286.80,
            ],
            [
                'details' => 'Directorate of Central Administration',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 55888938.00,
            ],
            [
                'details' => 'Ministry Of Health (MOH)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 5000.00,
            ],
            [
                'details' => 'Edo State Development Property Authority',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 47991337.42,
            ],
            [
                'details' => 'Secretary To The State Government (SSG)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 555000.00,
            ],
            [
                'details' => 'Ministry Of Finance (MOF)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 116847005.00,
            ],
            [
                'details' => 'Total Sales',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 407540993.23,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.5',
                'details' => 'Earnings - Code (12020700)',
                'ref_notes' => 'SPI 3.5',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 0,
            ],
            [
                'details' => 'Edo Broadcasting Service (EBS)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 12117840.00,
            ],
            [
                'details' => 'Government Printing Press',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 21105000.00,
            ],
            [
                'details' => 'Ministry Of Health (MOH)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 8204847.56,
            ],
            [
                'details' => 'Ministry Of Justice',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1946775.28,
            ],
            [
                'details' => 'Ministry of Water Resources',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 3384200.00,
            ],
            [
                'details' => 'Directorate of Information communication Tech. (DICT)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 3384200.00,
            ],
            [
                'details' => 'Min. of Tourism, Culture and National Orientation',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 2580500.00,
            ],
            [
                'details' => 'Edo State Urban Water Corporation',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1500000.00,
            ],
            [
                'details' => 'Total Earnings',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 50839166.84,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.6',
                'details' => 'Rent on Government Buildings - Code (12020800)',
                'ref_notes' => 'SPI 3.6',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 33210400.00,
            ],
            [
                'details' => 'Ministry Of Finance (MOF)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 7250000.00,
            ],
            [
                'details' => 'Edo State Dev. & Prop. Authority (EDPA)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 178328292.39,
            ],
            [
                'details' => 'Total Rent on Government Buildings',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 218788692.39,
                'is_subtotal' => true
            ],
            [],
            [
                'note' => '3.7',
                'details' => 'Rent on Lands and Others - Code (12020900)',
                'ref_notes' => 'SPI 3.7',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 0,
            ],
            [
                'details' => 'Ministry of Environment & Sustainability (ME&S)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 3833137.90,
            ],
            [
                'details' => 'Total Rent on Lands and Others',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 3833137.90,
                'is_subtotal' => true
            ],
            [
                'details' => 'Grand Total Non-Tax Revenue',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 25894281718.15,
                'is_grand_total' => true
            ],
            [],
            // NOTE 4
            [
                'note' => '4',
                'details' => 'Investment Income - Code (12021000)',
                'ref_notes' => 'SPI 4',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 0,
            ],
            [
                'details' => 'Ministry Of Finance (MOF)',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 4916277824.49,
            ],
            [
                'details' => 'Total Investment Income',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 4916277824.49,
                'is_subtotal' => true
            ],
            [],
            // NOTE 5
            [
                'note' => '5',
                'details' => 'Interest Earned - Code (12021200)',
                'ref_notes' => 'SPI 5',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 0,
            ],
            [
                'details' => 'Interest Received on Commercial Bank Deposits',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1527014693.30,
            ],
            [
                'details' => 'Total Interest Earned',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 1527014693.30,
                'is_subtotal' => true
            ],
            [],
            // NOTE 24
            [
                'note' => '24',
                'title' => 'CASH AND CASH EQUIVALENTS',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 48713617349.63,
                'is_header' => true
            ],
            [
                'details' => 'Cash and Bank Balances Held by the Treasury',
                'ref_notes' => '24.1',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 46654185402.35,
            ],
            [
                'details' => 'Cash and Bank balances Held by MDA',
                'ref_notes' => '24.2',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 2040851847.28,
            ],
            [
                'details' => 'Total Cash and Cash Equivalents',
                'ref_notes' => '',
                'actual_2025' => 0,
                'budget_2025' => 0,
                'variance_2025' => 0,
                'actual_2024' => 48713617349.63,
                'is_subtotal' => true
            ],
                 // --- NOTE 25: INVENTORY ---
        [
            'note' => '25',
            'title' => 'INVENTORY',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Medical Stores',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Engineering Stores',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Industrial & Chemical Stores',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Equipment/Spare Parts',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],

        // --- NOTE 26: RECEIVABLES ---
        [
            'note' => '26',
            'title' => 'RECEIVABLES',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 10423662476.05,
            'is_header' => true
        ],
        [
            'note' => '26.1',
            'title' => 'ADVANCES',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 10423662476.05,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Advance to MWCCE',
            'ref_notes' => 'SPL 11.1',
            'actual_2025' => '',
            'actual_2024' => 1690000000.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Administrative Advances - CAC Loan',
            'ref_notes' => 'SPL 11.2',
            'actual_2025' => '',
            'actual_2024' => 1816145777.31,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Advances to LGCs on Cashless Rollout Technology',
            'ref_notes' => 'SPL 11.3',
            'actual_2025' => '',
            'actual_2024' => 7988.23,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Advances on FGN Bailout to LGCs',
            'ref_notes' => 'SPL 11.4',
            'actual_2025' => '',
            'actual_2024' => 6917508710.51,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'SUB-TOTAL',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 10423662476.05,
            'is_header' => true
        ],
        [
            'note' => '26.2',
            'title' => 'ARREARS OF REVENUE',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 14920860474.57,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Receivables',
            'ref_notes' => 'SPL 11.5',
            'actual_2025' => '',
            'actual_2024' => 14920860474.57,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'TOTAL',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 14920860474.57,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Grand Total',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 25344522950.62,
            'is_header' => true
        ],

        // --- NOTE 27: PREPAYMENTS ---
        [
            'note' => '27',
            'title' => 'PREPAYMENTS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 824008974.91,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Ministry of Infrastructure',
            'ref_notes' => 'SPL 12',
            'actual_2025' => '',
            'actual_2024' => 478330013.50,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Min. of Environment & Public Utilities',
            'ref_notes' => 'SPL 12',
            'actual_2025' => '',
            'actual_2024' => 345678961.41,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Ministry of Physical Planning',
            'ref_notes' => 'SPL 12',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Ministry of Education',
            'ref_notes' => 'SPL 12',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'TOTAL',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 824008974.91,
            'is_header' => true
        ],

        // --- NOTE 28: LOANS GRANTED ---
        [
            'note' => '28',
            'title' => 'LOANS GRANTED',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 1000000000.00,
            'is_header' => true
        ],
        [
            'note' => '28.1',
            'title' => 'LOCAL LOANS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 1000000000.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Loan to State Governments',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Loan to Local Governments',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Loan to Government Owned Entities',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Loans to Private Entities',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 1000000000.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Sub - Total',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 1000000000.00,
            'is_header' => true
        ],
        [
            'note' => '28.2',
            'title' => 'FOREIGN LOANS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Loan to Foreign Governments',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Loan to Foreign/International Organisations',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Sub - Total',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'TOTAL LOAN GRANTED',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 1000000000.00,
            'is_header' => true
        ],

        // --- NOTE 29: INVESTMENTS ---
        [
            'note' => '29',
            'title' => 'INVESTMENTS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 3424519695.63,
            'is_header' => true
        ],
        [
            'note' => '29.1',
            'title' => 'LOCAL INVESTMENTS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 3424519695.63,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Local Investments: Quoted Companies',
            'ref_notes' => 'SPL 13',
            'actual_2025' => '',
            'actual_2024' => 2285382946.24,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Local Investments: Non - Quoted Companies',
            'ref_notes' => 'SPL 13',
            'actual_2025' => '',
            'actual_2024' => 1139136749.39,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'SUB-TOTAL',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 3424519695.63,
            'is_header' => true
        ],
        [
            'note' => '29.2',
            'title' => 'FOREIGN INVESTMENTS',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'Foreign Investments: Quoted Companies',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'Foreign Investments: Non- Quoted Companies',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => false
        ],
        [
            'note' => '',
            'title' => 'SUB-TOTAL',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 0.00,
            'is_header' => true
        ],
        [
            'note' => '',
            'title' => 'TOTAL INVESTMENT',
            'ref_notes' => '',
            'actual_2025' => '',
            'actual_2024' => 3424519695.63,
            'is_header' => true
        ]
        ];

        return Inertia::render('admin/reports/noteToGpfs', [
            'notesData' => $data
        ]);
    }

   public function getInventoryAndReceivables()
{
    $data = [
        ['description' => 'Medical Stores', 'note' => '', 'current_year' => 0, 'prev_year' => 0],
        // ... (rest of Note 25/26 data)
    ];

   
      
   
      //dd($data2);
    return Inertia::render('admin/reports/NoteToInventory', [
        'balanceData' => $data,
        'totals'      => ['current' => 25344522950.62, 'previous' => 28574816914.42]
    ]);
}
}
