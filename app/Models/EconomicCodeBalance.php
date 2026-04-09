<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EconomicCodeBalance extends Model
{
    protected $table = 'economic_code_balances';
    protected $fillable = ['financial_year' , 'amount', 'economic_code', 'status'];

    public function economicCodeItem()
    {
        return $this->belongsTo(EconomyCodeItem::class, 'economic_code');
    }
}
