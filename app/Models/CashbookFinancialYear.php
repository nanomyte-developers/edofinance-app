<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashbookFinancialYear extends Model
{
    protected $fillable = [
        'name', 
        'financial_year_id', 
        'start_date', 
        'end_date', 
        'opening_balance', 
        'closing_balance', 
        'is_active'
    ];

    // This is what makes $year->financial_year->name work in Vue
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }

    // Relationship to the 12 month cards
    public function months(): HasMany
    {
        return $this->hasMany(Cashbook::class, 'cashbook_financial_year_id');
    }
}
