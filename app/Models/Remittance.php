<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Remittance extends Model
{
    protected $fillable = [
        'receipt_number',
        'transfer_date',
        'source_bank_id',
        'destination_bank_id',
        'amount',
        'narration',
        'status',
    ];

    public function sourceBank()
    {
        return $this->belongsTo(BankActivity::class, 'source_bank_id');
    }

    public function destinationBank()
    {
        return $this->belongsTo(BankActivity::class, 'destination_bank_id');
    }
}
