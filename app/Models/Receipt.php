<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Receipt extends Model
{
    protected $fillable = [
        'receipt_number',
        'mda_name',
        'eco_code',
        'eco_code_item',
        'activity',
        'amount',
        'receipt_date',
        'classification',
        'tag',
        'bank_name',
        'account_number',
        'account_name',
        'status',
    ];


    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        // 'receipt_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get economy code based on eco_code string (if you need this)
     */
    public function economyCode()
    {
        return EconomyCode::where('code', $this->eco_code)->first();
    }

    /**
     * Get economy code item based on eco_code_item string (if you need this)
     */
    public function economyCodeItem()
    {
        return EconomyCodeItem::where('code', $this->eco_code_item)->first();
    }

    /**
     * Get bank activity (if you store bank_name as string, not ID)
     */
    public function bankActivity()
    {
        return BankActivity::where('account_number', $this->account_number)->first();
    }
}
