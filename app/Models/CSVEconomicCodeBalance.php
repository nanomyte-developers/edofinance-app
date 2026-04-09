<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CSVEconomicCodeBalance extends Model
{
    protected $table = 'csv_economic_code_items_balances';
    protected $fillable = ['economic_code_item' , 'amount', 'description'];

    public function economicCodeItem()
    {
        return $this->belongsTo(EconomyCodeItem::class, 'economic_code');
    }
}
