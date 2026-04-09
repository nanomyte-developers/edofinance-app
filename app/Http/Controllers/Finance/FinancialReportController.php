<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Inertia\Inertia;


class FinancialReportController extends Controller
{
    public function index()
    {

        $Note_1 = ['11010101', '11010106', '11010107', '11010108', '11010109', '11010111', '11010112', '11010113', '11010117', '11010128', '11010201', '11010202', '11010205', '11010302'];
        $Note_2 = ['12010101', '12010104',  '12010105',  '12010106', '12010107', '12010108', '12010109',  '12010110', '12010112', '12010113'];
        $Note_3 = ['12020109', '12020123', '12020129', '12020132', '12020134', '12020136', '12020401', '12020404', '12020403', '12020417', '12020427', '12020437', '12020438', '12020439', '12020447', '12020448', '12020449', '12020451', '12020453', '12020454', '12020456', '12020463', '12020464', '12020465', '12020467', '12020472', '12020481', '12020485', '12020493', '12020501', '12020502', '12020505', '12020604', '12020609', '12020612', '12020617', '12020701', '12020703', '12020709', '12020711', '12020725', '12020803', '12020805', '12020901', '12020902'];
        $Note_4 = ['12021102'];
        $note_5 = ['12021209', '12021210', '12021299'];
        $Note_6 = ['12021401', '12021403', '12021404', '12021406', '13020301', '13020302'];
        $Note_7 = ['14020201'];
        $Note_8 = ['12020145', '12021302'];
        $Note_9 = [];
        $Note_10 = ['11010203', '21010101', '21010103', '21010104'];
        $Note_11 = ['21020201', '21020202', '21020203', '21020206'];
        $Note_12 = ['21030101', '21030102', '21030103', '22010103', '22010105', '22010107'];
        $Note_13 = ['22020101', '22020102', '22020103', '22020104', '22020201', '22020202', '22020203', '22020204', '22020205', '22020206', '22020210', '22020301', '22020302', '22020303', '22020304', '22020305', '22020306', '22020307', '22020308', '22020309', '22020310', '22020311', '22020312', '22020313', '22020401', '22020402', '22020403', '22020404', '22020405', '22020406', '22020410', '22020411', '22020412', '22020501', '22020601', '22020602', '22020604', '22020605', '22020701', '22020702', '22020703', '22020704', '22020706', '22020707', '22020708', '22020709', '22020801', '22020802', '22020803', '22020806', '22021001', '22021002', '22021003', '22021004', '22021006', '22021007', '22021008', '22021009', '22021011', '22021013', '22021014', '22021017', '22021021', '22021024', '22021025', '22021037', '22021041', '22021043', '22021044', '22040108', '22040109', '22060202', '23020118'];
        $Note_14 = [];
        $Note_15 = [];
        $Note_16 = ['24010101', '24010202', '24010209', '24010304', '24010405', '24010501', '24010601', '24020101'];
        $Note_17 = [];
        $Note_18 = ['25030106'];
        $Note_19 = [];
        $Note_20 = ['22020901', '22020902', '22020904', '22020908', '22020909'];
        $Note_21 = [];
        $Note_22 = ['22090101'];

        $Note_1_sum = \getEconomicCodeSum($Note_1);
        $Note_2_sum = getEconomicCodeSum($Note_2);
        $Note_3_sum = getEconomicCodeSum($Note_3);
        $Note_4_sum = getEconomicCodeSum($Note_4);
        $note_5_sum = getEconomicCodeSum($note_5);
        $Note_6_sum = getEconomicCodeSum($Note_6);
        $Note_7_sum = getEconomicCodeSum($Note_7);
        $Note_8_sum = getEconomicCodeSum($Note_8);
        $Note_9_sum = getEconomicCodeSum($Note_9);
        $Note_10_sum = getEconomicCodeSum($Note_10);
        $Note_11_sum = getEconomicCodeSum($Note_11);
        $Note_12_sum = getEconomicCodeSum($Note_12);
        $Note_13_sum = getEconomicCodeSum($Note_13);
        $Note_14_sum = getEconomicCodeSum($Note_14);
        $Note_15_sum = getEconomicCodeSum($Note_15);
        $Note_16_sum = getEconomicCodeSum($Note_16);
        $Note_17_sum = getEconomicCodeSum($Note_17);
        $Note_18_sum = getEconomicCodeSum($Note_18);
        $Note_19_sum = getEconomicCodeSum($Note_19);
        $Note_20_sum = getEconomicCodeSum($Note_20);
        $Note_21_sum = getEconomicCodeSum($Note_21);
        $Note_22_sum = getEconomicCodeSum($Note_22);

        // dd($Note_20_sum,  $Note_22_sum);
        $staticData = [
            // REVENUE SECTION
            ['isHeader' => true, 'description' => 'REVENUE'],
            [],
            [
                'description' => 'Government Share of FAAC (Statutory Revenue)',
                'notes' => '1',
                'prevActual' => 358510472877.65,
                'actual2025' => $Note_1_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Tax Revenue',
                'notes' => '2',
                'prevActual' => 57298021701.31,
                'actual2025' => 5678798,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Non-Tax Revenue',
                'notes' => '3',
                'prevActual' => 25894281718.15,
                'actual2025' => $Note_3_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Investment Income',
                'notes' => '4',
                'prevActual' => 4916277824.49,
                'actual2025' => $Note_4_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Interest Earned',
                'notes' => '5',
                'prevActual' => 17489373.40,
                'actual2025' => $note_5_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Aids & Grants',
                'notes' => '6',
                'prevActual' => 1647062824.69,
                'actual2025' => $Note_6_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Other Capital Receipts',
                'notes' => '7',
                'prevActual' => 350188888.38,
                'actual2025' => $Note_7_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Debt Forgiveness',
                'notes' => '8',
                'prevActual' => 0,
                'actual2025' => $Note_8_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Other Revenue',
                'notes' => '9',
                'prevActual' => 86766652.20,
                'actual2025' => $Note_9_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'TOTAL OPERATING REVENUE',
                'prevActual' => 448720561860.27,
                'actual2025' => $Note_1_sum + $Note_2_sum + $Note_3_sum + $Note_4_sum + $note_5_sum + $Note_6_sum + $Note_7_sum + $Note_8_sum + $Note_9_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [],
            // EXPENDITURE SECTION
            ['isHeader' => true, 'description' => 'EXPENDITURE'],
            [
                'description' => 'Salaries & Wages',
                'notes' => '10',
                'prevActual' => 51394746900.85,
                'actual2025' => $Note_10_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Allowances & Social Contribution',
                'notes' => '11',
                'prevActual' => 4389229739.17,
                'actual2025' => $Note_11_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Social Benefits',
                'notes' => '12',
                'prevActual' => 12510020709.23,
                'actual2025' => $Note_12_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Overhead Cost',
                'notes' => '13',
                'prevActual' => 82925903553.78,
                'actual2025' => $Note_13_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Grants & Contributions',
                'notes' => '14',
                'prevActual' => 0,
                'actual2025' => $Note_14_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Subsidies',
                'notes' => '15',
                'prevActual' => 0,
                'actual2025' => $Note_15_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Depreciation Charges',
                'notes' => '16',
                'prevActual' => 46490106461.97,
                'actual2025' => $Note_16_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Impairment Charges',
                'notes' => '17',
                'prevActual' => 0,
                'actual2025' => $Note_17_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Amortization Charges',
                'notes' => '18',
                'prevActual' => 13665506953.57,
                'actual2025' => $Note_18_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Bad Debts Charges',
                'notes' => '19',
                'prevActual' => 0,
                'actual2025' => $Note_19_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'TOTAL OPERATING EXPENSES',
                'prevActual' => 211375514318.57,
                'actual2025' => $Note_10_sum + $Note_11_sum + $Note_12_sum + $Note_13_sum + $Note_14_sum + $Note_15_sum + $Note_16_sum + $Note_17_sum + $Note_18_sum + $Note_19_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'isTotal' => true,
                'description' => 'Surplus for the year before Foreign Exchange Losses and Public Debt Charges',
                'prevActual' => 237345047541.70,
                'actual2025' => ($Note_1_sum + $Note_2_sum + $Note_3_sum + $Note_4_sum + $note_5_sum + $Note_6_sum + $Note_7_sum + $Note_8_sum + $Note_9_sum) - ($Note_10_sum + $Note_11_sum + $Note_12_sum + $Note_13_sum + $Note_14_sum + $Note_15_sum + $Note_16_sum + $Note_17_sum + $Note_18_sum + $Note_19_sum) ,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            // NON-OPERATING SECTION
            ['isHeader' => true, 'description' => 'NON-OPERATING REVENUE/EXPENSES'],
            [
                'description' => 'Servicing of Loans & Other Charges',
                'notes' => '20',
                'prevActual' => 12373092930.84,
                'actual2025' => $Note_20_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Exchange Rate Loss',
                'notes' => '22',
                'prevActual' => 181691768408.12,
                'actual2025' => $Note_22_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'isTotal' => true,
                'description' => 'Total non-operating expenses',
                'prevActual' => 194064861338.96,
                'actual2025' => $Note_20_sum + $Note_22_sum,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'Surplus/(Deficit) from Ordinary Activities',
                'prevActual' => 43280186202.74,
                'actual2025' => ($Note_1_sum + $Note_2_sum + $Note_3_sum + $Note_4_sum + $note_5_sum + $Note_6_sum + $Note_7_sum + $Note_8_sum + $Note_9_sum) - ($Note_10_sum + $Note_11_sum + $Note_12_sum + $Note_13_sum + $Note_14_sum + $Note_15_sum + $Note_16_sum + $Note_17_sum + $Note_18_sum + $Note_19_sum) - ($Note_20_sum + $Note_22_sum),
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [
                'description' => 'Minority Interest Share of surplus/(deficit)',
                'notes' => '',
                'prevActual' => 0,
                'actual2025' => '',
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'Total Net Operating Revenue/(Expenses)',
                'prevActual' => 43280186202.74,
                'actual2025' => ($Note_1_sum + $Note_2_sum + $Note_3_sum + $Note_4_sum + $note_5_sum + $Note_6_sum + $Note_7_sum + $Note_8_sum + $Note_9_sum) - ($Note_10_sum + $Note_11_sum + $Note_12_sum + $Note_13_sum + $Note_14_sum + $Note_15_sum + $Note_16_sum + $Note_17_sum + $Note_18_sum + $Note_19_sum) - ($Note_20_sum + $Note_22_sum) - 0,
                'finalBudget' => '',
                'suppBudget' => '',
                'initialBudget' => '',
                'variance' => ''
            ],
        ];


        // $this->getEconomicCodeSum($Note_1);
        // dd('hi');
        return Inertia::render('admin/reports/financialStatement', [
            'financialData' => $staticData
        ]);
    }

    public function Myindex()
    {
        $staticData = [
            // REVENUE SECTION
            ['isHeader' => true, 'description' => 'REVENUE'],
            [],
            [
                'description' => 'Government Share of FAAC (Statutory Revenue)',
                'notes' => '1',
                'prevActual' => 157754747789.65,
                'actual2024' => 318320470817.61,
                'finalBudget' => 224968853414.21,
                'suppBudget' => 144521114259.61,
                'initialBudget' => 180447739154.60,
                'variance' => 26543322613.40
            ],
            [
                'description' => 'Tax Revenue',
                'notes' => '2',
                'prevActual' => 42436968679.20,
                'actual2024' => 67017285710.31,
                'finalBudget' => 48231015648.00,
                'suppBudget' => 1936711754.70,
                'initialBudget' => 46294298893.30,
                'variance' => 18786270062.31
            ],
            [
                'description' => 'Non-Tax Revenue',
                'notes' => '3',
                'prevActual' => 17341346708.95,
                'actual2024' => 25894283719.15,
                'finalBudget' => 25633530754.43,
                'suppBudget' => 1461717446.03,
                'initialBudget' => 24171813308.39,
                'variance' => 260752964.72
            ],
            [
                'description' => 'Investment Income',
                'notes' => '4',
                'prevActual' => 317365914.12,
                'actual2024' => 4551177824.49,
                'finalBudget' => 1500000000.00,
                'suppBudget' => 240000000.00,
                'initialBudget' => 1260000000.00,
                'variance' => 3051177824.49
            ],
            [
                'description' => 'Interest Earned',
                'notes' => '5',
                'prevActual' => 15509820.24,
                'actual2024' => 17489373.41,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 17489373.41
            ],
            [
                'description' => 'Aids & Grants',
                'notes' => '6',
                'prevActual' => 6116084573.15,
                'actual2024' => 1847062824.69,
                'finalBudget' => 16575560000.00,
                'suppBudget' => 500000000.00,
                'initialBudget' => 16075560000.00,
                'variance' => -14728497175.31
            ],
            [
                'description' => 'Other Capital Receipts',
                'notes' => '7',
                'prevActual' => 528537301.28,
                'actual2024' => 2530188898.38,
                'finalBudget' => 10816201572.42,
                'suppBudget' => -6183798427.58,
                'initialBudget' => 17000000000.00,
                'variance' => -8458012684.04
            ],
            [
                'description' => 'Debt Forgiveness',
                'notes' => '8',
                'prevActual' => 0,
                'actual2024' => 0,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 0
            ],
            [
                'description' => 'Other Revenue',
                'notes' => '9',
                'prevActual' => 89441440.96,
                'actual2024' => 86766632.23,
                'finalBudget' => 158500000.00,
                'suppBudget' => 143160754.06,
                'initialBudget' => 15339245.94,
                'variance' => -71733367.77
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'TOTAL OPERATING REVENUE',
                'prevActual' => 224598002254.55,
                'actual2024' => 420720561860.27,
                'finalBudget' => 327984161389.06,
                'suppBudget' => 141530375911.66,
                'initialBudget' => 284114465923.23,
                'variance' => 16675716839.38
            ],
            [],
            // EXPENDITURE SECTION
            ['isHeader' => true, 'description' => 'EXPENDITURE'],
            [
                'description' => 'Salaries & Wages',
                'notes' => '10',
                'prevActual' => 36767871193.31,
                'actual2024' => 51354746900.85,
                'finalBudget' => 58925140312.84,
                'suppBudget' => 10412662767.54,
                'initialBudget' => 48512477545.30,
                'variance' => 7570393411.99
            ],
            [
                'description' => 'Allowances & Social Contribution',
                'notes' => '11',
                'prevActual' => 4377711340.40,
                'actual2024' => 4388225739.17,
                'finalBudget' => 5893532291.89,
                'suppBudget' => 2443532291.89,
                'initialBudget' => 3450000000.00,
                'variance' => 1505306552.72
            ],
            [
                'description' => 'Social Benefits',
                'notes' => '12',
                'prevActual' => 11438163467.82,
                'actual2024' => 12510020709.23,
                'finalBudget' => 13759191882.84,
                'suppBudget' => 559191882.84,
                'initialBudget' => 13200000000.00,
                'variance' => 1249171173.61
            ],
            [
                'description' => 'Overhead Cost',
                'notes' => '13',
                'prevActual' => 48688154680.18,
                'actual2024' => 82925903353.78,
                'finalBudget' => 77278435804.36,
                'suppBudget' => 34250213276.36,
                'initialBudget' => 43028222528.00,
                'variance' => -5647467549.42
            ],
            [
                'description' => 'Grants & Contributions',
                'notes' => '14',
                'prevActual' => 0,
                'actual2024' => 0,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 0
            ],
            [
                'description' => 'Subsidies',
                'notes' => '15',
                'prevActual' => 0,
                'actual2024' => 0,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 0
            ],
            [
                'description' => 'Depreciation Charges',
                'notes' => '16',
                'prevActual' => 27911371752.94,
                'actual2024' => 46490106461.97,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => -46490106461.97
            ],
            [
                'description' => 'Impairment Charges',
                'notes' => '17',
                'prevActual' => 0,
                'actual2024' => 0,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 0
            ],
            [
                'description' => 'Amortization Charges',
                'notes' => '18',
                'prevActual' => 10040514374.99,
                'actual2024' => 13665506953.57,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => -13665506953.57
            ],
            [
                'description' => 'Bad Debts Charges',
                'notes' => '19',
                'prevActual' => 0,
                'actual2024' => 0,
                'finalBudget' => 0,
                'suppBudget' => 0,
                'initialBudget' => 0,
                'variance' => 0
            ],
            [],
            [
                'isTotal' => true,
                'description' => 'TOTAL OPERATING EXPENSES',
                'prevActual' => 139563786809.63,
                'actual2024' => 211335514318.57,
                'finalBudget' => 155856304291.93,
                'suppBudget' => 47665544218.73,
                'initialBudget' => 108397100522.68,
                'variance' => -55479210026.64
            ],
        ];


        return Inertia::render('admin/reports/financialStatement', [
            'financialData' => $staticData
        ]);
    }
}
