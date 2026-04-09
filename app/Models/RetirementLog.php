<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RetirementLog extends Model
{
    protected $fillable = [
        'retirement_voucher_id',
        'user_id',
        'action',
        'comment',
        'ip_address',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function retirementVoucher()
    {
        return $this->belongsTo(RetirementVoucher::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
