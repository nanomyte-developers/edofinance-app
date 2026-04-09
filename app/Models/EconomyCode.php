<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EconomyCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    protected $casts = [
        'status' => 'string',
    ];

    /**
     * Get the items for the Economic Code.
     */
    public function items()
    {
        return $this->hasMany(EconomyCodeItem::class, 'economy_code_id');
    }

    /**
     * Get only active items.
     */
    public function activeItems()
    {
        return $this->hasMany(EconomyCodeItem::class, 'economy_code_id')->where('status', 'active');
    }

    /**
     * Scope a query to only include active Economic Codes.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Find Economic Code by code.
     */
    public static function findByCode($code)
    {
        return static::where('code', $code)->first();
    }
}