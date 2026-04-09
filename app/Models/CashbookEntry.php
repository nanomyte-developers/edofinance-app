<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbookEntry extends Model
{
    protected $fillable = [
        'cashbook_id',
        'bank_activities_id',
        'user_id',
        'transaction_date',
        'description',
        'amount',
        'type',
        'reference_number',
        'cheque_number',
        'payer_name',
        'payee_name',
        'source_type',
        'source_id',
        'category',
        'sub_category',
        'payment_mode',
        'bank_name',
        'status',
        'is_reconciled',
        'metadata',
        'remarks',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_reconciled' => 'boolean',
        'metadata' => 'array',
    ];

    public function cashbook(): BelongsTo
    {
        return $this->belongsTo(Cashbook::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankActivity::class, 'bank_activities_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
