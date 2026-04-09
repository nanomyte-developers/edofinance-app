<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetirementItem extends Model
{
    protected $fillable = [
        'retirement_voucher_id',
        'voucher_item_id',
        'economic_code_id',
        'economic_code_item_id',
        'description',
        'quantity',
        'unit_price',
        'sub_total',
        'original_amount',
        'is_full_retirement',
        'comments',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'sub_total' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'is_full_retirement' => 'boolean',
    ];

    // Relationships
    public function retirementVoucher()
    {
        return $this->belongsTo(RetirementVoucher::class);
    }

    public function voucherItem()
    {
        return $this->belongsTo(VoucherItem::class);
    }

    public function economicCode()
    {
        return $this->belongsTo(EconomyCode::class);
    }

    // public function codeItem()
    // {
    //     return $this->belongsTo(EconomyCodeItem::class);
    // }
    public function codeItem()
    {
        // Make sure the foreign key matches your database column
        return $this->belongsTo(EconomyCodeItem::class, 'economic_code_item_id');
    }
}
