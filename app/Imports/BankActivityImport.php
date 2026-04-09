<?php

namespace App\Imports;

use App\Models\BankActivity;
use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\EconomyCodeItem;
use Maatwebsite\Excel\Concerns\ToModel;
use App\Models\Mda;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date; // Import the Date helper



class BankActivityImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        // dd(Carbon::instance(Date::excelToDateTimeObject($row[5])));
        if(empty($row[1])) return null; // Skip empty rows ($row);


        return new BankActivity([
            'tag' => $row[1],
            'bank_name'  => $row[2],
            'title'    => $row[3],
            'account_number' => $row[4],
            'status' => 1,

        ]);
    }
}
