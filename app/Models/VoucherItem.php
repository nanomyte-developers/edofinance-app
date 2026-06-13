<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoucherItem extends Model
{
    protected $fillable = [
        'voucher_id',
        'description',
        'economy_code_id',
        'economy_code_item_id',
        'quantity',
        'unit_price',
        'sub_total',
        'budget_code',
        'programme_code_id',
        'programme_code',
        'programme_name',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'sub_total' => 'decimal:2',
    ];

    /**
     * Relationship with Voucher
     */
    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    /**
     * Get the Economic Code for this item
     */
    public function economyCode(): BelongsTo
    {
        return $this->belongsTo(EconomyCode::class, 'economy_code_id');
    }

    /**
     * Get the Economic Code item for this item
     */
    public function economyCodeItem(): BelongsTo
    {
        return $this->belongsTo(EconomyCodeItem::class, 'economy_code_item_id');
    }

    /**
     * Get the Programme Code for this item
     */
    public function programmeCode(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCode::class, 'programme_code_id');
    }

    /**
     * Calculate amount automatically
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Auto-calculate sub_total if not set
            if ((empty($model->sub_total) || $model->sub_total == 0) && !empty($model->quantity) && !empty($model->unit_price)) {
                $model->sub_total = $model->quantity * $model->unit_price;
            }
        });
    }
}