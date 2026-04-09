<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashAndBankBalancesController extends Controller
{
    public function index()
    {
        $balanceData = [
            ['sno' => 170, 'economic_code' => '31012523', 'description' => 'UBA Plc, B/C_1027788845_Capital', 'current_year' => 0, 'prev_year' => 0],
            ['sno' => 171, 'economic_code' => '31012524', 'description' => 'UBA Plc, B/C_1027687584_Vat', 'current_year' => 448892387.06, 'prev_year' => 0],
            ['sno' => 172, 'economic_code' => '31012603', 'description' => 'Union Bank Plc_0014765769_Fores', 'current_year' => 98529.50, 'prev_year' => 98529.50],
            ['sno' => 173, 'economic_code' => '31012604', 'description' => 'Union Bank Plc_0010847166_VAT$', 'current_year' => 49421.24, 'prev_year' => 49421.24],
            ['sno' => 174, 'economic_code' => '31012606', 'description' => 'Union Bank Plc_0035015410_G.P.A', 'current_year' => 1050868.77, 'prev_year' => 1050868.77],
            ['sno' => 175, 'economic_code' => '31012608', 'description' => 'Union Bank Plc_0055630140_IGR', 'current_year' => 215002662.94, 'prev_year' => 114755399.50],
            ['sno' => 176, 'economic_code' => '31012701', 'description' => 'Unity Bank Plc_0012188606_M/V N', 'current_year' => 2.10, 'prev_year' => 2.10],
            ['sno' => 177, 'economic_code' => '31012703', 'description' => 'Unity Bank Plc_0024705354_G.P.A', 'current_year' => 24.00, 'prev_year' => 24.00],
            ['sno' => 178, 'economic_code' => '31012707', 'description' => 'Unity Bank Plc_0026061007_IGR', 'current_year' => 65835086.27, 'prev_year' => 81087911.88],
            ['sno' => 179, 'economic_code' => '31012708', 'description' => 'Unity Bank Plc_0017995261_IGR/I', 'current_year' => 6175591.49, 'prev_year' => 62198.61],
            ['sno' => 180, 'economic_code' => '31012801', 'description' => 'WEMA Bank Plc_0122146651_IGR', 'current_year' => 569900489.71, 'prev_year' => 284070699.30],
            ['sno' => 181, 'economic_code' => '31012802', 'description' => 'WEMA Bank Plc_0122307656_GPA', 'current_year' => 269226.44, 'prev_year' => 269849.94],
            ['sno' => 182, 'economic_code' => '31012901', 'description' => 'Zenith Bank Plc_1010501196_Fert', 'current_year' => 48243.63, 'prev_year' => 48243.63],
            ['sno' => 183, 'economic_code' => '31012905', 'description' => 'Zenith Bank Plc_1011866072_C of', 'current_year' => 0.05, 'prev_year' => 0.05],
            ['sno' => 184, 'economic_code' => '31012906', 'description' => 'Zenith Bank Plc_1012019318_EDPA', 'current_year' => 0.03, 'prev_year' => 0.03],
            ['sno' => 185, 'economic_code' => '31012907', 'description' => 'Zenith Bank Plc_1012017211_EDSG', 'current_year' => 243208146.00, 'prev_year' => 49033233.75],
            ['sno' => 186, 'economic_code' => '31012909', 'description' => 'Zenith Bank Plc_1012656997_Mis', 'current_year' => 0.99, 'prev_year' => 0.99],
            ['sno' => 187, 'economic_code' => '31012910', 'description' => 'Zenith Bank Plc_1012840633_Vehi', 'current_year' => 0.10, 'prev_year' => 0.10],
            ['sno' => 188, 'economic_code' => '31012913', 'description' => 'Zenith Bank Plc_1013851823_Land', 'current_year' => 50172.13, 'prev_year' => 50172.13],
            ['sno' => 189, 'economic_code' => '31012914', 'description' => 'Zenith Bank Plc_1013885860_Drug', 'current_year' => 0.24, 'prev_year' => 0.24],
            ['sno' => 190, 'economic_code' => '31012916', 'description' => 'Zenith Bank Plc_1014282002_Manu', 'current_year' => 4316695.97, 'prev_year' => 150281216.16],
        ];

        return Inertia::render('admin/reports/cashAndBankBalances', [
            'balanceData' => $balanceData
        ]);
    }
    /**
     * Data extraction from Note 24.3 - Other Bank of the Treasury
    //  */
    // public function getOtherBankOfTheTreasury()
    // {
    //     $balanceData = [
    //         [
    //             'sno' => 1,
    //             'economic_code' => 'N/A',
    //             'description' => 'Balance as at 1/1/2015 (inclusive of interest): £112,501 @ 2001 rate of £1=165.0976645',
    //             'current_year' => 18573652.35,
    //             'prev_year' => 0.00
    //         ],
    //         [
    //             'sno' => 2,
    //             'economic_code' => 'N/A',
    //             'description' => 'Deposit/Advance',
    //             'current_year' => 7000.00,
    //             'prev_year' => 0.00
    //         ],
    //     ];

    //     return Inertia::render('admin/reports/otherBankOfTheTreasury', [
    //         'balanceData' => $balanceData
    //     ]);
    // }

    // CashAndBankBalancesController.php

    public function getOtherBankOfTheTreasury()
    {
        $balanceData = [
            [
                'sno' => '',
                'economic_code' => '',
                'description' => 'Balance as at 1/1/2015 (inclusive of interest)',
                'current_year' => 0.00,
                'prev_year' => 0.00
            ],
             [
                'sno' => '',
                'economic_code' => '',
                'description' => '£112,501 @ 2001 rate of £1=165.0976645',
                'current_year' => 0.00,
                'prev_year' => 0.00
            ],
             [
                'sno' => '',
                'economic_code' => '',
                'description' => 'Jan - Dec',
                'current_year' => 0.00,
                'prev_year' => 0.00
            ],
             [
                'sno' => '',
                'economic_code' => '',
                'description' => 'Total',
                'current_year' => 0.00,
                'prev_year' => 0.00
            ],
            [
                'sno' => '',
                'economic_code' => '',
                'description' => 'Deposit/Advance',
                'current_year' => '0',
                'prev_year' => 0.00
            ],
        ];

        // Calculate totals for the footer
        $totals = [
            'current' => array_sum(array_column($balanceData, 'current_year')),
            'previous' => array_sum(array_column($balanceData, 'prev_year')),
        ];

        return Inertia::render('admin/reports/otherBankOfTheTreasury', [
            'balanceData' => $balanceData,
            'totals' => $totals
        ]);
    }

}
