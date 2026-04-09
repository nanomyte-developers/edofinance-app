<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AssetsEquityController extends Controller
{
    public function index()
    {
        $data = [
            // 2023 Section
            ['description' => '31ST DECEMBER 2024', 'isHeader' => true],
            ['description' => 'Beginning of the year', 'reserves' => 352413592256.25, 'revaluation_reserve' => 0, 'accumulated' => 223550256022.48, 'total' => 575963484278.73],
            ['description' => 'Effect of Retrospective Restatement', 'reserves' => -65349934450.27, 'revaluation_reserve' => 0, 'accumulated' => 1671770412.60, 'total' => 63678164037.67],
            ['description' => 'Restated Balance','isSubHeader' => true, 'reserves' => 287063657805.98, 'revaluation_reserve' => 0, 'accumulated' => 225222026435.08, 'total' => 512285684241.06],
            [],
             [
                'description' => 'Surplus on Revaluation of Property',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'Surplus on Revaluation of Investment',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            ['description' => 'Transfer from Statement of financial Performance', 'reserves' => 0, 'revaluation_reserve' => 0, 'accumulated' => 43280186202.74, 'total' => 4328018620274],
            ['description' => 'End of the Year 2024', 'isTotal' => true, 'reserves' => 287063657805.98, 'revaluation_reserve' => 0, 'accumulated' => 268502212637.81, 'total' => 555565870443.80],

            // 2024 Section

            ['description' => '31ST DECEMBER 2025', 'isHeader' => true],
            [
                'description' => 'Beginning of the year',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'Effect of Re-evaluation/Adjustments',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'Restated Balance',
                'isSubHeader' => true,
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [],
            [
                'description' => 'Surplus on Revaluation of Property',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'Surplus on Revaluation of Investment',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'Transfer from Statement of Financial Performance',
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
            [
                'description' => 'End of the Year 2025',
                'isFinal' => true,
                'reserves' => 0,
                'revaluation_reserve' => 0,
                'accumulated' => 0,
                'total' => 0
            ],
        ];
        return Inertia::render('admin/reports/assetsEquity', [
            'equityData' => $data
        ]);
    }
}
