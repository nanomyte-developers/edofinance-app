<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
}