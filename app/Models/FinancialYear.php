<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class FinancialYear extends Model
{
    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        // other fields...
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get active financial years
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get current financial year
     */
    public function scopeCurrent(Builder $query): Builder
    {
        $today = now()->format('Y-m-d');
        return $query->where('start_date', '<=', $today)
                    ->where('end_date', '>=', $today)
                    ->where('status', 1);
    }
}
