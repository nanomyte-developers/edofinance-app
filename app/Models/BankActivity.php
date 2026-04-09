<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\Model;

class BankActivity extends Model
{
    //
    protected $table = 'bank_activities';

    protected $fillable = [
        'tag',
        'bank_name',
        'title',
        'account_number',
        'status',
        'economic_code',
        'balanceBFW',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function cashBookBalanceBfw()
    {
        return $this->hasOne(CashBookBalanceBfw::class, 'bank_activity_id');
    }

    public function cashbooks(): HasMany
    {
        return $this->hasMany(Cashbook::class, 'bank_activities_id');
    }

}