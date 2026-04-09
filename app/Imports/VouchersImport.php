<?php

namespace App\Imports;

use App\Models\Voucher;
use App\Models\VoucherItem;
use App\Models\EconomyCodeItem;
use Maatwebsite\Excel\Concerns\ToModel;
use App\Models\Mda;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date; // Import the Date helper



class VouchersImport implements ToModel
{
    /**
     * @param array $row
     *
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row)
    {

        // dd(Carbon::instance(Date::excelToDateTimeObject($row[5])));

        $mda_id = Mda::where('name', $row[0])->orWhere('oracle_name', $row[0])->orwhere('new_name', $row[0])->first();
        if ($mda_id) {
            $mda_id = $mda_id->id;
        } else {
            dd($row);
        }
        $existing_voucher = Voucher::where('voucher_number', $row[6])->first();
        if ($existing_voucher) {
            return null;
        }

        try {
            $date = Carbon::createFromFormat('d/m/Y', $row[5]);
        } catch (\Exception $e) {
            try {
                $date = Carbon::createFromFormat('d-m-Y', $row[5]);
            } catch (\Exception $e) {
                try {
                    $date = Carbon::instance(Date::excelToDateTimeObject($row[5]));
                } catch (\Exception $e) {
                    try {
                        $date = Carbon::instance(Date::excelToDateTimeObject($row[5]));
                    } catch (\Exception $e) {
                        dd($row);
                    }
                }
            }
        }


        $voucher = new Voucher([
            'voucher_number' => $row[6],
            'year_id'  => 1,
            'mda_id'    => $mda_id,
            'created_by_user_id' => 1,
            'voucher_date' => $date->format('Y-m-d'),
            'narration' => substr($row[9], 0, 250), //$row[9],
            'total_amount' =>  str_replace(' ', '',  str_replace(',', '', $row[7])),
            'status' => 'Draft',
            'voucher_type' => $row[3],
            'current_stage' => 'originator',
            'payee_name' => $row[4],
            // 'rejection_reason',
            // 'schedule_id',
            // 'requires_retirement',
            // 'retired_at',
            // 'retirement_voucher_id',
        ]);
        $voucher->save();

        // $economicCode = EconomyCodeItem::where('code', $row[2])->first();
        $voucher_item = new VoucherItem([
            'voucher_id' => $voucher->id,
            'economy_code_id' => null,  //$economicCode->economyCode->id,
            'economy_code_item_id' => null,
            'budget_code' => $row[1],
            'quantity' => 1,
            'unit_price' => str_replace(' ', '', str_replace(',', '', $row[7])),
            'sub_total' => str_replace(' ', '', str_replace(',', '', $row[7])),
            'created_by_user_id' => 1,
            'description' => substr($row[9], 0, 250),

        ]);


        return [$voucher_item];
    }
}
