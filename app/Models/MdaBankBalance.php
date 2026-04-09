<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MdaBankBalance extends Model
{
    protected $table = 'mda_bank_balances';

    protected $fillable = [
        'mda_id',
        'bank_id',
        'title',
        'bank_name',
        'account_number',
        'balance_previous_year',

        'balance_current_year',
    ];

    /**
     * Get the MDA that owns the bank balance.
     */
   public function mda()
{
    return $this->belongsTo(Mda::class);
}

public function bank()
{
    return $this->belongsTo(Bank::class);
}
}
