<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashBookBalanceBfw extends Model
{
     protected $table = 'cash_book_balance_bfws';
    protected $fillable = ['financial_year','bank_activity_id', 'amount', 'status'];

    public function bankActivity()
    {
        return $this->belongsTo(BankActivity::class, 'bank_activity_id');
    }
}
