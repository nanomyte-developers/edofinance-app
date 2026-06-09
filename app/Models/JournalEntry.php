<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntry extends Model
{
    protected $table = 'journal_entries';
    protected $fillable = [
        'journal_id',
        'account_code',
        'account_name',
        'description',
        'debit_amount',
        'credit_amount',
        'line_number',
        'cost_center',
        'project_code',
        'department_id',
        'reference',
        'tax_code',
        'tax_amount',
        'currency_code',
        'exchange_rate',
        'foreign_debit',
        'foreign_credit',
        'due_date',
        'reconciled',
        'reconciliation_date',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'foreign_debit' => 'decimal:2',
        'foreign_credit' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'due_date' => 'date',
        'reconciliation_date' => 'date',
        'reconciled' => 'boolean',
        'line_number' => 'integer',
    ];

    /**
     * Get the journal
     */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /**
     * Get the GL account
     */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'account_code', 'account_code');
    }

    /**
     * Get the department
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Calculate the net amount (debit - credit)
     */
    public function getNetAmountAttribute(): float
    {
        return $this->debit_amount - $this->credit_amount;
    }

    /**
     * Check if entry is debit
     */
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    /**
     * Check if entry is credit
     */
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    /**
     * Get entry type
     */
    public function getEntryTypeAttribute(): string
    {
        return $this->isDebit() ? 'Debit' : 'Credit';
    }


    public function EconomicCodeItem()
    {
        return $this->belongsTo(EconomyCodeItem::class, 'account_code', 'code');
    }
    
}
