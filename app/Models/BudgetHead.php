<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BudgetHead extends Model
{
    protected $fillable = [
        'code',
        'description',
        'category', // 'capital', 'recurrent', 'personnel'
        'mda_id',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Scope to get only active heads
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope to filter by MDA or Global
     * Usage: BudgetHead::forMda($mda_id)->get();
     */
    public function scopeForMda(Builder $query, ?int $mdaId): void
    {
        $query->where(function ($q) use ($mdaId) {
            $q->whereNull('mda_id') // Global heads
              ->orWhere('mda_id', $mdaId); // Specific heads
        });
    }

    /**
     * Relationship: The MDA that owns this head (if specific)
     */
    public function mda(): BelongsTo
    {
        return $this->belongsTo(Mda::class);
    }

    /**
     * Relationship: Schedules using this budget head
     */
    public function schedules(): HasMany
    {
        return $this->hasMany(Schedule::class, 'budget_code_id');
    }

    /**
     * Accessor for Dropdown Label
     * Returns: "023300100100 - Overhead Costs"
     */
    public function getLabelTextAttribute(): string
    {
        return "{$this->code} - {$this->description}";
    }
}