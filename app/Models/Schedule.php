<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Schedule extends Model
{
    const STATUS_DRAFT = 'Draft';
    const STATUS_PROCESSED = 'Processed'; // When a voucher has been raised from it
    
    protected $fillable = [
        'schedule_number',
        'year_id',
        'mda_id',
        'budget_code_id', // Specific to Schedule
        'created_by_user_id',
        'schedule_date',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'schedule_date' => 'date',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Relationship with FinancialYear
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'year_id');
    }

    /**
     * Relationship with MDA (from administrative_codes table)
     */
    public function mda(): BelongsTo
    {
        return $this->belongsTo(Mda::class, 'mda_id');
    }

    /**
     * Relationship with Budget Code (from administrative_sector_codes table)
     */
    public function budgetCode(): BelongsTo
    {
        return $this->belongsTo(AdministrativeSectorCode::class, 'budget_code_id');
    }

    /**
     * Relationship with Budget Head (The "CODE" on top of the form)
     */
    // public function budgetCode(): BelongsTo
    // {
    //     // Assuming you have a BudgetHead model
    //     return $this->belongsTo(BudgetHead::class, 'budget_code_id');
    // }

    /**
     * Relationship with line items
     */
    public function items(): HasMany
    {
        return $this->hasMany(ScheduleItem::class);
    }

    /**
     * Relationship with creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * Helper to recalculate total from items
     */
    public function calculateTotalAmount(): float
    {
        return $this->items->sum('amount');
    }
    
    /**
     * Boot method to ensure total is accurate on save
     */
    public static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Optional: Any logic before saving
        });
    }

    /**
     * Get all of the Vouchers for the Schedule
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function Vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class, 'schedule_id', 'id');
    }
}
