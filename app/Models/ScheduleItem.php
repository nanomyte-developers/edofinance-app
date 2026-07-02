<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleItem extends Model
{
    protected $fillable = [
        'schedule_id',
        'item_date',
        'serial_number',
        'economy_code_id',
        'economy_code_item_id',
        'payee_name',
        'amount',
    ];

    protected $casts = [
        'item_date' => 'date',
        'amount' => 'decimal:2',
    ];

    /**
     * Get the Economic Code for this item
     */
    public function economyCode(): BelongsTo
    {
        return $this->belongsTo(EconomyCode::class);
    }

    /**
     * Get the Economic Code item for this item
     */
    public function economyCodeItem(): BelongsTo
    {
        return $this->belongsTo(EconomyCodeItem::class);
    }

    /**
     * Get the schedule that owns this item
     */
    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Get the vouchers for this schedule item
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'schedule_item_id');
    }

    /**
     * Get the voucher for this schedule item (single)
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'voucher_id');
    }
}