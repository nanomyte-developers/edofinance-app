<?php

namespace App\Models;

use App\Models\BankActivity;
use App\Models\CashbookEntry;
use Illuminate\Database\Eloquent\Model;

class Cashbook extends Model
{
    //

    protected $fillable = [
        'cashbook_financial_year_id',
        'bank_activities_id',
        'month_id',
        'year',
        'opening_balance',
        // 'total_payments',
        // 'total_remittances',
        'closing_balance',
        'status',
    ];

    

    public function bankAccount()
    {
        return $this->belongsTo(BankActivity::class, 'bank_activities_id');
    }

    public function treasuryYear()
    {
        return $this->belongsTo(CashbookFinancialYear::class, 'cashbook_financial_year_id');
    }

    public function month()
    {
        return $this->belongsTo(Month::class, 'month_id');
    }

    public function entries()
    {
        return $this->hasMany(CashbookEntry::class)->orderBy('transaction_date');
    }
    
}
