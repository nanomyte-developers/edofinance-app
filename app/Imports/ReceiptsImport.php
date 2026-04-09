<?php

namespace App\Imports;

use App\Models\Receipt;
use App\Models\EconomyCodeItem;
use App\Models\EconomyCode;
use Maatwebsite\Excel\Concerns\ToModel;
use App\Models\Mda;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date; // Import the Date helper



class ReceiptsImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        // dd(Carbon::instance(Date::excelToDateTimeObject($row[5])));
    
        $mda_id = Mda::where('name', $row[2])->orWhere('oracle_name', $row[2])->first();
        if ($mda_id) {
            $mda_id = $mda_id->id;
        }else {
            dd("did not find " .$row[2]);
        }

        $economicCode = EconomyCodeItem::where('code', $row[3])->first();

        if(empty($economicCode) || !$economicCode) {
            dd("did not find " .$row[3]);
        }

        $receipt = new Receipt([

        'receipt_number' => $row[1]  ,
        'mda_name' => $row[2],
        'eco_code' => $economicCode->economyCode->code,
        'eco_code_item' => $row[3] ,
        'activity' => $row[4],
        'amount' =>  str_replace(',', '', $row[5]),
        'receipt_date' =>  Carbon::createFromFormat('d/m/Y', ($row[6]))->format('Y-m-d') ,
        'classification',
        'tag' => $row[7],
        'bank_name' => $row[8],
        'account_number' => $row[9],
        'account_name' => $row[10],
            // 'rejection_reason',
            // 'schedule_id',
            // 'requires_retirement',
            // 'retired_at',
            // 'retirement_receipt_id',
        ]);
        $receipt->save();

        
        

        return [ $receipt];
    }
}
