<?php
// app/Models/ProgrammeCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProgrammeCode extends Model
{
    protected $table = 'programme_codes';
    
    protected $fillable = [
        'code',
        'name',
        'description',
        'budget_code',
        'economic_code_id',
        'approved_budget',
        'utilized_budget',
        'remaining_budget',
        'is_active',
        'financial_year_id',
        'sector',
        'mda_name',
        'mda_admin_code',
        'parent_programme_code_id',
        'is_mda_parent',
        'project_description',
    ];

    protected $casts = [
        'approved_budget' => 'decimal:2',
        'utilized_budget' => 'decimal:2',
        'remaining_budget' => 'decimal:2',
        'is_active' => 'boolean',
        'is_mda_parent' => 'boolean',
    ];

    /**
     * Relationship with Economic Code (must be series 32)
     */
    public function economicCode(): BelongsTo
    {
        return $this->belongsTo(EconomyCode::class, 'economic_code_id');
    }

    /**
     * Relationship with Financial Year
     */
    public function financialYear(): BelongsTo
    {
        return $this->belongsTo(FinancialYear::class, 'financial_year_id');
    }

    /**
     * Relationship with Voucher Items
     */
    public function voucherItems(): HasMany
    {
        return $this->hasMany(VoucherItem::class, 'programme_code_id');
    }

    /**
     * Parent Programme Code (MDA)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ProgrammeCode::class, 'parent_programme_code_id');
    }

    /**
     * Child Programme Codes (Projects under this MDA)
     */
    public function children(): HasMany
    {
        return $this->hasMany(ProgrammeCode::class, 'parent_programme_code_id');
    }

    /**
     * Check if this is a series 32 economic code
     */
    public function getIsSeries32Attribute(): bool
    {
        return $this->economicCode && str_starts_with($this->economicCode->code, '32');
    }

    /**
     * Check if budget is available
     */
    public function hasAvailableBudget(float $amount): bool
    {
        return $this->remaining_budget >= $amount;
    }

    /**
     * Update utilized budget
     */
    public function updateUtilizedBudget(float $amount, bool $increment = true): void
    {
        if ($increment) {
            $this->utilized_budget += $amount;
        } else {
            $this->utilized_budget -= $amount;
        }
        $this->remaining_budget = $this->approved_budget - $this->utilized_budget;
        $this->save();
        
        // Also update parent MDA budget if this is a child
        if ($this->parent) {
            $this->parent->updateMdaBudget();
        }
    }
    
    /**
     * Update MDA parent budget based on children
     */
    public function updateMdaBudget(): void
    {
        if ($this->is_mda_parent) {
            $totalUtilized = $this->children()->sum('utilized_budget');
            $this->utilized_budget = $totalUtilized;
            $this->remaining_budget = $this->approved_budget - $totalUtilized;
            $this->save();
        }
    }

    /**
     * Scope for active programmes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for parent MDAs
     */
    public function scopeMdaParents($query)
    {
        return $query->where('is_mda_parent', true);
    }

    /**
     * Scope for project children
     */
    public function scopeProjects($query)
    {
        return $query->where('is_mda_parent', false);
    }

    /**
     * Scope for series 32 economic codes
     */
    public function scopeSeries32($query)
    {
        return $query->whereHas('economicCode', function ($q) {
            $q->where('code', 'like', '32%');
        });
    }

    public function scopeSearch($query, $search)
    {
        if (empty($search)) {
            return $query;
        }
        
        return $query->where(function ($q) use ($search) {
            $q->where('code', 'like', "%{$search}%")
              ->orWhere('name', 'like', "%{$search}%")
              ->orWhere('project_description', 'like', "%{$search}%")
              ->orWhere('budget_code', 'like', "%{$search}%");
        });
    }
}