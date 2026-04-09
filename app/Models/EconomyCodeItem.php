<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EconomyCodeItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'economy_code_id',
        'code',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the Economic Code that owns the item.
     */
    public function economyCode()
    {
        return $this->belongsTo(EconomyCode::class, 'economy_code_id');
    }

    /**
     * Scope a query to only include active items.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Find item by code.
     */
    public static function findByCode($code)
    {
        return static::where('code', $code)->first();
    }

    /**
     * Get items by Economic Code ID.
     */
    public static function getByEconomyCodeId($economyCodeId)
    {
        return static::where('economy_code_id', $economyCodeId)->active()->get();
    }
}